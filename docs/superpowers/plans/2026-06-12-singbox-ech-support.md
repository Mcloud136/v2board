# SingboxOld ECH 支持实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 为 SingboxOld.php 添加 ECH（Encrypted Client Hello）配置下发支持，参考 Singbox.php 已有实现

**Architecture:** 在 SingboxOld.php 的 buildVless、buildVmess、buildTrojan、buildHysteria 四个方法的 TLS 配置块中插入 ECH 代码块。ECH 仅在 TLS 开启且 `$tlsSettings['ech']` 非空时生效，支持 `cloudflare` 和 `custom` 两种模式。

**Tech Stack:** PHP 8.2+, sing-box ECH outbound config format

---

## 文件结构

| 文件 | 操作 | 职责 |
|------|------|------|
| `app/Protocols/Singbox/SingboxOld.php` | 修改 | 添加 ECH 支持 |
| `tests/Unit/SingboxEchTest.php` | 创建 | ECH 配置生成测试 |

---

### Task 1: buildVless ECH 支持

**Files:**
- Modify: `app/Protocols/Singbox/SingboxOld.php:151-156`
- Test: `tests/Unit/SingboxEchTest.php`

- [ ] **Step 1: 写失败测试**

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class SingboxEchTest extends TestCase
{
    public function test_ech_cloudflare_in_vless(): void
    {
        $server = [
            'type' => 'vless',
            'name' => 'test-vless',
            'host' => 'example.com',
            'port' => 443,
            'tls' => 1,
            'tls_settings' => [
                'server_name' => 'example.com',
                'allow_insecure' => 0,
                'ech' => 'cloudflare',
            ],
            'network' => 'tcp',
        ];

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildVless');
        $result = $method->invoke($class, 'test-uuid', $server);

        $this->assertArrayHasKey('tls', $result);
        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
        $this->assertEquals('cloudflare-ech.com', $result['tls']['ech']['query_server_name']);
    }

    public function test_ech_custom_in_vless(): void
    {
        $server = [
            'type' => 'vless',
            'name' => 'test-vless',
            'host' => 'example.com',
            'port' => 443,
            'tls' => 1,
            'tls_settings' => [
                'server_name' => 'example.com',
                'ech' => 'custom',
                'ech_config' => 'AEX+DQBBqwAgACB8VWmnGRfdZIzHgFfqHr3RhPJ4iXo3gN7DZpPqMBN3dgA...',
            ],
            'network' => 'tcp',
        ];

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildVless');
        $result = $method->invoke($class, 'test-uuid', $server);

        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
        $this->assertIsArray($result['tls']['ech']['config']);
    }

    public function test_no_ech_when_not_configured(): void
    {
        $server = [
            'type' => 'vless',
            'name' => 'test-vless',
            'host' => 'example.com',
            'port' => 443,
            'tls' => 1,
            'tls_settings' => [
                'server_name' => 'example.com',
            ],
            'network' => 'tcp',
        ];

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildVless');
        $result = $method->invoke($class, 'test-uuid', $server);

        $this->assertArrayNotHasKey('ech', $result['tls']);
    }
}
```

- [ ] **Step 2: 运行测试确认失败**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit tests/Unit/SingboxEchTest.php --no-coverage 2>&1`

预期：FAIL（ECH 代码块不存在）

- [ ] **Step 3: 在 buildVless 中插入 ECH 代码**

文件：`app/Protocols/Singbox/SingboxOld.php`

在 `$array['tls'] = $tlsConfig;`（约第 156 行）之前插入：

```php
            if (!empty($tlsSettings['ech'])) {
                if ($tlsSettings['ech'] === 'cloudflare') {
                    $tlsConfig['ech'] = [
                        'enabled' => true,
                        'query_server_name' => 'cloudflare-ech.com'
                    ];
                } elseif ($tlsSettings['ech'] === 'custom' && !empty($tlsSettings['ech_config'])) {
                    $tlsConfig['ech'] = [
                        'enabled' => true,
                        'config' => is_array($tlsSettings['ech_config']) ? $tlsSettings['ech_config'] : [$tlsSettings['ech_config']]
                    ];
                }
            }
```

- [ ] **Step 4: 运行测试**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit tests/Unit/SingboxEchTest.php --no-coverage 2>&1`

预期：3/3 PASS

- [ ] **Step 5: 提交**

```bash
cd C:/Users/XOS/v2board
git add app/Protocols/Singbox/SingboxOld.php tests/Unit/SingboxEchTest.php
git commit -m "feat: add ECH support to buildVless in SingboxOld"
```

---

### Task 2: buildVmess ECH 支持

**Files:**
- Modify: `app/Protocols/Singbox/SingboxOld.php:210-215`

- [ ] **Step 1: 在 buildVmess 中插入 ECH 代码**

在 `$array['tls'] = $tlsConfig;`（约第 215 行）之前插入相同的 ECH 代码块：

```php
            if (!empty($tlsSettings['ech'])) {
                if ($tlsSettings['ech'] === 'cloudflare') {
                    $tlsConfig['ech'] = [
                        'enabled' => true,
                        'query_server_name' => 'cloudflare-ech.com'
                    ];
                } elseif ($tlsSettings['ech'] === 'custom' && !empty($tlsSettings['ech_config'])) {
                    $tlsConfig['ech'] = [
                        'enabled' => true,
                        'config' => is_array($tlsSettings['ech_config']) ? $tlsSettings['ech_config'] : [$tlsSettings['ech_config']]
                    ];
                }
            }
```

- [ ] **Step 2: 运行测试确认**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit tests/Unit/SingboxEchTest.php --no-coverage 2>&1`

预期：仍 PASS（不影响已有测试）

- [ ] **Step 3: 提交**

```bash
cd C:/Users/XOS/v2board
git add app/Protocols/Singbox/SingboxOld.php
git commit -m "feat: add ECH support to buildVmess in SingboxOld"
```

---

### Task 3: buildTrojan ECH 支持

**Files:**
- Modify: `app/Protocols/Singbox/SingboxOld.php:270-280`

- [ ] **Step 1: 在 buildTrojan 中插入 ECH 代码**

在 `$array['tls'] = $tlsConfig;`（约第 280 行）之前插入相同的 ECH 代码块。

- [ ] **Step 2: 运行测试确认**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit tests/Unit/SingboxEchTest.php --no-coverage 2>&1`

预期：仍 PASS

- [ ] **Step 3: 提交**

```bash
cd C:/Users/XOS/v2board
git add app/Protocols/Singbox/SingboxOld.php
git commit -m "feat: add ECH support to buildTrojan in SingboxOld"
```

---

### Task 4: buildHysteria ECH 支持

**Files:**
- Modify: `app/Protocols/Singbox/SingboxOld.php:323-330`

- [ ] **Step 1: 在 buildHysteria 中插入 ECH 代码**

在 `$array['tls'] = $tlsConfig;`（约第 330 行）之前插入相同的 ECH 代码块。

- [ ] **Step 2: 运行测试确认**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit tests/Unit/SingboxEchTest.php --no-coverage 2>&1`

预期：仍 PASS

- [ ] **Step 3: 提交**

```bash
cd C:/Users/XOS/v2board
git add app/Protocols/Singbox/SingboxOld.php
git commit -m "feat: add ECH support to buildHysteria in SingboxOld"
```

---

### Task 5: 补充测试并推送

**Files:**
- Modify: `tests/Unit/SingboxEchTest.php`

- [ ] **Step 1: 添加 Vmess/Trojan/Hysteria ECH 测试**

```php
    public function test_ech_cloudflare_in_vmess(): void
    {
        $server = [
            'type' => 'vmess',
            'name' => 'test-vmess',
            'host' => 'example.com',
            'port' => 443,
            'tls' => 1,
            'tls_settings' => [
                'server_name' => 'example.com',
                'ech' => 'cloudflare',
                'fingerprint' => 'chrome',
            ],
            'network' => 'tcp',
        ];

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildVmess');
        $result = $method->invoke($class, 'test-uuid', $server);

        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
        $this->assertEquals('cloudflare-ech.com', $result['tls']['ech']['query_server_name']);
    }

    public function test_ech_cloudflare_in_trojan(): void
    {
        $server = [
            'type' => 'trojan',
            'name' => 'test-trojan',
            'host' => 'example.com',
            'port' => 443,
            'tls' => 1,
            'tls_settings' => [
                'server_name' => 'example.com',
                'ech' => 'cloudflare',
            ],
            'network' => 'tcp',
        ];

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildTrojan');
        $result = $method->invoke($class, 'test-password', $server);

        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
    }

    public function test_ech_cloudflare_in_hysteria(): void
    {
        $server = [
            'type' => 'hysteria',
            'name' => 'test-hysteria',
            'host' => 'example.com',
            'port' => 443,
            'tls' => 1,
            'tls_settings' => [
                'server_name' => 'example.com',
                'ech' => 'cloudflare',
            ],
            'server_name' => 'example.com',
        ];

        $user = (object)['id' => 1, 'uuid' => 'test-uuid'];
        $class = new \App\Protocols\Singbox\SingboxOld($user, [$server]);
        $method = new \ReflectionMethod($class, 'buildHysteria');
        $result = $method->invoke($class, 'test-password', $server, $user);

        $this->assertArrayHasKey('ech', $result['tls']);
        $this->assertTrue($result['tls']['ech']['enabled']);
    }
```

- [ ] **Step 2: 运行全部测试**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit tests/Unit/SingboxEchTest.php --no-coverage 2>&1`

预期：6/6 PASS

- [ ] **Step 3: 运行全局测试确认无回归**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit --no-coverage 2>&1`

预期：全部 PASS

- [ ] **Step 4: 推送**

```bash
cd C:/Users/XOS/v2board
git push origin master
```

---

## ECH 配置格式参考（sing-box 文档）

```json
{
  "tls": {
    "enabled": true,
    "server_name": "example.com",
    "ech": {
      "enabled": true,
      "query_server_name": "cloudflare-ech.com",
      "config": ["AEX+DQBBqw..."],
      "config_path": "/path/to/ech.config"
    }
  }
}
```

## 实施顺序

| 顺序 | Task | 文件 | 预计时间 |
|------|------|------|----------|
| 1 | buildVless ECH | SingboxOld.php + 测试 | 10 分钟 |
| 2 | buildVmess ECH | SingboxOld.php | 3 分钟 |
| 3 | buildTrojan ECH | SingboxOld.php | 3 分钟 |
| 4 | buildHysteria ECH | SingboxOld.php | 3 分钟 |
| 5 | 补充测试并推送 | SingboxEchTest.php | 5 分钟 |

**总预计：24 分钟**
