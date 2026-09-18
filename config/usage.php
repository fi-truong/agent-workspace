<?php

return [
    'phase' => env('AI_USAGE_PHASE', 'testing'),
    // Set this when switching phase so the new quota starts from that date.
    'phase_started_at' => env('AI_USAGE_PHASE_STARTED_AT'),
    'token_limits' => [
        'testing' => (int) env('AI_TESTING_TOKEN_LIMIT', 20_000_000),
        'training' => (int) env('AI_TRAINING_TOKEN_LIMIT', 10_000_000),
    ],
];
