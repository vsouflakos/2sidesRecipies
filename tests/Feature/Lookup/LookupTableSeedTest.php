<?php

use App\Models\Allergen;
use App\Models\Unit;
use Database\Seeders\AllergenSeeder;
use Database\Seeders\UnitSeeder;

test('allergen lookup table seeds the 14 EU allergens', function () {
    $this->seed(AllergenSeeder::class);

    expect(Allergen::count())->toBe(14);
});

test('unit lookup table is seeded with weight, volume and count units', function () {
    $this->seed(UnitSeeder::class);

    expect(Unit::count())->toBeGreaterThan(0);

    expect(Unit::where('type', 'weight')->exists())->toBeTrue();
    expect(Unit::where('type', 'volume')->exists())->toBeTrue();
    expect(Unit::where('type', 'count')->exists())->toBeTrue();
});
