<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;

class AdminCsrf
{
    private const COOKIE = '__Host-admin_csrf';
    private const HEADER = 'X-CSRF-Token';
    private const TTL = 1800;

    public function handle(Request $request, Closure $next)
    {
        $sessionId = (string)$request->cookie(config('v2board.web_session_cookie', 'v2board_sid'), '');
        if ($sessionId === '') {
            return $next($request);
        }
        $user = AuthService::resolveWebSession($sessionId);
        if (!$user
            || ((int)($user['is_admin'] ?? 0) !== 1 && (int)($user['is_staff'] ?? 0) !== 1)
            || ($user['web_scope'] ?? 'user') !== 'admin') {
            return $next($request);
        }

        $key = 'admin_csrf_' . hash('sha256', $sessionId);
        $cached = Cache::get($key);
        $cookieToken = (string)$request->cookie(self::COOKIE, '');

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            if (!is_string($cached) || $cached === '' || $cookieToken !== $cached) {
                $token = bin2hex(random_bytes(32));
                Cache::put($key, $token, self::TTL);
                return $this->withTokenCookie($next($request), $token);
            }
            return $next($request);
        }

        $headerToken = (string)$request->header(self::HEADER, '');
        if (
            !is_string($cached) || $cached === ''
            || $cookieToken === '' || $headerToken === ''
            || !hash_equals($cached, $cookieToken)
            || !hash_equals($cached, $headerToken)
        ) {
            abort(403, 'CSRF token mismatch');
        }
        return $next($request);
    }

    private function withTokenCookie($response, string $token)
    {
        $cookie = Cookie::make(
            self::COOKIE,
            $token,
            (int)(self::TTL / 60),
            '/',
            null,
            true,
            false,
            false,
            'lax'
        );
        if (method_exists($response, 'withCookie')) {
            return $response->withCookie($cookie);
        }
        $response->headers->setCookie($cookie);
        return $response;
    }
}
