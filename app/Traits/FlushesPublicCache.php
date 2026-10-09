<?php

namespace App\Traits;

use App\Support\PublicCache;

/** Use on models whose data appears in cached public API responses. */
trait FlushesPublicCache
{
    public static function bootFlushesPublicCache(): void
    {
        $flush = static fn () => PublicCache::flush();

        static::saved($flush);
        static::deleted($flush);
        // Only models that use SoftDeletes can be restored.
        if (method_exists(static::class, 'restored')) {
            static::restored($flush);
        }
    }
}
