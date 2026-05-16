<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;

class AuthenticatesRole
{
    public function handle($request, Closure $next, $role = null)
    {
        $authorization = $request->input('auth_data') ?? $request->header('authorization');
        if (!$authorization) abort(403, '未登录或登陆已过期');

        $user = AuthService::decryptAuthData($authorization);
        if (!$user) abort(403, '未登录或登陆已过期');

        if ($role === 'admin' && !$user['is_admin']) {
            abort(403, '未登录或登陆已过期');
        }
        if ($role === 'staff' && !$user['is_staff']) {
            abort(403, '未登录或登陆已过期');
        }

        $request->merge([
            'user' => $user
        ]);
        return $next($request);
    }
}
