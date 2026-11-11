<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use App\Utils\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;

class AdminLoginController extends Controller
{
    private const CSRF_TTL = 600;
    private const CSRF_COOKIE = '__Host-admin_login_nonce';

    public function showLogin(Request $request)
    {
        if ($this->resolveAdmin($request)) {
            return redirect($this->adminPath() . '#/dashboard');
        }
        return $this->renderLogin($request);
    }

    public function login(Request $request)
    {
        $nonce = (string)$request->cookie(self::CSRF_COOKIE, '');
        $token = (string)$request->input('csrf_token', '');
        if (!$this->consumeCsrf($nonce, $token)) {
            return $this->loginResponse($request, '页面已过期，请重新提交', 400, (string)$request->input('email', ''));
        }

        $email = (string)$request->input('email', '');
        $password = (string)$request->input('password', '');
        $code = (string)$request->input('totp_code', '');
        $normalizedEmail = strtolower(trim($email));

        $emailKey = CacheKey::get('PASSWORD_ERROR_LIMIT', $normalizedEmail);
        $ipKey = CacheKey::get('PASSWORD_ERROR_LIMIT', 'ip:' . (string)$request->ip());
        $emailCount = (int)Cache::get($emailKey, 0);
        $ipCount = (int)Cache::get($ipKey, 0);
        if ($emailCount >= 5 || $ipCount >= 20) {
            return $this->loginResponse($request, '登录失败次数过多，请稍后再试', 429, $email);
        }

        $user = $email === '' ? null : User::where('email', $email)->first();
        if (!$user || !Helper::multiPasswordVerify(
            $user->password_algo,
            $user->password_salt,
            $password,
            $user->password
        )) {
            $this->recordFailure($emailKey, $ipKey, $emailCount, $ipCount);
            return $this->loginResponse($request, '账号或密码错误', 401, $email);
        }

        if ($user->banned) {
            return $this->loginResponse($request, '账号已被停用', 403, $email);
        }
        if (!$user->is_admin && !$user->is_staff) {
            $this->recordFailure($emailKey, $ipKey, $emailCount, $ipCount);
            return $this->loginResponse($request, '账号或密码错误', 401, $email);
        }

        $totpEnabled = (int)$user->totp_enabled === 1 && !empty($user->totp_secret);
        if ($totpEnabled) {
            $counter = Totp::verify((string)$user->totp_secret, $code);
            if ($counter === false) {
                $this->recordFailure($emailKey, $ipKey, $emailCount, $ipCount);
                return $this->loginResponse($request, '动态验证码错误', 401, $email, 'totp');
            }
            $affected = User::where('id', $user->id)
                ->where(function ($query) use ($counter) {
                    $query->whereNull('totp_last_used_counter')
                        ->orWhere('totp_last_used_counter', '<', $counter);
                })
                ->update(['totp_last_used_counter' => $counter]);
            if ($affected !== 1) {
                return $this->loginResponse($request, '动态验证码已使用，请等待下一次刷新', 401, $email, 'totp');
            }
        } elseif ((int)config('v2board.admin_totp_enforce', 0) === 1) {
            return $this->loginResponse($request, '请先绑定 TOTP 后再登录', 403, $email);
        }

        Cache::forget($emailKey);
        Cache::forget($ipKey);

        if ($user->password_algo !== null) {
            $user->password = password_hash($password, PASSWORD_DEFAULT);
            $user->password_algo = null;
            $user->password_salt = null;
            $user->save();
        }

        $web = (new AuthService($user))->generateWebAuthData($request, 'admin');
        $cookie = Cookie::make(
            config('v2board.web_session_cookie', 'v2board_sid'),
            $web['sid'],
            (int)ceil($web['ttl'] / 60),
            '/',
            null,
            true,
            true,
            false,
            'lax'
        );
        if ($this->wantsJson($request)) {
            return response()
                ->json(['code' => 0, 'msg' => '', 'redirect' => $this->adminPath() . '#/dashboard'])
                ->withCookie($cookie)
                ->header('Cache-Control', 'no-store, private');
        }
        return redirect($this->adminPath() . '#/dashboard')->withCookie($cookie);
    }

    private function resolveAdmin(Request $request)
    {
        $sessionId = (string)$request->cookie(config('v2board.web_session_cookie', 'v2board_sid'), '');
        if ($sessionId === '') {
            return false;
        }
        $user = AuthService::resolveWebSession($sessionId);
        if (!$user) {
            return false;
        }
        if ((int)($user['is_admin'] ?? 0) !== 1 && (int)($user['is_staff'] ?? 0) !== 1) {
            return false;
        }
        // 用户端签发的会话不具备管理端作用域，仍需从管理端完成密码 + TOTP
        if (($user['web_scope'] ?? 'user') !== 'admin') {
            return false;
        }
        return $user;
    }

    private function adminPath(): string
    {
        return '/' . config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key'))));
    }

    private function recordFailure(string $emailKey, string $ipKey, int $emailCount, int $ipCount): void
    {
        if (!(int)config('v2board.password_limit_enable', 1)) {
            return;
        }
        Cache::put($emailKey, $emailCount + 1, 60 * (int)config('v2board.password_limit_expire', 60));
        Cache::put($ipKey, $ipCount + 1, 3600);
    }

    private function issueNonceAndToken(): array
    {
        $nonce = bin2hex(random_bytes(32));
        $token = bin2hex(random_bytes(32));
        Cache::put($this->csrfCacheKey($nonce), $token, self::CSRF_TTL);

        $cookie = Cookie::make(
            self::CSRF_COOKIE,
            $nonce,
            (int)(self::CSRF_TTL / 60),
            '/',
            null,
            true,
            true,
            false,
            'lax'
        );

        return [$token, $cookie];
    }

    private function renderLogin(Request $request, string $error = '', int $status = 200, string $email = '')
    {
        [$token, $cookie] = $this->issueNonceAndToken();

        return response()
            ->view('admin_login', [
                'error' => $error,
                'email' => $email,
                'csrfToken' => $token,
            ], $status)
            ->withCookie($cookie)
            ->header('Cache-Control', 'no-store, private');
    }

    private function wantsJson(Request $request): bool
    {
        return stripos((string)$request->header('accept', ''), 'application/json') !== false;
    }

    /**
     * 校验语义与 renderLogin 完全一致，仅在客户端声明 Accept: application/json 时改为
     * JSON 响应，并重新签发一次性 CSRF（原 token 已被 consumeCsrf 消费，不重发则无法重试）。
     */
    private function loginResponse(Request $request, string $error, int $status, string $email, string $step = 'password')
    {
        if (!$this->wantsJson($request)) {
            return $this->renderLogin($request, $error, $status, $email);
        }

        [$token, $cookie] = $this->issueNonceAndToken();

        return response()
            ->json([
                'code' => -1,
                'msg' => $error,
                'step' => $step,
                'csrf_token' => $token,
            ], $status)
            ->withCookie($cookie)
            ->header('Cache-Control', 'no-store, private');
    }

    private function consumeCsrf(string $nonce, string $token): bool
    {
        if (strlen($nonce) !== 64 || strlen($token) !== 64) {
            return false;
        }
        $key = $this->csrfCacheKey($nonce);
        $store = Cache::getStore();
        if (method_exists($store, 'lock')) {
            $lock = $store->lock($key . '_lock', 5);
            if (!$lock->get()) {
                return false;
            }
            try {
                return $this->pullCsrf($key, $token);
            } finally {
                $lock->release();
            }
        }
        return $this->pullCsrf($key, $token);
    }

    private function pullCsrf(string $key, string $token): bool
    {
        $expected = Cache::pull($key);
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    private function csrfCacheKey(string $nonce): string
    {
        return 'admin_login_csrf_' . hash('sha256', $nonce);
    }
}
