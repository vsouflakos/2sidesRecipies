<?php

use App\Enums\SubmissionStatus;
use App\Models\Ingredient;
use App\Models\IngredientSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers INGR-09 — submit, freeze-while-pending, withdraw, and resubmission.
 *
 * RED until Plan 07-02 ships IngredientSubmissionController and routes.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// INGR-09: Owner can submit a private ingredient for inclusion
test('owner can submit a private ingredient and status becomes submitted', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $ingredient = Ingredient::factory()->private($owner)->create();

    $response = $this->actingAs($owner)->post(route('ingredients.submit', $ingredient));

    $response->assertRedirect();

    $ingredient->refresh();
    expect($ingredient->submission_status)->toBe(SubmissionStatus::Submitted);

    $this->assertDatabaseHas('ingredient_submissions', [
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $owner->id,
        'status' => SubmissionStatus::Submitted->value,
        'submission_number' => 1,
    ]);
});

// INGR-09: Non-owner cannot submit another user's private ingredient
test('non-owner cannot submit another users private ingredient', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $otherUser = User::factory()->create();
    $otherUser->assignRole('User');

    $ingredient = Ingredient::factory()->private($owner)->create();

    $response = $this->actingAs($otherUser)->post(route('ingredients.submit', $ingredient));

    $response->assertForbidden();
});

// INGR-09: Owner cannot submit an already-submitted ingredient
test('owner cannot submit an already submitted ingredient', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $ingredient = Ingredient::factory()->private($owner)->submitted()->create();

    $response = $this->actingAs($owner)->post(route('ingredients.submit', $ingredient));

    $response->assertForbidden();
});

// INGR-09: Frozen while pending — owner cannot edit a submitted ingredient
test('owner cannot edit a submitted ingredient while it is pending review', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $ingredient = Ingredient::factory()->private($owner)->submitted()->create();

    $response = $this->actingAs($owner)->put(route('ingredients.update', $ingredient), [
        'name' => 'Updated Name',
        'name_en' => 'Updated Name',
    ]);

    $response->assertForbidden();
});

// INGR-09: Owner can withdraw a pending submission
test('owner can withdraw a pending submission and ingredient reverts to private', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $ingredient = Ingredient::factory()->private($owner)->submitted()->create();

    IngredientSubmission::factory()->create([
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $owner->id,
        'status' => SubmissionStatus::Submitted->value,
        'submission_number' => 1,
    ]);

    $response = $this->actingAs($owner)->delete(route('ingredients.withdraw', $ingredient));

    $response->assertRedirect();

    $ingredient->refresh();
    expect($ingredient->submission_status)->toBe(SubmissionStatus::Private);

    $this->assertDatabaseHas('ingredient_submissions', [
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $owner->id,
        'status' => SubmissionStatus::Withdrawn->value,
    ]);
});

// INGR-09: After withdrawal, the ingredient is unfrozen and editable again
test('ingredient is editable again after withdrawal', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $ingredient = Ingredient::factory()->private($owner)->submitted()->create();

    IngredientSubmission::factory()->create([
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $owner->id,
        'status' => SubmissionStatus::Submitted->value,
        'submission_number' => 1,
    ]);

    $this->actingAs($owner)->delete(route('ingredients.withdraw', $ingredient));

    $ingredient->refresh();
    expect($ingredient->submission_status)->toBe(SubmissionStatus::Private);

    // After withdrawal the ingredient is no longer frozen
    $response = $this->actingAs($owner)->put(route('ingredients.update', $ingredient), [
        'name' => 'Updated After Withdrawal',
        'name_en' => 'Updated After Withdrawal',
    ]);

    // Should not be forbidden (may redirect or succeed)
    $response->assertStatus(302);
});

// INGR-09: Resubmission after rejection creates a new submission row with submission_number = 2
test('resubmission after rejection creates a new submission row with incremented submission_number', function () {
    $owner = User::factory()->create();
    $owner->assignRole('User');

    $ingredient = Ingredient::factory()->private($owner)->rejected()->create();

    // Existing rejection submission row
    IngredientSubmission::factory()->create([
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $owner->id,
        'status' => SubmissionStatus::Rejected->value,
        'submission_number' => 1,
        'reviewed_at' => now(),
    ]);

    $response = $this->actingAs($owner)->post(route('ingredients.submit', $ingredient));

    $response->assertRedirect();

    $ingredient->refresh();
    expect($ingredient->submission_status)->toBe(SubmissionStatus::Submitted);

    $this->assertDatabaseHas('ingredient_submissions', [
        'ingredient_id' => $ingredient->id,
        'submitted_by' => $owner->id,
        'status' => SubmissionStatus::Submitted->value,
        'submission_number' => 2,
    ]);
});
