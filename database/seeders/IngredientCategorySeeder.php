<?php

namespace Database\Seeders;

use App\Models\IngredientCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IngredientCategorySeeder extends Seeder
{
    /**
     * The category tree data: 13 top-level categories, each with subcategories.
     *
     * @var array<int, array{name: string, subcategories: list<string>}>
     */
    private array $categories = [
        ['name' => 'Vegetables', 'subcategories' => [
            'Leafy Greens',
            'Root Vegetables',
            'Brassicas',
            'Alliums',
            'Fruiting Vegetables',
            'Fungi',
        ]],
        ['name' => 'Fruits', 'subcategories' => [
            'Citrus',
            'Stone Fruits',
            'Berries & Small Fruits',
            'Tropical Fruits',
            'Pomes',
        ]],
        ['name' => 'Grains & Starches', 'subcategories' => [
            'Wheat & Flours',
            'Rice & Other Grains',
            'Legumes',
            'Pasta & Noodles',
            'Bread & Bakery Products',
        ]],
        ['name' => 'Dairy & Eggs', 'subcategories' => [
            'Milk & Cream',
            'Cheese',
            'Yoghurt & Fermented',
            'Butter & Ghee',
            'Eggs',
        ]],
        ['name' => 'Meat & Poultry', 'subcategories' => [
            'Beef & Veal',
            'Pork',
            'Lamb & Mutton',
            'Poultry',
            'Processed Meats & Charcuterie',
        ]],
        ['name' => 'Fish & Seafood', 'subcategories' => [
            'Finfish',
            'Shellfish & Crustaceans',
            'Preserved & Smoked Fish',
        ]],
        ['name' => 'Oils, Fats & Condiments', 'subcategories' => [
            'Vegetable Oils',
            'Animal Fats',
            'Vinegars & Acids',
            'Sauces & Condiments',
        ]],
        ['name' => 'Herbs & Spices', 'subcategories' => [
            'Fresh Herbs',
            'Dried Spices',
        ]],
        ['name' => 'Nuts & Seeds', 'subcategories' => [
            'Nuts',
            'Seeds',
            'Nut Butters & Pastes',
        ]],
        ['name' => 'Sweeteners & Sugar Products', 'subcategories' => [
            'Sugars & Syrups',
            'Honey & Natural Sweeteners',
            'Artificial & Low-Calorie Sweeteners',
        ]],
        ['name' => 'Beverages', 'subcategories' => [
            'Juices & Nectars',
            'Soft Drinks',
            'Alcoholic Beverages',
            'Hot Beverages',
        ]],
        ['name' => 'Prepared & Convenience Foods', 'subcategories' => [
            'Stocks & Broths',
            'Canned & Preserved',
            'Frozen Prepared',
        ]],
        ['name' => 'Other / Uncategorised', 'subcategories' => [
            'Food Additives & Starches',
            'Supplements & Enrichments',
        ]],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->categories as $sortOrder => $categoryData) {
            $categorySlug = Str::slug($categoryData['name']);

            $parent = IngredientCategory::firstOrCreate(
                ['slug' => $categorySlug],
                [
                    'name' => $categoryData['name'],
                    'parent_id' => null,
                    'sort_order' => $sortOrder,
                ]
            );

            foreach ($categoryData['subcategories'] as $subSortOrder => $subName) {
                $subSlug = Str::slug($subName);

                IngredientCategory::firstOrCreate(
                    ['slug' => $subSlug],
                    [
                        'name' => $subName,
                        'parent_id' => $parent->id,
                        'sort_order' => $subSortOrder,
                    ]
                );
            }
        }
    }
}
