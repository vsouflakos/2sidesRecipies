<?php

namespace App\Models;

use Database\Factories\RecipeSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeSection extends Model
{
    /** @use HasFactory<RecipeSectionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recipe_id',
        'name',
        'order',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredientLines(): HasMany
    {
        return $this->hasMany(RecipeIngredientLine::class, 'section_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class, 'section_id');
    }
}
