<?php

return [
    'redirect_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_REDIRECT_DOMAINS', '')),
    ))),

    'custom_schemes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_CUSTOM_SCHEMES', '')),
    ))),

    'authorization_server' => env('MCP_AUTHORIZATION_SERVER'),

    /*
    |--------------------------------------------------------------------------
    | Tool Search
    |--------------------------------------------------------------------------
    |
    | Limits enforced when a client searches or executes tools through the
    | generated tool-search helpers.
    |
    */

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],
];
