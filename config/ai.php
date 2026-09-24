<?php

return [
    'enabled' => (bool) env('AI_INSIGHTS_ENABLED', false),
    'endpoint' => env('AI_INSIGHTS_ENDPOINT'),
    'bearer_token' => env('AI_INSIGHTS_BEARER_TOKEN'),
    'model' => env('AI_INSIGHTS_MODEL'),
    'timeout_seconds' => (int) env('AI_INSIGHTS_TIMEOUT', 20),
];
