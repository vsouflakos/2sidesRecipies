<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class IngredientDecisionNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly int $ingredientId,
        public readonly string $ingredientName,
        public readonly string $decision,
        public readonly ?string $notes,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'ingredient_id' => $this->ingredientId,
            'ingredient_name' => $this->ingredientName,
            'decision' => $this->decision,
            'notes' => $this->notes,
        ];
    }
}
