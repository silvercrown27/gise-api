<?php

namespace App\Http\Middleware;

use App\Support\PublicCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caches anonymous GET responses of the public catalogue endpoints, and tells
 * Cloudflare it may do the same (s-maxage). Requests carrying credentials are
 * never cached or marked public: some endpoints, like a course's detail, show
 * more to a signed-in user.
 *
 * Usage: ->middleware('cache.public:120')  (seconds, default 60)
 */
class CachePublicResponse
{
    public function handle(Request $request, Closure $next, int $ttl = 60): Response
    {
        if (!$request->isMethod('GET') || $request->headers->has('Authorization') || $request->hasCookie('session')) {
            return $next($request);
        }

        $key = $this->key($request);

        if ($hit = Cache::get($key)) {
            return $this->decorate(response($hit, 200, ['Content-Type' => 'application/json']), $ttl)
                ->header('X-Cache', 'HIT');
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            Cache::put($key, $response->getContent(), $ttl);
            $this->decorate($response, $ttl)->headers->set('X-Cache', 'MISS');
        }

        return $response;
    }

    private function key(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return 'public-response:' . PublicCache::version() . ':' . md5($request->path() . '?' . http_build_query($query));
    }

    private function decorate(Response $response, int $ttl): Response
    {
        $response->headers->set('Cache-Control', "public, s-maxage={$ttl}, stale-while-revalidate=" . ($ttl * 5));

        return $response;
    }
}
