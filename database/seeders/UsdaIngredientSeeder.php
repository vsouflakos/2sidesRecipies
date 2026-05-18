<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsdaIngredientSeeder extends Seeder
{
    /**
     * Import the bundled USDA FoodData Central datasets (Foundation Foods and
     * SR Legacy) into the ingredients table.
     *
     * Delegates to the ingredients:import-usda command, which with no options
     * imports every dataset directory under storage/app/private. The command
     * is idempotent, so re-seeding never duplicates rows.
     */
    public function run(): void
    {
        $this->command->call('ingredients:import-usda');
    }
}
