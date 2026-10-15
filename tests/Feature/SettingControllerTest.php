<?php

namespace Tests\Feature;

use App\Helpers\ApiTokenHelper;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingControllerTest extends TestCase
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

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['view settings', 'update settings'] as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }
        $this->adminUser->givePermissionTo(Permission::whereIn('name', ['view settings', 'update settings'])->get());
    }

    public function test_index_omits_secret_setting_values(): void
    {
        Setting::query()->create([
            'key' => 'smtp_password',
            'value' => 'super-secret',
            'type' => 'string',
        ]);
        Setting::query()->create([
            'key' => 'smtp_host',
            'value' => '127.0.0.1',
            'type' => 'string',
        ]);

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->getJson('/api/v1/settings');

        $response->assertOk()->assertDontSee('super-secret');
        $items = collect($response->json('data'));
        $secret = $items->firstWhere('key', 'smtp_password');
        $host = $items->firstWhere('key', 'smtp_host');

        $this->assertIsArray($secret);
        $this->assertTrue($secret['secret']);
        $this->assertArrayNotHasKey('value', $secret);
        $this->assertSame('127.0.0.1', $host['value']);
        $this->assertFalse($host['secret']);
    }

    public function test_bulk_update_rejects_unknown_keys_without_writing(): void
    {
        Setting::query()->create([
            'key' => 'send_welcome_email',
            'value' => '1',
            'type' => 'string',
        ]);

        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->patchJson('/api/v1/settings', [
                'settings' => [
                    'send_welcome_email' => '0',
                    'not_a_real_key' => 'x',
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['settings.not_a_real_key']);
        $this->assertDatabaseHas('st_settings', [
            'key' => 'send_welcome_email',
            'value' => '1',
        ]);
        $this->assertDatabaseMissing('st_settings', [
            'key' => 'not_a_real_key',
        ]);
    }

    public function test_bulk_update_writes_an_allowed_key(): void
    {
        $response = $this->actingAs($this->adminUser, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->patchJson('/api/v1/settings', [
                'settings' => [
                    'mail_from_name' => 'Yoru',
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('st_settings', [
            'key' => 'mail_from_name',
            'value' => 'Yoru',
        ]);
    }
}
