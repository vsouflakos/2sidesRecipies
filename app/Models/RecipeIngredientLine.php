<?php

namespace App\Models;

use Database\Factories\RecipeIngredientLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredientLine extends Model
{
    /** @use HasFactory<RecipeIngredientLineFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recipe_id',
        'section_id',
        'ingredient_id',
        'sub_recipe_version_id',
        'quantity',
        'unit_id',
        'quantity_g',
        'prep_note',
        'yield_pct',
        'is_flour_base',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'quantity_g' => 'decimal:6',
            'yield_pct' => 'decimal:4',
            'is_flour_base' => 'boolean',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(RecipeSection::class, 'section_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function subRecipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'sub_recipe_version_id');
    }

    /**
     * Determine if this line is a sub-recipe reference rather than a raw ingredient.
     */
    public function isSubRecipe(): bool
    {
        return $this->sub_recipe_version_id !== null;
    }
}
