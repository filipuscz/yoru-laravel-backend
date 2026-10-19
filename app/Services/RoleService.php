<?php

namespace App\Services;

use App\Enums\QueryAcceptedComparatorEnum;
use App\Services\Concerns\UsesVersionedCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class RoleService extends BaseService
{
    use UsesVersionedCache;

    protected array $searchableColumns = [
        'name',
        'guard_name',
    ];

    public function __construct(Role $role)
    {
        parent::__construct($role);
    }

    protected function cacheVersionKey(): string
    {
        return 'roles_cache_version';
    }

    public function findByIndexes(
        array $indexes,
        bool $any,
        ?int $limit,
        array $orderBy,
        QueryAcceptedComparatorEnum $comparator = QueryAcceptedComparatorEnum::EQUAL,
        ?array $filters = null,
        ?array $fields = null,
        ?array $relation = null,
        ?array $defaultOrderBy = null,
    ): Collection|LengthAwarePaginator|CursorPaginator|Builder {
        // If limit is -1 or cursor is true, serialization might be large or uncacheable?
        // No, arguments are simple types.
        $argsHash = md5(serialize(func_get_args()));
        $cacheKey = 'roles_query_'.$this->getCacheVersion().'_'.$argsHash;

        return Cache::rememberForever($cacheKey, function () use (
            $indexes, $any, $limit, $orderBy, $comparator, $filters, $fields, $relation, $defaultOrderBy
        ) {
            return parent::findByIndexes(
                $indexes, $any, $limit, $orderBy, $comparator, $filters, $fields, $relation, $defaultOrderBy
            );
        });
    }

    public function create(array $data): Model
    {
        /** @var Role $role */
        $role = parent::create($data);

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        $this->incrementCacheVersion();

        return $role->load('permissions');
    }

    public function update(array $data, mixed $idOrSlug): ?Model
    {
        /** @var Role|null $role */
        $role = parent::update($data, $idOrSlug);

        if (isset($data['permissions']) && $role) {
            $role->syncPermissions($data['permissions']);
            $role->load('permissions');
        }

        if ($role) {
            $this->incrementCacheVersion();
        }

        return $role;
    }

    public function findById($idOrSlug): ?Model
    {
        $cacheKey = 'roles_id_'.$this->getCacheVersion().'_'.$idOrSlug;

        return Cache::rememberForever($cacheKey, function () use ($idOrSlug) {
            $role = parent::findById($idOrSlug);
            if ($role) {
                $role->load('permissions');
            }

            return $role;
        });
    }

    public function delete(mixed $idOrSlug): void
    {
        parent::delete($idOrSlug);
        $this->incrementCacheVersion();
    }
}
