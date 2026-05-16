<?php

namespace App\Models;

use Database\Factories\IngredientCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngredientCategory extends Model
{
    /** @use HasFactory<IngredientCategoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['parent_id', 'name', 'slug', 'sort_order'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(IngredientCategory::class, 'parent_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'category_id');
    }
}
