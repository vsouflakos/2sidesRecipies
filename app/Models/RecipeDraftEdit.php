<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeDraftEdit extends Model
{
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'recipe_draft_id',
        'sequence',
        'action',
        'before_snapshot',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(RecipeDraft::class, 'recipe_draft_id');
    }
}
