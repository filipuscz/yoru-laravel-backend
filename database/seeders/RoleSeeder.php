<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions securely
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions (Best Practice: Resource-based permissions)
        $permissions = [
            'view users',
            'create users',
            'update users',
            'delete users',
            'view examples',
            'create examples',
            'update examples',
            'delete examples',
            'view settings',
            'update settings',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        // Seed initial SMTP settings
        $settings = [
            'send_welcome_email' => '1',
            'smtp_host' => '127.0.0.1',
            'smtp_port' => '2525',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'mail_from_address' => 'hello@example.com',
            'mail_from_name' => 'Laravel',
        ];
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $apiPermissions = Permission::query()->where('guard_name', 'api')->get();

        $adminRole = Role::updateOrCreate([
            'name' => 'admin',
            'guard_name' => 'api',
        ]);
        $adminRole->givePermissionTo($apiPermissions);

        $userRole = Role::updateOrCreate([
            'name' => 'user',
            'guard_name' => 'api',
        ]);
        $userRole->givePermissionTo(
            $apiPermissions->where('name', 'view examples')->values()
        );
    }
}
