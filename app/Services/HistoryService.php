<?php

namespace App\Services;

use App\Models\Example;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HistoryService
{
    public function resolveRecordId(string $source, string $identifier): ?int
    {
        return match ($source) {
            'examples' => $this->idFrom(Example::withTrashed(), $identifier),
            'users' => $this->idFrom(User::withTrashed(), $identifier),
            'user_details' => $this->userDetailId($identifier),
            'settings' => $this->settingId($identifier),
            'roles' => $this->idFromName(Role::query(), $identifier),
            'permissions' => $this->idFromName(Permission::query(), $identifier),
            default => null,
        };
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function paginate(string $source, int $recordId, int $perPage): LengthAwarePaginator
    {
        $history = $this->source($source);

        return DB::table($history['table'])
            ->select(['id', 'action', 'changes', 'created_at'])
            ->where($history['column'], $recordId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(string $source, int $recordId, int $historyId): ?object
    {
        $history = $this->source($source);

        return DB::table($history['table'])
            ->where('id', $historyId)
            ->where($history['column'], $recordId)
            ->first();
    }

    /**
     * @return array{table: string, column: string}
     */
    private function source(string $source): array
    {
        $history = config("history.sources.{$source}");

        if (! is_array($history) || ! isset($history['table'], $history['column'])) {
            throw new \InvalidArgumentException('Unknown history source.');
        }

        return [
            'table' => (string) $history['table'],
            'column' => (string) $history['column'],
        ];
    }

    /**
     * @param  Builder<Example>|Builder<User>|Builder<Setting>|Builder<Role>|Builder<Permission>|Builder<UserDetail>  $query
     */
    private function idFrom(Builder $query, string $identifier): ?int
    {
        if (! ctype_digit($identifier)) {
            return null;
        }

        $id = $query->whereKey($identifier)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  Builder<Role>|Builder<Permission>  $query
     */
    private function idFromName(Builder $query, string $identifier): ?int
    {
        if (ctype_digit($identifier)) {
            return $this->idFrom($query, $identifier);
        }

        $id = $query->where('name', $identifier)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function settingId(string $identifier): ?int
    {
        if (ctype_digit($identifier)) {
            return $this->idFrom(Setting::query(), $identifier);
        }

        $id = Setting::query()->where('key', $identifier)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function userDetailId(string $identifier): ?int
    {
        $userId = $this->idFrom(User::withTrashed(), $identifier);
        if ($userId === null) {
            return null;
        }

        $id = UserDetail::withTrashed()->where('user_id', $userId)->value('id');

        return $id === null ? null : (int) $id;
    }
}
