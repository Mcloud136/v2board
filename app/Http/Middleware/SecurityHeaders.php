<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $nonce = base64_encode(random_bytes(16));
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (str_contains((string)$response->headers->get('content-type'), 'text/html')) {
            $csp = "default-src 'self'; "
                . "script-src 'self' 'nonce-{$nonce}'; "
                . "style-src 'self' 'unsafe-inline'; "
                . "img-src 'self' data: https:; "
                . "font-src 'self' data:; "
                . "connect-src 'self' https://api.wxmuma.cn https://origin.wxmuma.cn; "
                . "object-src 'none'; base-uri 'self'; frame-ancestors 'self'; "
                . "upgrade-insecure-requests";
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
