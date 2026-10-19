<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequestIdMiddlewareTest extends TestCase
{
    public function test_response_includes_the_clients_valid_request_id(): void
    {
        $this->withHeader('X-Request-ID', 'frontend-request-123')
            ->getJson('/api/v1/languages/en')
            ->assertOk()
            ->assertHeader('X-Request-ID', 'frontend-request-123');
    }

    public function test_response_generates_a_request_id_when_missing(): void
    {
        $response = $this->getJson('/api/v1/languages/en');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $response->headers->get('X-Request-ID'),
        );
    }
}
