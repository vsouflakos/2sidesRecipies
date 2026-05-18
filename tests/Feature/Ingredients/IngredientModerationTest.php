<?php

use App\Enums\SubmissionStatus;
use App\Models\Ingredient;
use App\Models\IngredientSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers INGR-10 — moderator review queue, approve, reject, and permission gates.
 *
 * RED until Plan 07-02 ships IngredientSubmissionController (admin) and routes.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// INGR-10: Moderator can view the review queue
test('moderator can view the ingredient review queue', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $response = $this->actingAs($moderator)->get(route('admin.ingredients.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/ingredients')
        ->has('submissions')
    );
});

// INGR-10: Queue items are ordered FIFO (oldest submitted_at first)
test('queue items are ordered oldest submitted_at first', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $submitter = User::factory()->create();
    $submitter->assignRole('User');

    $olderIngredient = Ingredient::factory()->private($submitter)->submitted()->create();
    $olderSubmission = IngredientSubmission::factory()->create([
        'ingredient_id' => $olderIngredient->id,
        'submitted_by' => $submitter->id,
        'status' => SubmissionStatus::Submitted->value,
        'submitted_at' => now()->subHours(2),
    ]);

    $newerIngredient = Ingredient::factory()->private($submitter)->submitted()->create();
    $newerSubmission = IngredientSubmission::factory()->create([
        'ingredient_id' => $newerIngredient->id,
        'submitted_by' => $submitter->id,
        'status' => SubmissionStatus::Submitted->value,
        'submitted_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($moderator)->get(route('admin.ingredients.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/ingredients')
        ->has('submissions', 2)
        ->where('submissions.0.id', $olderSubmission->id)
        ->where('submissions.1.id', $newerSubmission->id)
    );
});

// INGR-10: Moderator can approve a submission
test('moderator can approve a submission with optional notes', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $submitter = User::factory()->create();
    $submitter->assignRole('User');

    $ingredient = Ingredient::factory()->private($submitter)->submitted()->create();
    $submission = IngredientSubmission::factory()->create([
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $submitter->id,
        'status' => SubmissionStatus::Submitted->value,
    ]);

    $response = $this->actingAs($moderator)->post(
        route('admin.ingredient-submissions.approve', $submission),
        ['notes' => 'Looks good!'],
    );

    $response->assertRedirect();

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::Approved);
    expect($submission->reviewed_by)->toBe($moderator->id);
});

// INGR-10: Moderator cannot reject without notes
test('moderator cannot reject a submission without providing notes', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $submitter = User::factory()->create();
    $submitter->assignRole('User');

    $ingredient = Ingredient::factory()->private($submitter)->submitted()->create();
    $submission = IngredientSubmission::factory()->create([
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $submitter->id,
        'status' => SubmissionStatus::Submitted->value,
    ]);

    $response = $this->actingAs($moderator)->post(
        route('admin.ingredient-submissions.reject', $submission),
        [],
    );

    $response->assertStatus(422);
});

// INGR-10: Moderator can reject with notes
test('moderator can reject a submission with required notes', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $submitter = User::factory()->create();
    $submitter->assignRole('User');

    $ingredient = Ingredient::factory()->private($submitter)->submitted()->create();
    $submission = IngredientSubmission::factory()->create([
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $submitter->id,
        'status' => SubmissionStatus::Submitted->value,
    ]);

    $response = $this->actingAs($moderator)->post(
        route('admin.ingredient-submissions.reject', $submission),
        ['notes' => 'Missing allergen information and insufficient nutritional data.'],
    );

    $response->assertRedirect();

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::Rejected);
    expect($submission->notes)->toBe('Missing allergen information and insufficient nutritional data.');
});

// INGR-10: Plain User cannot access the review queue
test('a plain user without review-ingredients permission cannot access the review queue', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $response = $this->actingAs($user)->get(route('admin.ingredients.index'));

    $response->assertForbidden();
});
