<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Version stamp for cached public API responses (see CachePublicResponse).
 * Any change to public catalogue data bumps it, which orphans every cached
 * response at once - no per-key invalidation, and it works on any cache store.
 */
class PublicCache
{
    private const KEY = 'public-cache-version';

    public static function version(): int
    {
        return (int) Cache::get(self::KEY, 1);
    }

    public static function flush(): void
    {
        // Cache::add seeds the counter when it is missing, increment then bumps it.
        Cache::add(self::KEY, 1, now()->addYear());
        Cache::increment(self::KEY);
    }
}
