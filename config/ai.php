<?php

return [
    'enabled' => (bool) env('AI_INSIGHTS_ENABLED', false),
    'provider' => env('AI_INSIGHTS_PROVIDER', 'generic'),
    'base_url' => env('AI_INSIGHTS_BASE_URL'),
    'api_key' => env('AI_INSIGHTS_API_KEY'),
    'endpoint' => env('AI_INSIGHTS_ENDPOINT'),
    'bearer_token' => env('AI_INSIGHTS_BEARER_TOKEN'),
    'model' => env('AI_INSIGHTS_MODEL'),
    'timeout_seconds' => (int) env('AI_INSIGHTS_TIMEOUT', 20),
    'max_tool_rounds' => (int) env('AI_INSIGHTS_MAX_TOOL_ROUNDS', 4),
    'allow_customer_data' => (bool) env('AI_INSIGHTS_ALLOW_CUSTOMER_DATA', false),
];
