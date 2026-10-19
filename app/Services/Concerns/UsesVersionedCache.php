<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Cache;

trait UsesVersionedCache
{
    abstract protected function cacheVersionKey(): string;

    protected function getCacheVersion(): string
    {
        return (string) Cache::rememberForever(
            $this->cacheVersionKey(),
            fn (): int => 1,
        );
    }

    protected function incrementCacheVersion(): void
    {
        $this->getCacheVersion();
        Cache::increment($this->cacheVersionKey());
    }
}
