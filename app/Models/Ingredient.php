<?php

namespace App\Models;

use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'source',
        'source_id',
        'usda_fdc_id',
        'name_cache',
        'verified',
        'verified_by',
        'verified_at',
        'data_hash',
        'foodex2_code',
        // Nutrition columns
        'energy_kcal',
        'protein_g',
        'fat_g',
        'saturated_fat_g',
        'monounsaturated_fat_g',
        'polyunsaturated_fat_g',
        'carbs_g',
        'sugars_g',
        'starch_g',
        'fibre_g',
        'sodium_mg',
        'calcium_mg',
        'iron_mg',
        'magnesium_mg',
        'phosphorus_mg',
        'potassium_mg',
        'zinc_mg',
        'vitamin_a_ug',
        'vitamin_b1_mg',
        'vitamin_b2_mg',
        'vitamin_b3_mg',
        'vitamin_b6_mg',
        'vitamin_b9_ug',
        'vitamin_b12_ug',
        'vitamin_c_mg',
        'vitamin_d_ug',
        'vitamin_e_mg',
        'vitamin_k_ug',
        'cholesterol_mg',
        // Reserved frozen-dessert columns
        'total_solids_pct',
        'fat_pct',
        'msnf_pct',
        'sugar_pct',
        'other_solids_pct',
        'water_pct',
        'pac_coefficient',
        'pod_coefficient',
        'de_value',
        'brix',
        'ingredient_class',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'verified_at' => 'datetime',
            // Nutrition casts
            'energy_kcal' => 'decimal:4',
            'protein_g' => 'decimal:4',
            'fat_g' => 'decimal:4',
            'saturated_fat_g' => 'decimal:4',
            'monounsaturated_fat_g' => 'decimal:4',
            'polyunsaturated_fat_g' => 'decimal:4',
            'carbs_g' => 'decimal:4',
            'sugars_g' => 'decimal:4',
            'starch_g' => 'decimal:4',
            'fibre_g' => 'decimal:4',
            'sodium_mg' => 'decimal:4',
            'calcium_mg' => 'decimal:4',
            'iron_mg' => 'decimal:4',
            'magnesium_mg' => 'decimal:4',
            'phosphorus_mg' => 'decimal:4',
            'potassium_mg' => 'decimal:4',
            'zinc_mg' => 'decimal:4',
            'vitamin_a_ug' => 'decimal:4',
            'vitamin_b1_mg' => 'decimal:4',
            'vitamin_b2_mg' => 'decimal:4',
            'vitamin_b3_mg' => 'decimal:4',
            'vitamin_b6_mg' => 'decimal:4',
            'vitamin_b9_ug' => 'decimal:4',
            'vitamin_b12_ug' => 'decimal:4',
            'vitamin_c_mg' => 'decimal:4',
            'vitamin_d_ug' => 'decimal:4',
            'vitamin_e_mg' => 'decimal:4',
            'vitamin_k_ug' => 'decimal:4',
            'cholesterol_mg' => 'decimal:4',
            // Frozen-dessert column casts
            'total_solids_pct' => 'decimal:4',
            'fat_pct' => 'decimal:4',
            'msnf_pct' => 'decimal:4',
            'sugar_pct' => 'decimal:4',
            'other_solids_pct' => 'decimal:4',
            'water_pct' => 'decimal:4',
            'pac_coefficient' => 'decimal:4',
            'pod_coefficient' => 'decimal:4',
            'de_value' => 'decimal:4',
            'brix' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(IngredientTranslation::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(IngredientConversion::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(IngredientPrice::class);
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'ingredient_allergen')
            ->withPivot('state')
            ->withTimestamps();
    }

    /**
     * Get the ingredient name for the given locale, falling back to English then a dash.
     */
    public function nameFor(string $locale): string
    {
        return $this->translations->firstWhere('locale', $locale)?->name
            ?? $this->translations->firstWhere('locale', 'en')?->name
            ?? '—';
    }

    /**
     * Determine if this ingredient is an official (non-user) ingredient.
     */
    public function isOfficial(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Determine if this ingredient is a private (user-owned) ingredient.
     */
    public function isPrivate(): bool
    {
        return $this->user_id !== null;
    }
}
