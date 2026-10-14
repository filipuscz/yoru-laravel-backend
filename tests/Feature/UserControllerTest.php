<?php

namespace Tests\Feature;

use App\Helpers\ApiTokenHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiToken;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('passport:keys', ['--no-interaction' => true]);
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'Test', '--no-interaction' => true]);

        $this->apiToken = ApiTokenHelper::generate();
        $this->adminUser = User::factory()->create();

        // Setup permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $permissions = ['view users', 'create users', 'update users', 'delete users'];
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }
        $this->adminUser->givePermissionTo(Permission::whereIn('name', $permissions)->get());
    }

    public function test_can_list_users()
    {
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_create_user()
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'username' => 'testuser',
            'password' => 'secret123',
        ];

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/users', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'testuser@example.com');

        $this->assertDatabaseHas('me_users', [
            'email' => 'testuser@example.com',
            'username' => 'testuser',
        ]);
    }

    public function test_created_user_can_log_in_with_the_given_password(): void
    {
        $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/users', [
                'name' => 'Loginable User',
                'email' => 'loginable@example.com',
                'username' => 'loginable',
                'password' => 'secret123',
            ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $this->postJson('/api/v1/auth/login', [
            'username' => 'loginable@example.com',
            'password' => 'secret123',
        ])->assertOk();
    }

    public function test_can_show_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_can_update_user()
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->putJson("/api/v1/users/{$user->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('me_users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200);

        // Since it's soft delete, assert soft deleted or check database directly
        $this->assertSoftDeleted('me_users', [
            'id' => $user->id,
        ]);
    }

    public function test_can_bulk_update_users()
    {
        $users = User::factory()->count(3)->create();
        $ids = $users->pluck('id')->toArray();

        $payload = [
            'ids' => $ids,
            'name' => 'Bulk Updated Name',
        ];

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/users/bulk-update', $payload);

        $response->assertStatus(200);

        foreach ($ids as $id) {
            $this->assertDatabaseHas('me_users', [
                'id' => $id,
                'name' => 'Bulk Updated Name',
            ]);
        }
    }

    public function test_can_bulk_delete_users()
    {
        $users = User::factory()->count(3)->create();
        $ids = $users->pluck('id')->toArray();

        $payload = [
            'ids' => $ids,
        ];

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/users/bulk-delete', $payload);

        $response->assertStatus(200);

        foreach ($ids as $id) {
            $this->assertSoftDeleted('me_users', [
                'id' => $id,
            ]);
        }
    }
}
