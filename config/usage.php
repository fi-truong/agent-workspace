<?php

return [
    'phase' => env('AI_USAGE_PHASE', 'testing'),
    // Optional reporting start date when usage needs to be reviewed by phase.
    'phase_started_at' => env('AI_USAGE_PHASE_STARTED_AT'),
];
