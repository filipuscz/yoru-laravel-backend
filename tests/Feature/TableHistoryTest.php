<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TableHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_history_omits_secrets_and_records_soft_delete(): void
    {
        $user = User::factory()->create();

        $created = $this->historyRow('me_user_hist', 'user_id', $user->getKey(), 'created');
        $snapshot = $this->snapshot($created);
        $this->assertArrayNotHasKey('password', $snapshot);
        $this->assertArrayNotHasKey('remember_token', $snapshot);
        $this->assertSame($user->name, $snapshot['name']);

        $user->update(['name' => 'History User']);

        $updated = $this->historyRow('me_user_hist', 'user_id', $user->getKey(), 'updated');
        $changes = $this->changes($updated);
        $this->assertSame('History User', $changes['name']);
        $this->assertArrayNotHasKey('password', $changes);

        $user->delete();

        $this->assertDatabaseHas('me_user_hist', [
            'user_id' => $user->getKey(),
            'action' => 'deleted',
        ]);
    }

    public function test_user_detail_history_omits_transaction_pin(): void
    {
        $user = User::factory()->create();
        $detail = UserDetail::query()->create([
            'user_id' => $user->getKey(),
            'transaction_pin' => '1234',
            'phone_number' => '0800000000',
        ]);

        $created = $this->historyRow('me_user_detail_hist', 'user_detail_id', $detail->getKey(), 'created');
        $this->assertArrayNotHasKey('transaction_pin', $this->snapshot($created));

        $detail->update(['phone_number' => '0811111111']);

        $updated = $this->historyRow('me_user_detail_hist', 'user_detail_id', $detail->getKey(), 'updated');
        $this->assertSame('0811111111', $this->changes($updated)['phone_number']);
        $this->assertArrayNotHasKey('transaction_pin', $this->changes($updated));

        $detail->delete();

        $this->assertDatabaseHas('me_user_detail_hist', [
            'user_detail_id' => $detail->getKey(),
            'action' => 'deleted',
        ]);
    }

    public function test_post_history_records_create_update_and_soft_delete(): void
    {
        $post = Post::factory()->create();

        $this->assertDatabaseHas('tb_post_hist', [
            'post_id' => $post->getKey(),
            'action' => 'created',
        ]);

        $post->update(['title' => 'Changed title']);

        $updated = $this->historyRow('tb_post_hist', 'post_id', $post->getKey(), 'updated');
        $this->assertSame('Changed title', $this->snapshot($updated)['title']);
        $this->assertSame('Changed title', $this->changes($updated)['title']);

        $post->delete();

        $this->assertDatabaseHas('tb_post_hist', [
            'post_id' => $post->getKey(),
            'action' => 'deleted',
        ]);
        $this->assertSame(3, DB::table('tb_post_hist')->where('post_id', $post->getKey())->count());
    }

    public function test_setting_history_records_create_update_and_delete(): void
    {
        $setting = Setting::query()->create([
            'key' => 'site_name',
            'value' => 'Yoru',
            'type' => 'string',
        ]);

        $this->assertDatabaseHas('st_setting_hist', [
            'setting_id' => $setting->getKey(),
            'action' => 'created',
        ]);

        $setting->update(['value' => 'Yoru API']);

        $updated = $this->historyRow('st_setting_hist', 'setting_id', $setting->getKey(), 'updated');
        $this->assertSame('Yoru API', $this->changes($updated)['value']);

        $setting->delete();

        $this->assertDatabaseHas('st_setting_hist', [
            'setting_id' => $setting->getKey(),
            'action' => 'deleted',
        ]);
    }

    public function test_role_history_records_create_update_and_delete(): void
    {
        $role = Role::query()->create([
            'name' => 'history-role',
            'guard_name' => 'api',
        ]);

        $this->assertDatabaseHas('st_role_hist', [
            'role_id' => $role->getKey(),
            'action' => 'created',
        ]);

        $role->update(['name' => 'history-role-renamed']);

        $updated = $this->historyRow('st_role_hist', 'role_id', $role->getKey(), 'updated');
        $this->assertSame('history-role-renamed', $this->changes($updated)['name']);

        $role->delete();

        $this->assertDatabaseHas('st_role_hist', [
            'role_id' => $role->getKey(),
            'action' => 'deleted',
        ]);
    }

    public function test_permission_history_records_create_update_and_delete(): void
    {
        $permission = Permission::query()->create([
            'name' => 'history permission',
            'guard_name' => 'api',
        ]);

        $this->assertDatabaseHas('st_permission_hist', [
            'permission_id' => $permission->getKey(),
            'action' => 'created',
        ]);

        $permission->update(['name' => 'history permission renamed']);

        $updated = $this->historyRow('st_permission_hist', 'permission_id', $permission->getKey(), 'updated');
        $this->assertSame('history permission renamed', $this->changes($updated)['name']);

        $permission->delete();

        $this->assertDatabaseHas('st_permission_hist', [
            'permission_id' => $permission->getKey(),
            'action' => 'deleted',
        ]);
    }

    private function historyRow(string $table, string $column, int|string $id, string $action): object
    {
        $row = DB::table($table)->where($column, $id)->where('action', $action)->first();
        $this->assertNotNull($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(object $row): array
    {
        $decoded = json_decode((string) $row->snapshot, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function changes(object $row): array
    {
        $decoded = json_decode((string) $row->changes, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
