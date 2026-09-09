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
];
