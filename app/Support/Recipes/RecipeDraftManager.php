<?php

namespace App\Support\Recipes;

use App\Exceptions\DraftSequenceMismatchException;
use App\Models\RecipeDraft;
use App\Models\RecipeDraftEdit;
use Illuminate\Support\Facades\DB;

class RecipeDraftManager
{
    /**
     * Apply an edit to the draft: log the before-state, update draft data, increment sequence.
     *
     * All changes are wrapped in a DB transaction so the edit log and draft row
     * are always consistent.
     *
     * @param  array<string, mixed>  $newData  The new draft data to persist.
     */
    public function applyEdit(RecipeDraft $draft, string $action, array $newData): void
    {
        DB::transaction(function () use ($draft, $action, $newData) {
            RecipeDraftEdit::create([
                'recipe_draft_id' => $draft->id,
                'sequence' => $draft->edit_sequence,
                'action' => $action,
                'before_snapshot' => $draft->data,
            ]);

            $draft->data = $newData;
            $draft->edit_sequence += 1;
            $draft->save();
        });
    }

    /**
     * Recall the last applied edit, restoring the draft to its prior state.
     *
     * Pitfall 5: if $expectedSequence does not match the draft's current edit_sequence,
     * a DraftSequenceMismatchException is thrown (maps to 409 in the controller).
     *
     * @param  int  $expectedSequence  The sequence number the caller believes is current.
     * @return array<string, mixed> The restored draft data.
     *
     * @throws DraftSequenceMismatchException If the sequence is out of sync.
     */
    public function recall(RecipeDraft $draft, int $expectedSequence): array
    {
        if ($expectedSequence !== $draft->edit_sequence) {
            throw new DraftSequenceMismatchException($expectedSequence, $draft->edit_sequence);
        }

        $lastEdit = $draft->edits()->orderByDesc('sequence')->first();

        if ($lastEdit === null) {
            return $draft->data;
        }

        DB::transaction(function () use ($draft, $lastEdit) {
            $draft->data = $lastEdit->before_snapshot;
            $draft->edit_sequence -= 1;
            $draft->save();

            $lastEdit->delete();
        });

        return $draft->data;
    }
}
