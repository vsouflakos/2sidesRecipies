<?php

namespace App\Models;

use Database\Factories\RecipeDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeDraft extends Model
{
    /** @use HasFactory<RecipeDraftFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recipe_id',
        'user_id',
        'data',
        'edit_sequence',
        'cached_nutrition_json',
        'cached_cost_per_portion',
        'cached_allergen_slugs',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'edit_sequence' => 'integer',
            'cached_nutrition_json' => 'array',
            'cached_cost_per_portion' => 'decimal:4',
            'cached_allergen_slugs' => 'array',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function edits(): HasMany
    {
        return $this->hasMany(RecipeDraftEdit::class);
    }
}
