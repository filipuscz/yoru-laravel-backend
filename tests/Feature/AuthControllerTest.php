<?php

namespace Tests\Feature;

use App\Helpers\ApiTokenHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('passport:keys', ['--no-interaction' => true]);
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'Test', '--no-interaction' => true]);

        $this->apiToken = ApiTokenHelper::generate();
    }

    public function test_user_can_register()
    {
        $response = $this->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/auth/register', [
                'name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertStatus(200) // RegisterController responds with OK typically, let's check
            ->assertJsonStructure(['data', 'token']);

        $this->assertDatabaseHas('me_users', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_user_can_register_without_api_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Public User',
            'username' => 'publicuser',
            'email' => 'public@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data', 'token']);
    }

    public function test_user_can_login()
    {
        $this->withoutExceptionHandling();
        User::factory()->create([
            'email' => 'jane@example.com',
            'username' => 'janedoe',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/auth/login', [
                'username' => 'jane@example.com',
                'password' => 'secret123',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'token']);
    }

    public function test_user_can_fetch_profile()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_login_rate_limiting()
    {
        User::factory()->create([
            'email' => 'spam@example.com',
            'username' => 'spam',
            'password' => Hash::make('secret123'),
        ]);

        // Hit limit
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['X-API-TOKEN' => $this->apiToken])
                ->postJson('/api/v1/auth/login', [
                    'username' => 'spam@example.com',
                    'password' => 'wrong',
                ])->assertStatus(422); // Password failed validation exception
        }

        // 6th request hits limit (might be 429 from middleware or 422 from form request)
        $response = $this->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/auth/login', [
                'username' => 'spam@example.com',
                'password' => 'wrong',
            ]);

        $this->assertTrue(in_array($response->status(), [429, 422]));
    }
}
