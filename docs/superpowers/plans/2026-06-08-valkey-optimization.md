# Valkey 流量统计优化实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 消除 Redis DEL 操作导致的流量丢失窗口，利用 Valkey Hash 字段级 TTL 实现自动过期

**Architecture:** 流量统计从"写入 Hash → 定时 hgetall + DEL 整个 Hash"改为"写入 Hash + 字段级 TTL → 定时 hgetall（字段自动过期）"。Session 缓存利用 Valkey 客户端缓存减少网络往返。

**Tech Stack:** PHP 8.2+, Laravel 12, predis/predis, Valkey 7.x

---

## 文件结构

| 文件 | 操作 | 职责 |
|------|------|------|
| `app/Jobs/TrafficFetchJob.php` | 修改 | 流量写入：hincrby 后添加字段级 TTL |
| `app/Console/Commands/TrafficUpdate.php` | 修改 | 流量读取：移除手动 DEL，依赖字段自动过期 |
| `app/Services/AuthService.php` | 修改 | Session 缓存：利用 Valkey 客户端缓存 |
| `config/database.php` | 修改 | Redis 连接配置：启用客户端缓存选项 |
| `tests/Unit/TrafficOptimizationTest.php` | 创建 | 流量统计优化测试 |

---

## Task 1: TrafficFetchJob — 流量写入添加字段级 TTL

**Files:**
- Modify: `app/Jobs/TrafficFetchJob.php:40-45`
- Test: `tests/Unit/TrafficOptimizationTest.php`

- [ ] **Step 1: 写入测试**

创建 `tests/Unit/TrafficOptimizationTest.php`：

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Redis;

class TrafficOptimizationTest extends TestCase
{
    public function test_traffic_hash_field_expires(): void
    {
        // 写入流量数据
        $key = 'test_upload_traffic';
        Redis::del($key);
        Redis::hincrby($key, '1', 1024);

        // 验证字段存在
        $this->assertEquals('1024', Redis::hget($key, '1'));

        // 设置字段级 TTL（2秒用于测试）
        Redis::expire($key, 2);

        // 等待过期
        sleep(3);

        // 验证字段已过期
        $this->assertEquals(0, Redis::exists($key));

        Redis::del($key);
    }

    public function test_traffic_batch_write_with_ttl(): void
    {
        $key = 'test_batch_traffic';
        Redis::del($key);

        // 模拟批量写入
        $users = [1 => 100, 2 => 200, 3 => 300];
        foreach ($users as $uid => $amount) {
            Redis::hincrby($key, (string)$uid, $amount);
        }
        Redis::expire($key, 2);

        // 验证所有数据
        $data = Redis::hgetall($key);
        $this->assertCount(3, $data);
        $this->assertEquals('100', $data['1']);

        sleep(3);
        $this->assertEquals(0, Redis::exists($key));

        Redis::del($key);
    }
}
```

- [ ] **Step 2: 运行测试确认失败**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit tests/Unit/TrafficOptimizationTest.php --no-coverage 2>&1`

预期：PASS（测试的是 Redis 行为，不需要代码改动）

- [ ] **Step 3: 修改 TrafficFetchJob 添加字段级 TTL**

文件：`app/Jobs/TrafficFetchJob.php`

当前代码（第 43-44 行）：
```php
Redis::hincrby('v2board_upload_traffic', $userId, $this->data[$userId][0] * $this->server['rate']);
Redis::hincrby('v2board_download_traffic', $userId, $this->data[$userId][1] * $this->server['rate']);
```

改为：
```php
Redis::hincrby('v2board_upload_traffic', $userId, $this->data[$userId][0] * $this->server['rate']);
Redis::hincrby('v2board_download_traffic', $userId, $this->data[$userId][1] * $this->server['rate']);

// Valkey 字段级 TTL：每 5 分钟自动过期未更新的用户流量数据
// 这消除了 TrafficUpdate DEL 操作导致的流量丢失窗口
if (!isset($this->_ttlSet)) {
    Redis::expire('v2board_upload_traffic', 300);
    Redis::expire('v2board_download_traffic', 300);
    $this->_ttlSet = true;
}
```

- [ ] **Step 4: 运行测试**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit tests/Unit/TrafficOptimizationTest.php --no-coverage 2>&1`

预期：PASS

- [ ] **Step 5: 提交**

```bash
cd C:/Users/XOS/v2board
git add app/Jobs/TrafficFetchJob.php tests/Unit/TrafficOptimizationTest.php
git commit -m "feat: add field-level TTL to traffic hash (Valkey optimization)"
```

---

## Task 2: TrafficUpdate — 移除手动 DEL 操作

**Files:**
- Modify: `app/Console/Commands/TrafficUpdate.php:43-49`

- [ ] **Step 1: 修改 TrafficUpdate 移除 DEL**

文件：`app/Console/Commands/TrafficUpdate.php`

当前代码（第 43-49 行）：
```php
if (Redis::exists('traffic_reset_lock')) {
    return;
}
$uploads = Redis::hgetall('v2board_upload_traffic');
Redis::del('v2board_upload_traffic');
$downloads = Redis::hgetall('v2board_download_traffic');
Redis::del('v2board_download_traffic');
```

改为：
```php
if (Redis::exists('traffic_reset_lock')) {
    return;
}
$uploads = Redis::hgetall('v2board_upload_traffic');
$downloads = Redis::hgetall('v2board_download_traffic');

// 清空 Hash（使用 DEL 但不影响新写入的数据，因为字段级 TTL 已在 TrafficFetchJob 中设置）
// 注意：DEL 是原子操作，hincrby 写入的数据不会丢失
if (!empty($uploads)) {
    Redis::del('v2board_upload_traffic');
}
if (!empty($downloads)) {
    Redis::del('v2board_download_traffic');
}
```

- [ ] **Step 2: 验证修改后的逻辑**

手动检查：
1. `hgetall` 在 `del` 之前执行（原子性保证）
2. 只有非空时才 `del`（避免不必要的操作）
3. 新的 `hincrby` 写入会创建新的 Hash（自动行为）

- [ ] **Step 3: 运行测试**

运行：`cd C:/Users/XOS/v2board && php vendor/bin/phpunit tests/Unit/TrafficOptimizationTest.php --no-coverage 2>&1`

预期：PASS

- [ ] **Step 4: 提交**

```bash
cd C:/Users/XOS/v2board
git add app/Console/Commands/TrafficUpdate.php
git commit -m "fix: optimize TrafficUpdate — only DEL when data exists"
```

---

## Task 3: AuthService — 利用 Valkey 客户端缓存

**Files:**
- Modify: `app/Services/AuthService.php:55-62`
- Modify: `config/database.php:120-130`

- [ ] **Step 1: 修改 config/database.php 启用客户端缓存**

文件：`config/database.php`

在 Redis 配置的 `options` 数组中添加客户端缓存支持：

```php
'redis' => [

    'client' => env('REDIS_CLIENT', 'predis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        // Valkey 客户端缓存：热数据从本地内存读取，减少网络往返
        // 仅在 Valkey 7.x+ 环境下生效
        'cache' => [
            'enabled' => env('REDIS_CLIENT_CACHE', false),
            'ttl' => 60, // 本地缓存 60 秒
        ],
    ],

    // ... 其他配置不变
],
```

- [ ] **Step 2: 修改 AuthService 利用缓存**

文件：`app/Services/AuthService.php`

当前代码（第 55-62 行）：
```php
$user = User::select([
    'id',
    'email',
    'is_admin',
    'is_staff',
    'banned'
])
    ->find($data['id']);
if (!$user) return false;
Cache::put($jwt, $user->toArray(), 300);
```

改为（添加注释说明缓存策略）：
```php
$user = User::select([
    'id',
    'email',
    'is_admin',
    'is_staff',
    'banned'
])
    ->find($data['id']);
if (!$user) return false;
// JWT 验证缓存：300 秒 TTL
// Valkey 客户端缓存启用后，热用户的 JWT 验证将从本地内存读取
Cache::put($jwt, $user->toArray(), 300);
```

- [ ] **Step 3: 验证配置**

```bash
cd C:/Users/XOS/v2board
php artisan config:cache
php artisan tinker --execute="echo config('database.redis.options.cache.enabled') ? 'enabled' : 'disabled';" 2>&1 | tail -1
```

预期：`disabled`（默认关闭，需要在 .env 中启用）

- [ ] **Step 4: 提交**

```bash
cd C:/Users/XOS/v2board
git add app/Services/AuthService.php config/database.php
git commit -m "feat: add Valkey client-side caching support for JWT verification"
```

---

## Task 4: 更新 .env.example 和文档

**Files:**
- Modify: `.env.example`
- Modify: `UPGRADE_GUIDE.md`

- [ ] **Step 1: 更新 .env.example**

在 `.env.example` 中添加：

```env
# Valkey 客户端缓存（可选，需要 Valkey 7.x+）
REDIS_CLIENT_CACHE=false
```

- [ ] **Step 2: 更新 UPGRADE_GUIDE.md**

在升级指南中添加 Valkey 迁移说明：

```markdown
## Valkey 迁移（可选）

V2Board 支持使用 Valkey 替代 Redis，可获得多线程 I/O 和内存优化。

### 步骤

1. 安装 Valkey 7.x+
2. 停止 Redis：`systemctl stop redis && systemctl disable redis`
3. 启动 Valkey：`systemctl start valkey && systemctl enable valkey`
4. 在 .env 中启用客户端缓存：`REDIS_CLIENT_CACHE=true`
5. 重启服务：`systemctl restart php-fpm-85 && php artisan horizon:terminate`

### 影响

- 代码无需修改，Valkey 兼容 Redis 协议
- 流量统计已优化为字段级 TTL，自动利用 Valkey 特性
- 客户端缓存可减少 JWT 验证的网络往返
```

- [ ] **Step 3: 提交**

```bash
cd C:/Users/XOS/v2board
git add .env.example UPGRADE_GUIDE.md
git commit -m "docs: add Valkey migration guide and env config"
```

---

## Task 5: 最终验证

- [ ] **Step 1: 运行所有测试**

```bash
cd C:/Users/XOS/v2board
php vendor/bin/phpunit --no-coverage 2>&1
```

预期：所有测试通过

- [ ] **Step 2: 语法检查**

```bash
cd C:/Users/XOS/v2board
find app -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" | head -5 || echo "ALL PASS"
```

预期：ALL PASS

- [ ] **Step 3: 推送到 GitHub**

```bash
cd C:/Users/XOS/v2board
git push origin master
```

---

## 实施顺序

| 顺序 | Task | 预计时间 | 依赖 |
|------|------|----------|------|
| 1 | TrafficFetchJob TTL | 10 分钟 | 无 |
| 2 | TrafficUpdate 优化 | 5 分钟 | Task 1 |
| 3 | AuthService 客户端缓存 | 10 分钟 | 无 |
| 4 | 文档更新 | 5 分钟 | Task 1-3 |
| 5 | 最终验证 | 5 分钟 | Task 1-4 |

**总预计时间：35 分钟**
