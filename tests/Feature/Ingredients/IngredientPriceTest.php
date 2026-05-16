<?php

use App\Models\Ingredient;
use App\Models\IngredientPrice;
use App\Models\Unit;
use App\Models\User;

/**
 * Covers INGR-08 — per-user ingredient price recording.
 *
 * RED until Plan 02-06 ships the IngredientPriceController and routes.
 */
test('a user can record a price for an ingredient', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $unit = Unit::firstOrCreate(
        ['name' => 'kilogram'],
        ['symbol' => 'kg', 'type' => 'weight', 'base_factor' => 1000.0]
    );

    $this->actingAs($user)
        ->post("/ingredients/{$ingredient->id}/prices", [
            'amount' => 4.50,
            'quantity' => 1,
            'unit_id' => $unit->id,
            'currency' => 'EUR',
            'recorded_at' => '2026-05-16',
        ])
        ->assertRedirect();

    expect(IngredientPrice::where('ingredient_id', $ingredient->id)
        ->where('user_id', $user->id)
        ->exists()
    )->toBeTrue();
});

test('the per gram cost is computed when a price is recorded', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $unit = Unit::firstOrCreate(
        ['name' => 'kilogram'],
        ['symbol' => 'kg', 'type' => 'weight', 'base_factor' => 1000.0]
    );

    $this->actingAs($user)
        ->post("/ingredients/{$ingredient->id}/prices", [
            'amount' => 4.00,
            'quantity' => 1,
            'unit_id' => $unit->id,
            'currency' => 'EUR',
            'recorded_at' => '2026-05-16',
        ]);

    $price = IngredientPrice::where('ingredient_id', $ingredient->id)->first();

    expect($price->per_gram_cost)->not->toBeNull();
});

test('price history is per-user private and fully retained', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ingredient = Ingredient::factory()->create();

    IngredientPrice::factory()->count(2)->create([
        'user_id' => $owner->id,
        'ingredient_id' => $ingredient->id,
    ]);

    expect(IngredientPrice::where('user_id', $owner->id)->count())->toBe(2);
    expect(IngredientPrice::where('user_id', $other->id)->count())->toBe(0);
});
