<?php

use App\Models\Ingredient;
use App\Models\IngredientConversion;
use App\Models\IngredientPrice;
use App\Models\Unit;
use App\Models\User;

/**
 * Covers INGR-08 — per-user ingredient price recording.
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

test('the per gram cost is computed correctly when a price is recorded', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $unit = Unit::firstOrCreate(
        ['name' => 'kilogram'],
        ['symbol' => 'kg', 'type' => 'weight', 'base_factor' => 1000.0]
    );

    // €4.20 for 500g => per_gram_cost = 4.20 / 500 = 0.0084
    $gramUnit = Unit::firstOrCreate(
        ['name' => 'gram'],
        ['symbol' => 'g', 'type' => 'weight', 'base_factor' => 1.0]
    );

    $this->actingAs($user)
        ->post("/ingredients/{$ingredient->id}/prices", [
            'amount' => 4.20,
            'quantity' => 500,
            'unit_id' => $gramUnit->id,
            'currency' => 'EUR',
            'recorded_at' => '2026-05-16',
        ]);

    $price = IngredientPrice::where('ingredient_id', $ingredient->id)->first();

    expect($price->per_gram_cost)->not->toBeNull();
    // €4.20 / 500g = 0.0084 per gram
    expect((float) $price->per_gram_cost)->toBe(0.0084);
});

test('per gram cost uses ingredient conversion for non-weight units', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $cupUnit = Unit::firstOrCreate(
        ['name' => 'cup'],
        ['symbol' => 'cup', 'type' => 'volume', 'base_factor' => null]
    );

    // 1 cup = 240g for this ingredient
    IngredientConversion::create([
        'ingredient_id' => $ingredient->id,
        'from_amount' => 1,
        'from_unit_id' => $cupUnit->id,
        'gram_weight' => 240,
        'source' => 'user',
    ]);

    // €2.40 for 1 cup (240g) => per_gram_cost = 2.40 / 240 = 0.01
    $this->actingAs($user)
        ->post("/ingredients/{$ingredient->id}/prices", [
            'amount' => 2.40,
            'quantity' => 1,
            'unit_id' => $cupUnit->id,
            'currency' => 'EUR',
            'recorded_at' => '2026-05-16',
        ])
        ->assertRedirect();

    $price = IngredientPrice::where('ingredient_id', $ingredient->id)->first();
    expect($price)->not->toBeNull();
    expect((float) $price->per_gram_cost)->toBe(0.01);
});

test('validation rejects non-weight unit with no ingredient conversion', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $cupUnit = Unit::firstOrCreate(
        ['name' => 'cup'],
        ['symbol' => 'cup', 'type' => 'volume', 'base_factor' => null]
    );

    // No conversion row for this ingredient + cup combination

    $this->actingAs($user)
        ->post("/ingredients/{$ingredient->id}/prices", [
            'amount' => 2.40,
            'quantity' => 1,
            'unit_id' => $cupUnit->id,
            'currency' => 'EUR',
            'recorded_at' => '2026-05-16',
        ])
        ->assertSessionHasErrors('unit_id');
});

test('price history is per-user private on official ingredients', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ingredient = Ingredient::factory()->create(['user_id' => null]); // official

    IngredientPrice::factory()->count(2)->create([
        'user_id' => $owner->id,
        'ingredient_id' => $ingredient->id,
    ]);

    expect(IngredientPrice::where('user_id', $owner->id)->count())->toBe(2);
    expect(IngredientPrice::where('user_id', $other->id)->count())->toBe(0);
});

test('recording multiple prices keeps full dated history ordered most recent first', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $unit = Unit::firstOrCreate(
        ['name' => 'kilogram'],
        ['symbol' => 'kg', 'type' => 'weight', 'base_factor' => 1000.0]
    );

    $this->actingAs($user)->post("/ingredients/{$ingredient->id}/prices", [
        'amount' => 3.00, 'quantity' => 1, 'unit_id' => $unit->id,
        'currency' => 'EUR', 'recorded_at' => '2026-05-01',
    ]);

    $this->actingAs($user)->post("/ingredients/{$ingredient->id}/prices", [
        'amount' => 4.00, 'quantity' => 1, 'unit_id' => $unit->id,
        'currency' => 'EUR', 'recorded_at' => '2026-05-10',
    ]);

    $prices = IngredientPrice::where('user_id', $user->id)
        ->where('ingredient_id', $ingredient->id)
        ->orderByDesc('recorded_at')
        ->get();

    expect($prices)->toHaveCount(2);
    expect($prices->first()->recorded_at->toDateString())->toBe('2026-05-10');
});

test('amount and quantity must be positive numbers', function () {
    $user = User::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $unit = Unit::firstOrCreate(
        ['name' => 'kilogram'],
        ['symbol' => 'kg', 'type' => 'weight', 'base_factor' => 1000.0]
    );

    $this->actingAs($user)
        ->post("/ingredients/{$ingredient->id}/prices", [
            'amount' => -1,
            'quantity' => 0,
            'unit_id' => $unit->id,
            'currency' => 'EUR',
            'recorded_at' => '2026-05-16',
        ])
        ->assertSessionHasErrors(['amount', 'quantity']);
});

test('recorded_at must be a valid date not in the future', function () {
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
            'recorded_at' => '2099-01-01',
        ])
        ->assertSessionHasErrors('recorded_at');
});
