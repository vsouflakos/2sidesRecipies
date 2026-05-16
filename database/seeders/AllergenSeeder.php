<?php

namespace Database\Seeders;

use App\Models\Allergen;
use Illuminate\Database\Seeder;

class AllergenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Source: EU Regulation 1169/2011 Annex II — 14 mandatory allergens.
     */
    public function run(): void
    {
        $allergens = [
            ['name' => 'Gluten',       'slug' => 'gluten',      'note' => 'Cereals containing gluten (wheat, rye, barley, oats, spelt)'],
            ['name' => 'Crustaceans',  'slug' => 'crustaceans', 'note' => null],
            ['name' => 'Eggs',         'slug' => 'eggs',        'note' => null],
            ['name' => 'Fish',         'slug' => 'fish',        'note' => null],
            ['name' => 'Peanuts',      'slug' => 'peanuts',     'note' => null],
            ['name' => 'Soybeans',     'slug' => 'soybeans',    'note' => null],
            ['name' => 'Milk',         'slug' => 'milk',        'note' => 'Including lactose'],
            ['name' => 'Tree nuts',    'slug' => 'tree-nuts',   'note' => 'Almonds, hazelnuts, walnuts, cashews, pecans, Brazil nuts, pistachios, macadamia nuts'],
            ['name' => 'Celery',       'slug' => 'celery',      'note' => null],
            ['name' => 'Mustard',      'slug' => 'mustard',     'note' => null],
            ['name' => 'Sesame seeds', 'slug' => 'sesame',      'note' => null],
            ['name' => 'Sulphites',    'slug' => 'sulphites',   'note' => 'Sulphur dioxide and sulphites above 10 mg/kg'],
            ['name' => 'Lupin',        'slug' => 'lupin',       'note' => null],
            ['name' => 'Molluscs',     'slug' => 'molluscs',    'note' => null],
        ];

        foreach ($allergens as $allergen) {
            Allergen::firstOrCreate(['slug' => $allergen['slug']], $allergen);
        }
    }
}
