<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     * 由 config('app.trusted_proxies')（env TRUSTED_PROXIES）驱动，默认空（不信任任何代理）。
     * 注意：config:cache 后 env() 在配置文件外不可用，故经 config 读取。
     *
     * @var array|string
     */
    protected $proxies;

    public function __construct()
    {
        $proxies = config('app.trusted_proxies');
        if ($proxies === '*') {
            $this->proxies = '*';
        } elseif (is_string($proxies) && trim($proxies) !== '') {
            $this->proxies = array_filter(array_map('trim', explode(',', $proxies)));
        }
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR |
                         Request::HEADER_X_FORWARDED_HOST |
                         Request::HEADER_X_FORWARDED_PORT |
                         Request::HEADER_X_FORWARDED_PROTO |
                         Request::HEADER_X_FORWARDED_AWS_ELB;
}
