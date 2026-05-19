<?php

namespace App\Models;

use Database\Factories\RecipeConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeConversation extends Model
{
    /** @use HasFactory<RecipeConversationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['recipe_id', 'agent_status', 'agent_error', 'agent_started_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['agent_started_at' => 'datetime'];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RecipeConversationMessage::class)->orderBy('created_at');
    }
}
