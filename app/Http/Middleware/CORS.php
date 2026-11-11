<?php

namespace App\Http\Middleware;

use Closure;

class CORS
{
    public function handle($request, Closure $next)
    {
        // 仅信任 Origin 头；不再以 Referer 充当 origin（非标准行为且扩大反射面）
        $origin = $request->header('origin');

        $allowedOrigin = $this->isAllowedOrigin($origin);

        // 预检请求直接返回
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        if ($allowedOrigin) {
            $response->header('Access-Control-Allow-Origin', $allowedOrigin);
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
        $response->header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS,HEAD');
        $response->header('Access-Control-Allow-Headers', 'Origin,Content-Type,Accept,Authorization,X-Request-With');
        $response->header('Access-Control-Max-Age', 10080);

        return $response;
    }

    private function isAllowedOrigin(?string $origin): ?string
    {
        if (empty($origin)) return null;

        $origin = trim($origin, '/');

        // 从配置读取允许的域名列表
        $allowedOrigins = config('v2board.cors_allowed_origins', []);

        // 默认允许站点自身域名
        $appUrl = config('v2board.app_url');
        if ($appUrl) {
            $appHost = parse_url($appUrl, PHP_URL_HOST);
            $allowedOrigins[] = $appHost;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        if (!$originHost) return null;
        $originHost = strtolower($originHost);

        foreach ($allowedOrigins as $allowed) {
            $allowed = strtolower(trim($allowed, '/'));
            // 通配符 *.example.com 仅匹配单级子域（防止任意层级子域绕过）
            if (strpos($allowed, '*.') === 0) {
                $suffix = substr($allowed, 1); // .example.com
                if (substr($originHost, -strlen($suffix)) === $suffix
                    && substr_count(substr($originHost, 0, -strlen($suffix)), '.') === 0) {
                    return $origin;
                }
            } elseif ($originHost === $allowed || strtolower($origin) === $allowed) {
                return $origin;
            }
        }

        return null;
    }
}
