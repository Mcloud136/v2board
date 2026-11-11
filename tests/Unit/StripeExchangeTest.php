<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FIX-B7：Stripe 外币渠道汇率失败即中止（禁止默认 1 导致 1:1 少收费）
 */
class StripeExchangeTest extends TestCase
{
    private function invokeExchange(object $driver, string $from, string $to)
    {
        $m = new \ReflectionMethod($driver, 'exchange');
        return $m->invoke($driver, $from, $to);
    }

    private function alipay(): \App\Payments\StripeAlipay
    {
        return new \App\Payments\StripeAlipay([
            'stripe_sk_live' => 'sk_test_x',
            'stripe_webhook_key' => 'whsec_x',
            'currency' => 'USD',
        ]);
    }

    public function test_exchange_returns_null_when_rate_missing(): void
    {
        Http::fake(['*' => Http::response(['rates' => []], 200)]);
        $this->assertNull($this->invokeExchange($this->alipay(), 'CNY', 'USD'));
    }

    public function test_exchange_returns_rate_when_available(): void
    {
        Http::fake(['*' => Http::response(['rates' => ['USD' => 0.14]], 200)]);
        $this->assertSame(0.14, $this->invokeExchange($this->alipay(), 'CNY', 'USD'));
    }

    public function test_exchange_returns_null_on_http_failure(): void
    {
        Http::fake(function () {
            throw new \Exception('connection timeout');
        });
        $this->assertNull($this->invokeExchange($this->alipay(), 'CNY', 'USD'));
    }
}
