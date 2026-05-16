<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            // Weight
            ['name' => 'gram',       'symbol' => 'g',     'type' => 'weight', 'base_factor' => 1.0],
            ['name' => 'kilogram',   'symbol' => 'kg',    'type' => 'weight', 'base_factor' => 1000.0],
            ['name' => 'ounce',      'symbol' => 'oz',    'type' => 'weight', 'base_factor' => 28.3495],
            ['name' => 'pound',      'symbol' => 'lb',    'type' => 'weight', 'base_factor' => 453.592],
            // Volume
            ['name' => 'milliliter', 'symbol' => 'ml',   'type' => 'volume', 'base_factor' => 1.0],
            ['name' => 'liter',      'symbol' => 'l',    'type' => 'volume', 'base_factor' => 1000.0],
            ['name' => 'teaspoon',   'symbol' => 'tsp',  'type' => 'volume', 'base_factor' => 4.92892],
            ['name' => 'tablespoon', 'symbol' => 'tbsp', 'type' => 'volume', 'base_factor' => 14.7868],
            ['name' => 'cup',        'symbol' => 'cup',  'type' => 'volume', 'base_factor' => 236.588],
            // Count
            ['name' => 'piece',      'symbol' => 'pc',    'type' => 'count',  'base_factor' => null],
            ['name' => 'slice',      'symbol' => 'sl',    'type' => 'count',  'base_factor' => null],
            ['name' => 'bunch',      'symbol' => 'bunch', 'type' => 'count',  'base_factor' => null],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['name' => $unit['name']], $unit);
        }
    }
}
