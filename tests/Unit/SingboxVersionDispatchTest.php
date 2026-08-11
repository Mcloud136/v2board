<?php

namespace Tests\Unit;

use App\Http\Controllers\V1\Client\ClientController;
use App\Protocols\Singbox\Singbox;
use App\Protocols\Singbox\SingboxOld;
use PHPUnit\Framework\TestCase;

/**
 * FIX-05：sing-box 版本分流必须使用 version_compare
 * 旧实现的字符串比较会把 1.9.0/1.10.x 误判为 >= 1.12.0
 */
class SingboxVersionDispatchTest extends TestCase
{
    public function test_versions_below_1_12_use_old_generator(): void
    {
        $this->assertSame(SingboxOld::class, ClientController::resolveSingboxClass('1.9.0'));
        $this->assertSame(SingboxOld::class, ClientController::resolveSingboxClass('1.10.9'));
        $this->assertSame(SingboxOld::class, ClientController::resolveSingboxClass('1.11.99'));
    }

    public function test_versions_at_or_above_1_12_use_new_generator(): void
    {
        $this->assertSame(Singbox::class, ClientController::resolveSingboxClass('1.12.0'));
        $this->assertSame(Singbox::class, ClientController::resolveSingboxClass('1.12.1'));
        $this->assertSame(Singbox::class, ClientController::resolveSingboxClass('2.0.0'));
    }

    public function test_missing_version_falls_back_to_old_generator(): void
    {
        $this->assertSame(SingboxOld::class, ClientController::resolveSingboxClass(null));
    }

    public function test_old_string_comparison_would_be_wrong(): void
    {
        // 固化回归证据：字符串比较下该断言为真，正是本次修复的缺陷形态
        $this->assertTrue('1.9.0' >= '1.12.0');
        $this->assertFalse(version_compare('1.9.0', '1.12.0', '>='));
    }
}
