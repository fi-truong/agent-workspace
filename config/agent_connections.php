<?php

return [
    // Emergency rollback: disable this flag and clear Laravel's configuration
    // cache. Saved connections remain intact, while chat uses the existing flow.
    'enabled' => (bool) env('AI_PLUS_AGENT_CONNECTIONS_ENABLED', true),
];
