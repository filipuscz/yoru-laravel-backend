<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Tests\TestCase;

class McpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('passport:keys', ['--no-interaction' => true]);
    }

    public function test_guest_cannot_initialize_the_mcp_server(): void
    {
        $this->postJson('/mcp/v1', $this->initializeRequest())
            ->assertUnauthorized();
    }

    public function test_guest_cannot_discover_the_mcp_server(): void
    {
        $request = $this->discoverRequest();

        $this->postJson('/mcp/v1', $request, $this->mcpHeaders($request))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_discover_the_empty_mcp_server(): void
    {
        $user = User::factory()->create();

        Passport::actingAs($user, ['mcp:use'], 'api');

        $request = $this->discoverRequest();

        $response = $this->postJson('/mcp/v1', $request, $this->mcpHeaders($request))
            ->assertOk()
            ->assertJsonPath('result.supportedVersions.0', '2026-07-28')
            ->assertJsonPath('result.capabilities.tools.listChanged', false)
            ->assertJsonPath('result.capabilities.resources.listChanged', false)
            ->assertJsonPath('result.capabilities.prompts.listChanged', false)
            ->assertJsonPath(
                'result.instructions',
                'This server is an intentionally empty, authenticated template. Add narrowly scoped tools only after validating their inputs, authorizing the authenticated user, and delegating domain work to application services.',
            );

        $meta = $response->json('result._meta');

        $this->assertIsArray($meta);

        $serverInfo = $meta['io.modelcontextprotocol/serverInfo'];

        $this->assertIsArray($serverInfo);

        $this->assertSame('Backend Template MCP Server', $serverInfo['name']);
        $this->assertSame('1.0.0', $serverInfo['version']);
    }

    public function test_authenticated_user_can_initialize_the_empty_mcp_server(): void
    {
        $user = User::factory()->create();

        Passport::actingAs($user, ['mcp:use'], 'api');

        $this->postJson('/mcp/v1', $this->initializeRequest())
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Backend Template MCP Server')
            ->assertJsonPath('result.serverInfo.version', '1.0.0')
            ->assertJsonPath('result.capabilities.tools.listChanged', false)
            ->assertJsonPath('result.capabilities.resources.listChanged', false)
            ->assertJsonPath('result.capabilities.prompts.listChanged', false);
    }

    public function test_mcp_requests_are_rate_limited(): void
    {
        $user = User::factory()->create();

        Passport::actingAs($user, ['mcp:use'], 'api');

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson('/mcp/v1', $this->initializeRequest())->assertOk();
        }

        $this->postJson('/mcp/v1', $this->initializeRequest())
            ->assertTooManyRequests();
    }

    public function test_oauth_metadata_and_client_registration_respect_the_allowlist(): void
    {
        config([
            'mcp.authorization_server' => 'https://api.example.test',
            'mcp.redirect_domains' => ['https://client.example.test'],
            'mcp.custom_schemes' => ['cursor'],
        ]);

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('issuer', 'https://api.example.test')
            ->assertJsonPath('scopes_supported.0', 'mcp:use');

        $this->postJson('/oauth/register', [
            'client_name' => 'Allowed Client',
            'redirect_uris' => ['https://client.example.test/callback'],
        ])->assertCreated()
            ->assertJsonPath('scope', 'mcp:use');

        $this->postJson('/oauth/register', [
            'client_name' => 'Rejected Client',
            'redirect_uris' => ['https://untrusted.example.test/callback'],
        ])->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');

        $this->postJson('/oauth/register', [
            'client_name' => 'Desktop Client',
            'redirect_uris' => ['cursor://oauth/callback'],
        ])->assertCreated();
    }

    /**
     * @return array<string, mixed>
     */
    private function discoverRequest(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'server/discover',
            'params' => [
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => [],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, string>
     */
    private function mcpHeaders(array $message): array
    {
        return [
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => (string) $message['method'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function initializeRequest(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => [],
                'clientInfo' => [
                    'name' => 'Template test client',
                    'version' => '1.0.0',
                ],
            ],
        ];
    }
}
