<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Backend Template MCP Server')]
#[Version('1.0.0')]
#[Instructions('This server is an intentionally empty, authenticated template. Add narrowly scoped tools only after validating their inputs, authorizing the authenticated user, and delegating domain work to application services.')]
class TemplateServer extends Server
{
    protected array $tools = [];

    protected array $resources = [];

    protected array $prompts = [];
}
