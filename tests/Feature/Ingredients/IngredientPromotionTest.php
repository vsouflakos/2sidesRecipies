<?php

use App\Enums\SubmissionStatus;
use App\Models\Ingredient;
use App\Models\IngredientSubmission;
use App\Models\User;
use App\Notifications\IngredientDecisionNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * Covers INGR-11 — convert-in-place promotion, rejection revert, and notifications.
 *
 * RED until Plan 07-02 ships IngredientSubmissionController (admin) and routes.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// INGR-11: Approval promotes ingredient in place — user_id becomes null, verified = true
test('approval promotes ingredient to official with user_id null and verified true', function () {
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

    $this->actingAs($moderator)->post(
        route('admin.ingredient-submissions.approve', $submission),
    );

    $ingredient->refresh();
    expect($ingredient->user_id)->toBeNull();
    expect($ingredient->submission_status)->toBe(SubmissionStatus::Approved);
    expect($ingredient->verified)->toBeTrue();
    expect($ingredient->verified_by)->toBe($moderator->id);
    expect($ingredient->verified_at)->not->toBeNull();
});

// INGR-11: Promoted ingredient is visible to a different user
test('promoted ingredient is visible to another authenticated user', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $submitter = User::factory()->create();
    $submitter->assignRole('User');

    $viewer = User::factory()->create();
    $viewer->assignRole('User');

    $ingredient = Ingredient::factory()->private($submitter)->submitted()->create();
    $submission = IngredientSubmission::factory()->create([
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $submitter->id,
        'status' => SubmissionStatus::Submitted->value,
    ]);

    $this->actingAs($moderator)->post(
        route('admin.ingredient-submissions.approve', $submission),
    );

    // Another user can now see the promoted official ingredient
    $response = $this->actingAs($viewer)->get(route('ingredients.show', $ingredient));
    $response->assertOk();
});

// INGR-11: Submitter's recipe referencing ingredient still resolves after promotion
test('the ingredient id is unchanged after promotion so existing recipe references hold', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $submitter = User::factory()->create();
    $submitter->assignRole('User');

    $ingredient = Ingredient::factory()->private($submitter)->submitted()->create();
    $originalId = $ingredient->id;

    $submission = IngredientSubmission::factory()->create([
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $submitter->id,
        'status' => SubmissionStatus::Submitted->value,
    ]);

    $this->actingAs($moderator)->post(
        route('admin.ingredient-submissions.approve', $submission),
    );

    $ingredient->refresh();

    // The ingredient's id must remain the same after promotion (convert-in-place)
    expect($ingredient->id)->toBe($originalId);

    // The ingredient is still findable by its original id
    $found = Ingredient::withTrashed()->find($originalId);
    expect($found)->not->toBeNull();
    expect($found->user_id)->toBeNull();
});

// INGR-11: Rejection reverts — submission_status becomes rejected, user_id unchanged
test('rejection reverts ingredient to rejected status with user_id unchanged', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $submitter = User::factory()->create();
    $submitter->assignRole('User');

    $ingredient = Ingredient::factory()->private($submitter)->submitted()->create();
    $originalUserId = $submitter->id;

    $submission = IngredientSubmission::factory()->create([
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $submitter->id,
        'status' => SubmissionStatus::Submitted->value,
    ]);

    $this->actingAs($moderator)->post(
        route('admin.ingredient-submissions.reject', $submission),
        ['notes' => 'Insufficient nutritional data provided.'],
    );

    $ingredient->refresh();
    expect($ingredient->submission_status)->toBe(SubmissionStatus::Rejected);
    expect($ingredient->user_id)->toBe($originalUserId);
    expect($ingredient->verified)->toBeFalse();
});

// INGR-11: Submitter receives a database notification on approval
test('submitter receives a database notification when their ingredient is approved', function () {
    Notification::fake();

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

    $this->actingAs($moderator)->post(
        route('admin.ingredient-submissions.approve', $submission),
    );

    Notification::assertSentTo($submitter, IngredientDecisionNotification::class);
});

// INGR-11: Submitter receives a database notification on rejection
test('submitter receives a database notification when their ingredient is rejected', function () {
    Notification::fake();

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

    $this->actingAs($moderator)->post(
        route('admin.ingredient-submissions.reject', $submission),
        ['notes' => 'Missing allergen information.'],
    );

    Notification::assertSentTo($submitter, IngredientDecisionNotification::class);
});
