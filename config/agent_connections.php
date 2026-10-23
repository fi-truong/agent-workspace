<?php

return [
    // Emergency rollback: disable this flag and clear Laravel's configuration
    // cache. Saved connections remain intact, while chat uses the existing flow.
    'enabled' => (bool) env('AI_PLUS_AGENT_CONNECTIONS_ENABLED', true),
    'max_connections' => 10,
    'max_calls' => 10,
    'max_turn_tokens' => 32000,
    'timeout_seconds' => 300,
    'delegate_max_tokens' => 2048,
];
