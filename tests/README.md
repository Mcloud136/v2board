# PHPUnit 本地运行环境说明（FIX-09）

测试套件（PHPUnit 11）运行前置条件：

1. `composer install` 完成（vendor 存在）。
2. 存在 `.env` 或 `.env.testing`，至少包含 `APP_KEY`（`php artisan key:generate`）。
3. 存在 `config/v2board.php`（运行时由管理端保存生成）；本地首次运行可手工创建：
   ```php
   <?php
   return [];
   ```
4. PHP 扩展：`pdo_sqlite`（OrderServicePaidTest / TrafficUpdateCommandTest 使用内存 sqlite 隔离生产库）。
5. Redis/MySQL 不是本地测试必需：
   - Redis 交互通过 `Redis::shouldReceive` mock；
   - 缓存走 `CACHE_STORE=array`（phpunit.xml 已内置）；
   - 需要真实 Redis/MySQL 的端到端场景见 docs/dev-logs 中的服务器验证清单。

运行：

```bash
php vendor/bin/phpunit tests/Unit
```
