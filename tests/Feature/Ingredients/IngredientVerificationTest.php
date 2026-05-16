<?php

use App\Models\Ingredient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Covers the verify action — global ingredient verification.
 *
 * RED until Plans 02-02 and 02-05 ship the IngredientVerificationController
 * and the data-hash reset on re-import.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a moderator can verify an ingredient', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $ingredient = Ingredient::factory()->create(['verified' => false]);

    $this->actingAs($moderator)
        ->post("/admin/ingredients/{$ingredient->id}/verify")
        ->assertRedirect();

    $ingredient->refresh();

    expect($ingredient->verified)->toBeTrue();
    expect($ingredient->verified_by)->toBe($moderator->id);
    expect($ingredient->verified_at)->not->toBeNull();
});

test('a plain user cannot verify an ingredient', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $ingredient = Ingredient::factory()->create(['verified' => false]);

    $this->actingAs($user)
        ->post("/admin/ingredients/{$ingredient->id}/verify")
        ->assertForbidden();

    expect($ingredient->fresh()->verified)->toBeFalse();
});

test('re-importing changed data resets verification to false', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $ingredient = Ingredient::factory()->verified($moderator)->create([
        'source' => 'ciqual',
        'source_id' => 'sample-001',
        'data_hash' => md5('original-payload'),
    ]);

    $fixture = base_path('tests/fixtures/ciqual-sample.xml');
    $this->artisan('ingredients:import-ciqual', ['--source-file' => $fixture]);

    expect($ingredient->fresh()->verified)->toBeFalse();
});
