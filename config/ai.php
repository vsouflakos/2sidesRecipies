<?php

return [
    // Provider name MUST match a key in config/prism.php 'providers'.
    // Empty string means "not configured" — the "Ask AI" UI is then hidden.
    'provider' => env('AI_PROVIDER', ''),
    'model' => env('AI_MODEL', ''),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 4096),
    // Character budget for the serialized context payload (AI-02 truncation guard).
    'context_budget_chars' => (int) env('AI_CONTEXT_BUDGET_CHARS', 80000),
];
