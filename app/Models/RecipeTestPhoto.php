<?php

namespace App\Models;

use Database\Factories\RecipeTestPhotoFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RecipeTestPhoto extends Model
{
    /** @use HasFactory<RecipeTestPhotoFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recipe_test_id',
        'path',
        'order',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function recipeTest(): BelongsTo
    {
        return $this->belongsTo(RecipeTest::class);
    }

    public function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk(config('filesystems.default', 'public'))->url($this->path),
        );
    }
}
