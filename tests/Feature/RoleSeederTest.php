<?php

namespace Tests\Feature;

use App\Helpers\ApiTokenHelper;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('passport:keys', ['--no-interaction' => true]);
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'Test', '--no-interaction' => true]);
    }

    public function test_seeded_roles_and_permissions_use_the_api_guard(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('st_roles', [
            'name' => 'admin',
            'guard_name' => 'api',
        ]);
        $this->assertDatabaseHas('st_roles', [
            'name' => 'user',
            'guard_name' => 'api',
        ]);
        $this->assertDatabaseHas('st_permissions', [
            'name' => 'view users',
            'guard_name' => 'api',
        ]);
        $this->assertDatabaseMissing('st_roles', [
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = User::factory()->create();
        $adminRole = Role::query()->where('name', 'admin')->where('guard_name', 'api')->firstOrFail();
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin, 'api')
            ->withHeaders(['X-API-TOKEN' => ApiTokenHelper::generate()])
            ->getJson('/api/v1/roles');

        $response->assertOk();
    }
}
