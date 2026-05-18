<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\IngredientDetailResource;
use App\Http\Resources\IngredientSubmissionResource;
use App\Models\IngredientSubmission;
use Inertia\Inertia;
use Inertia\Response;

class IngredientReviewController extends Controller
{
    /**
     * Display the FIFO-ordered moderator review queue.
     *
     * Returns all submitted ingredients ordered oldest first (FIFO) so the
     * moderator processes the queue in submission order.
     */
    public function index(): Response
    {
        $rows = IngredientSubmission::where('status', SubmissionStatus::Submitted->value)
            ->with(['submittedBy', 'ingredient.category', 'ingredient.allergens', 'ingredient.conversions'])
            ->orderBy('submitted_at')
            ->get();

        return Inertia::render('admin/ingredients', [
            'submissions' => IngredientSubmissionResource::collection($rows)->resolve(),
        ]);
    }

    /**
     * Display the full review screen for a single ingredient submission.
     *
     * Loads all relations needed for the moderator review UI including prior
     * rejection context, translations, and full ingredient detail data.
     */
    public function show(IngredientSubmission $submission): Response
    {
        $submission->load([
            'submittedBy',
            'reviewedBy',
            'ingredient.translations',
            'ingredient.allergens',
            'ingredient.conversions.unit',
            'ingredient.category.parent',
            'ingredient.submissions.reviewedBy',
        ]);

        return Inertia::render('admin/ingredients/show', [
            'submission' => (new IngredientSubmissionResource($submission))->resolve(),
            'ingredient' => (new IngredientDetailResource($submission->ingredient))->resolve(),
        ]);
    }
}
