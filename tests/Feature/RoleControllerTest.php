<?php

namespace Tests\Feature;

use App\Helpers\ApiTokenHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleControllerTest extends TestCase
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

        // Setup role
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $this->adminUser->assignRole($adminRole);
    }

    public function test_can_list_roles()
    {
        Role::create(['name' => 'test_role_1', 'guard_name' => 'api']);
        Role::create(['name' => 'test_role_2', 'guard_name' => 'api']);

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_can_create_role()
    {
        $payload = [
            'name' => 'editor',
        ];

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/roles', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'editor');

        $this->assertDatabaseHas('st_roles', [
            'name' => 'editor',
        ]);
    }

    public function test_can_show_role()
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'api']);

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->getJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $role->id);
    }

    public function test_can_update_role()
    {
        $role = Role::create(['name' => 'old_name', 'guard_name' => 'api']);

        $payload = [
            'name' => 'new_name',
        ];

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->putJson("/api/v1/roles/{$role->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'new_name');

        $this->assertDatabaseHas('st_roles', [
            'id' => $role->id,
            'name' => 'new_name',
        ]);
    }

    public function test_can_delete_role()
    {
        $role = Role::create(['name' => 'to_delete', 'guard_name' => 'api']);

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('st_roles', [
            'id' => $role->id,
        ]);
    }

    public function test_can_bulk_update_roles()
    {
        $role1 = Role::create(['name' => 'bulk_update_1', 'guard_name' => 'api']);
        $role2 = Role::create(['name' => 'bulk_update_2', 'guard_name' => 'api']);

        $payload = [
            'ids' => [$role1->id, $role2->id],
            // Since we use unique:st_roles,name, bulk updating names might fail if not careful.
            // But usually bulk update is for other fields like 'guard_name'.
            'guard_name' => 'api',
        ];

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/roles/bulk-update', $payload);

        $response->assertStatus(200);
    }

    public function test_can_bulk_delete_roles()
    {
        $role1 = Role::create(['name' => 'bulk_delete_1', 'guard_name' => 'api']);
        $role2 = Role::create(['name' => 'bulk_delete_2', 'guard_name' => 'api']);

        $payload = [
            'ids' => [$role1->id, $role2->id],
        ];

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->postJson('/api/v1/roles/bulk-delete', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('st_roles', [
            'id' => $role1->id,
        ]);
        $this->assertDatabaseMissing('st_roles', [
            'id' => $role2->id,
        ]);
    }
}
