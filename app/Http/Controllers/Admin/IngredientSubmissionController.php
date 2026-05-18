<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveIngredientRequest;
use App\Http\Requests\Admin\RejectIngredientRequest;
use App\Models\IngredientSubmission;
use App\Notifications\IngredientDecisionNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class IngredientSubmissionController extends Controller
{
    /**
     * Approve a pending ingredient submission.
     *
     * Promotes the ingredient in place (user_id → null, verified → true) and
     * sends a database notification to the submitter after the transaction commits.
     */
    public function approve(ApproveIngredientRequest $request, IngredientSubmission $submission): RedirectResponse
    {
        DB::transaction(function () use ($request, $submission) {
            $ingredient = $submission->ingredient;

            $ingredient->update([
                'user_id' => null,
                'submission_status' => SubmissionStatus::Approved,
                'verified' => true,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);

            $submission->update([
                'status' => SubmissionStatus::Approved->value,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'notes' => $request->validated('notes'),
            ]);
        });

        $submission->refresh();
        $submission->submittedBy?->notify(new IngredientDecisionNotification(
            $submission->ingredient->id,
            $submission->ingredient->name_cache,
            'approved',
            $request->validated('notes'),
        ));

        return redirect()->route('admin.ingredients.index')
            ->with('success', __('app.ingredients.approve_toast'));
    }

    /**
     * Reject a pending ingredient submission.
     *
     * Reverts the ingredient's submission_status to Rejected (user_id unchanged)
     * and sends a database notification to the submitter after the transaction commits.
     */
    public function reject(RejectIngredientRequest $request, IngredientSubmission $submission): RedirectResponse
    {
        DB::transaction(function () use ($request, $submission) {
            $submission->ingredient->update([
                'submission_status' => SubmissionStatus::Rejected,
            ]);

            $submission->update([
                'status' => SubmissionStatus::Rejected->value,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'notes' => $request->validated('notes'),
            ]);
        });

        $submission->refresh();
        $submission->submittedBy?->notify(new IngredientDecisionNotification(
            $submission->ingredient->id,
            $submission->ingredient->name_cache,
            'rejected',
            $request->validated('notes'),
        ));

        return redirect()->route('admin.ingredients.index')
            ->with('success', __('app.ingredients.reject_toast'));
    }
}
