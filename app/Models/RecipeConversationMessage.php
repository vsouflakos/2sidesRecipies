<?php

namespace App\Models;

use Database\Factories\RecipeConversationMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeConversationMessage extends Model
{
    /** @use HasFactory<RecipeConversationMessageFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['recipe_conversation_id', 'role', 'content', 'proposal_state'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['proposal_state' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(RecipeConversation::class, 'recipe_conversation_id');
    }
}
