<?php

use App\Mcp\Servers\TemplateServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/v1', TemplateServer::class)
    ->middleware(['auth:api', 'throttle:mcp']);
