<?php

namespace Tests\Unit;

use App\Services\AuthService;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * FIX-04：批量会话清理
 * 依赖 phpunit.xml 的 CACHE_STORE=array，不依赖 Redis/MySQL
 */
class AuthServiceBatchTest extends TestCase
{
    public function test_batch_clears_sessions_and_jwt_caches(): void
    {
        $jwt1 = 'jwt-token-user-1';
        $jwt2 = 'jwt-token-user-2';
        Cache::put(CacheKey::get('USER_SESSIONS', 1), ['guid1' => ['auth_data' => $jwt1]], 86400);
        Cache::put(CacheKey::get('USER_SESSIONS', 2), ['guid2' => ['auth_data' => $jwt2]], 86400);
        Cache::put($jwt1, ['id' => 1, 'banned' => 0], 900);
        Cache::put($jwt2, ['id' => 2, 'banned' => 0], 900);

        AuthService::clearUserSessionsBatch([1, 2]);

        $this->assertNull(Cache::get(CacheKey::get('USER_SESSIONS', 1)));
        $this->assertNull(Cache::get(CacheKey::get('USER_SESSIONS', 2)));
        // JWT 快照缓存一并清除，封禁/删除即时生效
        $this->assertNull(Cache::get($jwt1));
        $this->assertNull(Cache::get($jwt2));
    }

    public function test_batch_with_empty_ids_is_noop(): void
    {
        AuthService::clearUserSessionsBatch([]);
        $this->assertTrue(true);
    }

    public function test_single_clear_still_works(): void
    {
        $jwt = 'jwt-token-single';
        Cache::put(CacheKey::get('USER_SESSIONS', 9), ['g' => ['auth_data' => $jwt]], 86400);
        Cache::put($jwt, ['id' => 9], 900);

        AuthService::clearUserSessions(9);

        $this->assertNull(Cache::get(CacheKey::get('USER_SESSIONS', 9)));
        $this->assertNull(Cache::get($jwt));
    }
}
