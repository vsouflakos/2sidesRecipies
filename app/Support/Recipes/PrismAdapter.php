<?php

namespace App\Support\Recipes;

class PrismAdapter
{
    public function provider(): string
    {
        return (string) config('ai.provider', '');
    }

    public function model(): string
    {
        return (string) config('ai.model', '');
    }

    public function maxTokens(): int
    {
        return (int) config('ai.max_tokens', 4096);
    }

    /** The AI feature is available only when both provider and model are configured. */
    public function isConfigured(): bool
    {
        return filled($this->provider()) && filled($this->model());
    }
}
