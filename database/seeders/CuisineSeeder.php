<?php

namespace Database\Seeders;

use App\Models\Cuisine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CuisineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cuisines = [
            'Greek',
            'Italian',
            'French',
            'Spanish',
            'Mediterranean',
            'American',
            'Mexican',
            'Middle Eastern',
            'Indian',
            'Chinese',
            'Japanese',
            'Thai',
            'Other',
        ];

        foreach ($cuisines as $name) {
            Cuisine::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'slug' => Str::slug($name)]
            );
        }
    }
}
