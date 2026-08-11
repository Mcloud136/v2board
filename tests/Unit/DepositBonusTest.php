<?php

namespace Tests\Unit;

use App\Services\OrderService;
use PHPUnit\Framework\TestCase;

/**
 * FIX-13a：充值档位赠送计算（去重后的公共实现）
 * 断言的是既有上游语义（档位值为百分比数字，直接加到以分为单位的余额），
 * 本测试固化该行为防止重构偏移
 */
class DepositBonusTest extends TestCase
{
    public function test_empty_config_returns_zero(): void
    {
        $this->assertSame(0, OrderService::calcDepositBonus([], 10000));
        $this->assertSame(0, OrderService::calcDepositBonus([null], 10000));
    }

    public function test_tier_boundaries(): void
    {
        // 档位格式 "金额元:赠送百分比"，金额内部转为分比较
        $tiers = ['10:0.1', '50:0.2'];
        $this->assertSame(0, OrderService::calcDepositBonus($tiers, 999));   // 低于最低档
        $this->assertSame(10, OrderService::calcDepositBonus($tiers, 1000)); // 恰好命中 10 元档
        $this->assertSame(20, OrderService::calcDepositBonus($tiers, 5000)); // 命中更高档取 max
        $this->assertSame(20, OrderService::calcDepositBonus($tiers, 99999));
    }

    public function test_single_tier(): void
    {
        $this->assertSame(10, OrderService::calcDepositBonus(['100:0.1'], 10000));
        $this->assertSame(0, OrderService::calcDepositBonus(['100:0.1'], 9999));
    }
}
