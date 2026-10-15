<?php

namespace Tests\Feature;

use App\Helpers\ApiTokenHelper;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserDetail;
use App\Services\ExampleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HistoryControllerTest extends TestCase
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
        $permissions = ['view users', 'view examples', 'view settings'];
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }
        $this->adminUser->givePermissionTo(Permission::whereIn('name', $permissions)->get());
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $this->adminUser->assignRole($adminRole);
    }

    public function test_example_history_lists_changes_and_shows_one_snapshot(): void
    {
        $service = app(ExampleService::class);
        $example = $service->create($this->exampleAttributes('original'));
        $service->update(['string_column' => 'changed'], $example->getKey());
        $other = $service->create($this->exampleAttributes('other'));

        $list = $this->getJsonAs($this->adminUser, '/api/v1/examples/'.$example->getKey().'/histories');
        $list->assertOk()
            ->assertJsonPath('data.0.action', 'updated')
            ->assertJsonPath('data.0.changes.string_column', 'changed')
            ->assertJsonMissingPath('data.0.snapshot');

        $historyId = $list->json('data.0.id');
        $show = $this->getJsonAs($this->adminUser, '/api/v1/examples/'.$example->getKey().'/histories/'.$historyId);
        $show->assertOk()
            ->assertJsonPath('data.snapshot.string_column', 'changed');

        $otherHistoryId = $this->getJsonAs($this->adminUser, '/api/v1/examples/'.$other->getKey().'/histories')->json('data.0.id');
        $this->getJsonAs($this->adminUser, '/api/v1/examples/'.$example->getKey().'/histories/'.$otherHistoryId)
            ->assertNotFound();
    }

    public function test_example_history_requires_view_permission(): void
    {
        $example = app(ExampleService::class)->create($this->exampleAttributes('original'));
        $guest = User::factory()->create();

        $this->getJsonAs($guest, '/api/v1/examples/'.$example->getKey().'/histories')
            ->assertForbidden();
    }

    public function test_user_history_omits_password(): void
    {
        $list = $this->getJsonAs($this->adminUser, '/api/v1/users/'.$this->adminUser->getKey().'/histories');
        $list->assertOk()->assertJsonPath('data.0.action', 'created');

        $historyId = $list->json('data.0.id');
        $show = $this->getJsonAs($this->adminUser, '/api/v1/users/'.$this->adminUser->getKey().'/histories/'.$historyId);
        $show->assertOk()->assertJsonMissingPath('data.snapshot.password');
        $this->assertSame($this->adminUser->name, $show->json('data.snapshot.name'));
    }

    public function test_user_detail_history_omits_transaction_pin(): void
    {
        UserDetail::query()->create([
            'user_id' => $this->adminUser->getKey(),
            'transaction_pin' => '1234',
            'phone_number' => '0800000000',
        ]);

        $list = $this->getJsonAs($this->adminUser, '/api/v1/users/'.$this->adminUser->getKey().'/details/histories');
        $list->assertOk()->assertJsonPath('data.0.action', 'created')->assertJsonMissingPath('data.0.snapshot');

        $historyId = $list->json('data.0.id');
        $this->getJsonAs($this->adminUser, '/api/v1/users/'.$this->adminUser->getKey().'/details/histories/'.$historyId)
            ->assertOk()
            ->assertJsonMissingPath('data.snapshot.transaction_pin')
            ->assertJsonPath('data.snapshot.phone_number', '0800000000');
    }

    public function test_setting_history_can_be_read_by_key(): void
    {
        Setting::query()->create([
            'key' => 'smtp_host',
            'value' => '127.0.0.1',
            'type' => 'string',
        ]);

        $this->getJsonAs($this->adminUser, '/api/v1/settings/smtp_host/histories')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'created')
            ->assertJsonMissingPath('data.0.snapshot');
    }

    public function test_role_and_permission_history_are_readable_by_an_admin(): void
    {
        $role = Role::create(['name' => 'history-reader', 'guard_name' => 'api']);
        $permission = Permission::create(['name' => 'history permission', 'guard_name' => 'api']);

        $this->getJsonAs($this->adminUser, '/api/v1/roles/'.$role->getKey().'/histories')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'created');
        $this->getJsonAs($this->adminUser, '/api/v1/permissions/'.$permission->getKey().'/histories')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'created');
    }

    private function getJsonAs(User $user, string $uri): TestResponse
    {
        return $this->actingAs($user, 'api')
            ->withHeaders(['X-API-TOKEN' => $this->apiToken])
            ->getJson($uri);
    }

    /**
     * @return array<string, mixed>
     */
    private function exampleAttributes(string $stringColumn): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'ulid' => (string) Str::ulid(),
            'string_column' => $stringColumn,
            'string_with_length' => 'length',
            'char_column' => 'ABCD',
            'text_column' => 'text',
            'medium_text_column' => 'medium',
            'long_text_column' => 'long',
            'integer_column' => 1,
            'big_integer_column' => 1,
            'medium_integer_column' => 1,
            'small_integer_column' => 1,
            'tiny_integer_column' => 1,
            'unsigned_integer_column' => 1,
            'decimal_column' => 10.50,
            'double_column' => 1.5,
            'float_column' => 1.5,
            'boolean_column' => false,
            'date_column' => '2026-01-01',
            'datetime_column' => '2026-01-01 00:00:00',
            'datetime_tz_column' => '2026-01-01 00:00:00',
            'time_column' => '00:00:00',
            'time_tz_column' => '00:00:00',
            'year_column' => 2026,
            'enum_column' => 'active',
            'set_column' => 'one',
        ];
    }
}
