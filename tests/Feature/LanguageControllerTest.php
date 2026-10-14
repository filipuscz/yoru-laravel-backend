<?php

namespace Tests\Feature;

use Tests\TestCase;

class LanguageControllerTest extends TestCase
{
    /**
     * Test fetching an existing language (e.g., 'en').
     */
    public function test_it_can_fetch_existing_translations_for_en(): void
    {
        $response = $this->getJson('/api/v1/languages/en');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'messages',
                    'validation',
                    'auth',
                ],
            ]);
    }

    /**
     * Test fetching a non-existent language returns 404.
     */
    public function test_it_returns_404_for_non_existent_language(): void
    {
        $response = $this->getJson('/api/v1/languages/xx-non-existent');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Language not found');
    }

    /**
     * Test that the language endpoint does not require an auth token.
     */
    public function test_it_is_accessible_without_auth_token(): void
    {
        // This is essentially the same as the first test but explicitly verifies
        // that a guest request (no actingAs) succeeds.
        $response = $this->getJson('/api/v1/languages/en');

        $response->assertStatus(200);
    }
}
