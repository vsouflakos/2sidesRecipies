<?php

namespace App\Models;

use Database\Factories\RecipeVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeVersion extends Model
{
    /** @use HasFactory<RecipeVersionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recipe_id',
        'version_number',
        'committed_by',
        'committed_at',
        'change_note',
        'snapshot',
        'yield_g',
        'cached_nutrition_json',
        'cached_cost_per_gram',
        'cached_cost_per_portion',
        'cached_allergen_slugs',
        'cached_selling_price',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'committed_at' => 'datetime',
            'snapshot' => 'array',
            'cached_nutrition_json' => 'array',
            'cached_allergen_slugs' => 'array',
            'yield_g' => 'decimal:4',
            'cached_cost_per_gram' => 'decimal:8',
            'cached_cost_per_portion' => 'decimal:4',
            'cached_selling_price' => 'decimal:4',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function committedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committed_by');
    }
}
