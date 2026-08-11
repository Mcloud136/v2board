<?php

namespace Tests\Unit;

use App\Utils\CacheKey;
use PHPUnit\Framework\TestCase;

/**
 * FIX-06：缓存键防碰撞
 * 纯逻辑测试，不依赖应用启动与缓存存储
 */
class CacheKeyTest extends TestCase
{
    public function test_similar_emails_do_not_collide(): void
    {
        $a = CacheKey::get('PASSWORD_ERROR_LIMIT', 'a.b@c.com');
        $b = CacheKey::get('PASSWORD_ERROR_LIMIT', 'ab@c.com');
        $c = CacheKey::get('PASSWORD_ERROR_LIMIT', 'a.b@ccom');
        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertNotSame($b, $c);
    }

    public function test_same_value_is_stable(): void
    {
        $this->assertSame(
            CacheKey::get('EMAIL_VERIFY_CODE', 'user@example.com'),
            CacheKey::get('EMAIL_VERIFY_CODE', 'user@example.com')
        );
    }

    public function test_integer_values_pass_through_readable(): void
    {
        // 用户 ID / 节点 ID 等整型值保持可读拼接，USER_SESSIONS 等既有键形态不变
        $this->assertSame('USER_SESSIONS_42', CacheKey::get('USER_SESSIONS', 42));
        $this->assertSame('SERVER_VMESS_LAST_CHECK_AT_7', CacheKey::get('SERVER_VMESS_LAST_CHECK_AT', 7));
    }

    public function test_string_key_is_charset_safe(): void
    {
        $key = CacheKey::get('EMAIL_VERIFY_CODE', 'weird+email@host.com');
        $this->assertMatchesRegularExpression('/^EMAIL_VERIFY_CODE_[a-f0-9]{32}$/', $key);
    }
}
