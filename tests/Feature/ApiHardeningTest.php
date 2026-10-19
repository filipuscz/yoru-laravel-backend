<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
    public function test_v1_routes_use_the_api_rate_limiter(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.languages.show');

        $this->assertNotNull($route);
        $this->assertContains('throttle:api', $route->gatherMiddleware());
    }

    public function test_password_grant_is_disabled(): void
    {
        $this->assertFalse(Passport::$passwordGrantEnabled);
    }

    public function test_malformed_api_token_returns_unauthorized(): void
    {
        $this->withHeader('X-API-TOKEN', 'not-base64!')
            ->getJson('/api/v1/examples')
            ->assertUnauthorized();
    }
}
