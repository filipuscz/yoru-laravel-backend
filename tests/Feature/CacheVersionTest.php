<?php

namespace Tests\Feature;

use App\Enums\QueryAcceptedComparatorEnum;
use App\Services\PermissionService;
use App\Services\RoleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacheVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_role_cache_version_changes_for_multiple_writes_in_the_same_second(): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00');
        $service = app(RoleService::class);
        $this->primeListCache($service);

        $service->create(['name' => 'same-second-role', 'guard_name' => 'api']);

        $this->assertSame(1, $this->listTotal($service));
    }

    public function test_permission_cache_version_changes_for_multiple_writes_in_the_same_second(): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00');
        $service = app(PermissionService::class);
        $this->primeListCache($service);

        $service->create(['name' => 'same-second-permission', 'guard_name' => 'api']);

        $this->assertSame(1, $this->listTotal($service));
    }

    private function primeListCache(RoleService|PermissionService $service): void
    {
        $this->assertSame(0, $this->listTotal($service));
    }

    private function listTotal(RoleService|PermissionService $service): int
    {
        $results = $service->findByIndexes(
            [],
            false,
            10,
            [],
            QueryAcceptedComparatorEnum::EQUAL,
        );

        return $results->total();
    }
}
