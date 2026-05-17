<?php

namespace App\Models;

use App\Enums\Difficulty;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'hero_image_path',
        'yield_amount',
        'yield_unit_id',
        'portions',
        'portion_size_g',
        'prep_time_minutes',
        'cook_time_minutes',
        'difficulty',
        'cuisine_id',
        'notes',
        'current_version_id',
        'selling_price',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
            'yield_amount' => 'decimal:4',
            'portions' => 'decimal:4',
            'portion_size_g' => 'decimal:4',
            'selling_price' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cuisine(): BelongsTo
    {
        return $this->belongsTo(Cuisine::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'recipe_tag');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(RecipeSection::class);
    }

    public function ingredientLines(): HasMany
    {
        return $this->hasMany(RecipeIngredientLine::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'current_version_id');
    }

    public function draft(): HasOne
    {
        return $this->hasOne(RecipeDraft::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(RecipeTest::class);
    }

    public function latestTest(): HasOne
    {
        return $this->hasOne(RecipeTest::class)->latestOfMany('tested_at');
    }
}
