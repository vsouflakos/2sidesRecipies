<?php

namespace App\Models;

use App\Enums\TestType;
use App\Enums\TestVerdict;
use Database\Factories\RecipeTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeTest extends Model
{
    /** @use HasFactory<RecipeTestFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recipe_id',
        'recipe_version_id',
        'user_id',
        'type',
        'tested_at',
        'tasting_notes',
        'overall_rating',
        'ratings',
        'hypothesis',
        'outcome_narrative',
        'verdict',
        'change_rows',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TestType::class,
            'verdict' => TestVerdict::class,
            'ratings' => 'array',
            'change_rows' => 'array',
            'tested_at' => 'datetime',
            'overall_rating' => 'integer',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RecipeTestPhoto::class)->orderBy('order');
    }
}
