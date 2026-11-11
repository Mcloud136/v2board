<?php

namespace App\Services;

use App\Utils\CacheKey;
use App\Utils\Helper;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class AuthService
{
    private const ADMIN_AUTH_TTL = 1800; // 管理端/员工 30 分钟
    private const USER_AUTH_TTL = 86400; // 普通用户 24 小时
    private const JWT_USER_CACHE_TTL = 900;

    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function generateAuthData(Request $request)
    {
        $guid = Helper::guid();
        $now = time();
        $ttl = ($this->user->is_admin || $this->user->is_staff)
            ? self::ADMIN_AUTH_TTL
            : self::USER_AUTH_TTL;
        $authData = JWT::encode([
            'id' => $this->user->id,
            'session' => $guid,
            'iat' => $now,
            'exp' => $now + $ttl,
        ], config('app.key'), 'HS256');
        self::addSession($this->user->id, $guid, [
            'ip' => $request->ip(),
            'login_at' => time(),
            'ua' => $request->userAgent(),
            'auth_key' => self::jwtCacheKey($authData)
        ]);
        return [
            'token' => $this->user->token,
            'is_admin' => $this->user->is_admin,
            'auth_data' => $authData
        ];
    }

    /**
     * JWT 用户缓存键：键名里只放摘要，避免可用令牌以明文形态出现在缓存键中
     */
    private static function jwtCacheKey(string $jwt): string
    {
        return CacheKey::get('AUTH_TOKEN', $jwt);
    }

    /**
     * 会话元数据 -> 缓存键。加固前签发的旧会话仍带明文 auth_data，按其摘要回溯旧键
     */
    private static function sessionAuthKey(array $meta): ?string
    {
        if (isset($meta['auth_key'])) return (string)$meta['auth_key'];
        if (isset($meta['auth_data'])) return self::jwtCacheKey((string)$meta['auth_data']);
        return null;
    }
    public static function decryptAuthData($jwt)
    {
        if (!is_string($jwt) || $jwt === '' || substr_count($jwt, '.') !== 2) {
            return false;
        }

        try {
            if (!Cache::has(self::jwtCacheKey($jwt))) {
                $data = (array)JWT::decode($jwt, new Key(config('app.key'), 'HS256'));
                if (!self::checkSession($data['id'], $data['session'])) return false;
                $user = User::select([
                    'id',
                    'email',
                    'is_admin',
                    'is_staff',
                    'banned'
                ])
                    ->find($data['id']);
                if (!$user) return false;
                $cacheTtl = isset($data['exp'])
                    ? max(1, min(self::JWT_USER_CACHE_TTL, (int)$data['exp'] - time()))
                    : self::JWT_USER_CACHE_TTL;
                Cache::put(self::jwtCacheKey($jwt), $user->toArray(), $cacheTtl);
            }

            $user = Cache::get(self::jwtCacheKey($jwt));
            return is_array($user) && isset($user['id']) ? $user : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function generateWebAuthData(Request $request, string $scope = 'user'): array
    {
        $scope = ($scope === 'admin') ? 'admin' : 'user';
        $ttl = ($this->user->is_admin || $this->user->is_staff)
            ? self::ADMIN_AUTH_TTL
            : self::USER_AUTH_TTL;
        $sid = Helper::guid();
        Cache::put(CacheKey::get('WEB_SESSION', $sid), [
            'user_id' => $this->user->id,
            'created_at' => time(),
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
            'is_admin' => (int)$this->user->is_admin,
            'is_staff' => (int)$this->user->is_staff,
            'scope' => $scope,
        ], $ttl);

        $listKey = CacheKey::get('WEB_SESSIONS', $this->user->id);
        $sessions = (array)Cache::get($listKey, []);
        $sessions[$sid] = time() + $ttl;
        Cache::put($listKey, $sessions, 86400);

        return [
            'data' => [
                'token' => $this->user->token,
                'is_admin' => $this->user->is_admin,
                'auth_data' => $this->generateDecoyAuthData($ttl),
            ],
            'sid' => $sid,
            'ttl' => $ttl,
        ];
    }

    private function generateDecoyAuthData(int $ttl): string
    {
        return JWT::encode([
            'id' => $this->user->id,
            'session' => Helper::guid(),
            'iat' => time(),
            'exp' => time() + $ttl,
        ], config('app.key'), 'HS256');
    }

    public static function resolveWebSession($sessionId)
    {
        if (!is_string($sessionId) || $sessionId === '') return false;
        $data = Cache::get(CacheKey::get('WEB_SESSION', $sessionId));
        if (!$data || empty($data['user_id'])) return false;
        $user = User::select([
            'id',
            'email',
            'is_admin',
            'is_staff',
            'banned'
        ])->find($data['user_id']);
        if (!$user || $user->banned) return false;
        $arr = $user->toArray();
        // 加固前签发的旧会话没有 scope，按最保守的 user 处理
        $arr['web_scope'] = (($data['scope'] ?? null) === 'admin') ? 'admin' : 'user';
        return $arr;
    }

    public static function destroyWebSession($sessionId)
    {
        if (!is_string($sessionId) || $sessionId === '') return;
        $key = CacheKey::get('WEB_SESSION', $sessionId);
        $data = Cache::get($key);
        Cache::forget($key);
        if ($data && !empty($data['user_id'])) {
            $listKey = CacheKey::get('WEB_SESSIONS', $data['user_id']);
            $sessions = (array)Cache::get($listKey, []);
            unset($sessions[$sessionId]);
            Cache::put($listKey, $sessions, 86400);
        }
    }

    public static function resolveRequestUser(Request $request)
    {
        $sessionId = config('v2board.web_session_enabled', false) ? $request->cookie(config('v2board.web_session_cookie', 'v2board_sid')) : null;
        if ($sessionId) {
            $user = self::resolveWebSession($sessionId);
            if ($user) return $user;
        }
        $authorization = $request->input('auth_data') ?? $request->header('authorization');
        if (!$authorization) return false;
        return self::decryptAuthData($authorization);
    }

    private static function checkSession($userId, $session)
    {
        $sessions = (array)Cache::get(CacheKey::get("USER_SESSIONS", $userId)) ?? [];
        if (!in_array($session, array_keys($sessions))) return false;
        return true;
    }

    private static function addSession($userId, $guid, $meta)
    {
        $cacheKey = CacheKey::get("USER_SESSIONS", $userId);
        $sessions = (array)Cache::get($cacheKey, []);
        $sessions[$guid] = $meta;
        if (!Cache::put(
            $cacheKey,
            $sessions,
            86400
        )) return false;
        return true;
    }

    public function getSessions()
    {
        return (array)Cache::get(CacheKey::get("USER_SESSIONS", $this->user->id), []);
    }

    public function removeSession($sessionId)
    {
        $cacheKey = CacheKey::get("USER_SESSIONS", $this->user->id);
        $sessions = (array)Cache::get($cacheKey, []);
        $authKey = self::sessionAuthKey($sessions[$sessionId] ?? []);
        if ($authKey) Cache::forget($authKey);
        unset($sessions[$sessionId]);
        if (!Cache::put(
            $cacheKey,
            $sessions,
            86400
        )) return false;
        return true;
    }

    public function removeAllSession()
    {
        return self::clearUserSessions($this->user->id);
    }

    /**
     * 清除指定用户的所有 session 和 JWT 缓存
     * 可用于批量操作（封禁、删除等）而无需实例化 AuthService
     */
    public static function clearUserSessions(int $userId): bool
    {
        $cacheKey = CacheKey::get("USER_SESSIONS", $userId);
        $sessions = (array)Cache::get($cacheKey, []);
        foreach ($sessions as $meta) {
            $authKey = self::sessionAuthKey((array)$meta);
            if ($authKey) Cache::forget($authKey);
        }
        $webListKey = CacheKey::get('WEB_SESSIONS', $userId);
        foreach ((array)Cache::get($webListKey, []) as $sid => $expires) {
            Cache::forget(CacheKey::get('WEB_SESSION', $sid));
        }
        Cache::forget($webListKey);
        return Cache::forget($cacheKey);
    }

    /**
     * 批量清除多个用户的 session 和 JWT 缓存
     * 相比逐个调用 clearUserSessions：会话读取合并为一次 MGET，
     * 避免大批量封禁/删除时的串行 Redis 往返
     */
    public static function clearUserSessionsBatch(array $userIds): void
    {
        if (empty($userIds)) return;
        $sessionKeys = [];
        foreach ($userIds as $userId) {
            $sessionKeys[] = CacheKey::get("USER_SESSIONS", $userId);
        }
        $authKeys = [];
        foreach (Cache::many($sessionKeys) as $sessions) {
            foreach ((array)$sessions as $meta) {
                $authKey = self::sessionAuthKey((array)$meta);
                if ($authKey) $authKeys[] = $authKey;
            }
        }
        foreach ($authKeys as $authKey) {
            Cache::forget($authKey);
        }
        foreach ($sessionKeys as $sessionKey) {
            Cache::forget($sessionKey);
        }
        foreach ($userIds as $userId) {
            $webListKey = CacheKey::get('WEB_SESSIONS', $userId);
            foreach ((array)Cache::get($webListKey, []) as $sid => $expires) {
                Cache::forget(CacheKey::get('WEB_SESSION', $sid));
            }
            Cache::forget($webListKey);
        }
    }
}
