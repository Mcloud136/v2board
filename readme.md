<img src="https://avatars.githubusercontent.com/u/56885001?s=200&v=4" alt="logo" width="130" height="130" align="right"/>

[![](https://img.shields.io/badge/TgChat-@UnOfficialV2board讨论-blue.svg)](https://t.me/unofficialV2board)

## 本分支支持的后端
 - [修改版V2bX](https://github.com/wyx2685/V2bX)
 - [v2node](https://github.com/Mcloud136/v2node)

## 本项目基于[xiao佬二改v2board](https://github.com/wyx2685/v2board)制作，升级到Laravel11并加入一些修改提升使用体验，Mcloud136/v2board vs wyx2685/v2board 改进对比报告

## 一、框架升级（最大改动）

| 项目 | 上游 (wyx2685) | 改进版本 (Mcloud136) |
|------|---------------|---------------------|
| Laravel | 8.x（已停止维护） | **11.52.0**（支持到 2026.9） |
| PHP 要求 | ^7.3 \|\| ^8.0 | **^8.2** |
| 安全状态 | 存在已知 CVE | 已消除框架漏洞 |

## 二、依赖包更新

| 依赖包 | 上游 | 改进版本 | 说明 |
|--------|------|---------|------|
| fideloper/proxy | ^4.4 | **已删除** | 废弃包，改用 Laravel 内置 |
| facade/ignition | ^2.3.6 | **spatie/laravel-ignition ^2.4** | 安全替代 |
| laravel/horizon | ^5.9.6 | **^5.21** | 支持 Laravel 11 |
| nunomaduro/collision | ^4.3 | **^8.0** | |
| phpunit/phpunit | ^9.0 | **^11.0** | |
| symfony/yaml | ^4.3 | **^6.0 \|\| ^7.0** | |

## 三、代码质量改进

### 1. 路由系统现代化

- 上游：300+ 条路由使用已废弃的字符串语法 `'Controller@method'`
- 改进版本：全部转为 `[Controller::class, 'method']` 标准语法
- RouteServiceProvider 完全重写，移除废弃的 `$namespace` 和 `map()` 模式

### 2. Monolog 3.x 兼容性

- 上游：`MysqlLoggerHandler` 使用旧版 `array $record` 签名
- 改进版本：适配 `LogRecord $record` 新 API

### 3. TelegramController 修复

- 上游：构造函数中 `abort(401)` 导致 `route:list` 失败
- 改进版本：认证检查移至 `webhook()` 方法

### 4. MySQL 兼容性修复

- 上游：`DB::select(DB::raw($sql))` 在 Laravel 11 中报错
- 改进版本：改为 `DB::statement($sql)`

### 5. HTTP Kernel 现代化

- 移除废弃的 `CheckForMaintenanceMode`，改用 `PreventRequestsDuringMaintenance`
- `TrustProxies` 从废弃的 Fideloper 包改为 Illuminate 内置
- `$routeMiddleware` 重命名为 `$middlewareAliases`

## 四、支付模块改进 (EPay)

| 改进项 | 说明 |
|--------|------|
| 配置校验 | 构造函数检查 url/pid/key 是否为空 |
| 类型声明 | 添加 `declare(strict_types=1)` 和返回类型 |
| 签名逻辑 | 提取 `buildSign()` 方法，消除重复代码 |
| 表单标签 | 中文化：`URL` → `易支付接口地址`，`PID` → `商户ID` |
| 参数简化 | 移除 `type` 支付类型参数（冗余配置） |
| 回调验证 | 添加必要参数存在性检查 |

## 五、Clash 规则精简

| 文件 | 上游 | 改进版本 | 变化 |
|------|------|---------|------|
| app.clash.yaml | 557 行 | 119 行 | 精简 78% |
| default.clash.yaml | 719 行 | 大幅精简 | 去除冗余规则 |

## 六、环境配置更新

- `.env.example`：`BROADCAST_DRIVER` → `BROADCAST_CONNECTION`，`CACHE_DRIVER` → `CACHE_STORE`
- `database/seeds/` 重命名为 `database/seeders/`，添加正确的 namespace

## 七、文档

- 新增 `UPGRADE_GUIDE.md`：完整的升级测试和回退指南（345 行）

## 八、代码质量修复（两轮共 17 项）

### 第一轮：零风险 + 低风险修复（9 项）

| 修复 | 文件 | 说明 |
|------|------|------|
| 流量重置逻辑 | `ResetTraffic.php` | switch case 3 缺少 break，导致流量被重置两次 |
| 统计计时错误 | `V2boardStatistics.php` | 耗时显示比实际小 1000 倍 |
| 邮件密码泄露 | `SendEmailJob.php` | return 值包含 SMTP 密码 |
| Model 安全 | `InviteCode/ServerGroup/ServerLog` | 添加 `$guarded` 防止 mass assignment |
| 冗余锁 | `StatServerJob.php` | 移除重复的 `lockForUpdate()` |
| 订单超时 | `OrderHandleJob.php` | timeout 从 5 秒增至 30 秒 |
| 冗余查询 | `StatUserJob.php` | 消除重复的 first() 查询 |
| 查询优化 | 4 个 Controller | plan_name 匹配从 O(N*M) 优化为 O(N+M) |
| 返佣保存 | `CheckCommission.php` | 添加缺失的 `$order->save()` |

### 第二轮：功能修复 + 性能优化（8 项）

| 修复 | 文件 | 说明 |
|------|------|------|
| **VIP 折扣失效** | `CheckRenewal.php` | `total_amount=0` 导致折扣计算为 0，用户被扣原价 |
| **邮件异常吞没** | `SendEmailJob.php` | 发送失败不重试，邮件永久丢失 |
| 邮件阻塞 | `SendEmailJob.php` | 移除 `sleep(2)`，每封邮件节省 2 秒 |
| 内存溢出 | 3 个 Command | `User::all()` 改为 `chunk(200)` 分批处理 |
| 队列阻塞 | `StatUserJob.php` | 手动 `sleep` 重试改为 Laravel `$backoff` 机制 |
| 锁优化 | `CouponService.php` | `lockForUpdate()` 延迟到实际扣减时，减少锁竞争 |
| 中间件重构 | `Admin/User/Staff` | 提取 `AuthenticatesRole` 基类，消除重复代码 |
| 内存限制 | 9 个文件 | 移除 `ini_set('memory_limit', -1)`，改用分批查询 |

## 统计

| 指标 | 数值 |
|------|------|
| 框架升级修改文件数 | 25 |
| 代码质量修复文件数 | 17 |
| 总新增行数 | 1,100+ |
| 总删除行数 | 1,700+ |
| 净减少代码 | 600+ 行 |

## 迁移步骤

### 从原版迁移

```bash
git remote set-url origin https://github.com/Mcloud136/v2board
git checkout master
bash update.sh
```

### 环境要求

| 项目 | 最低版本 |
|------|---------|
| PHP | 8.2+ |
| Composer | 2.x |
| MySQL | 5.5+ |
| Redis | 任意版本 |

### 必需 PHP 扩展

`redis` `fileinfo` `pdo_mysql` `openssl` `curl` `mbstring` `xml` `pcntl`

### 禁用函数检查

`disable_functions` 中**不能**包含：`putenv` `proc_open` `pcntl_alarm` `pcntl_signal`

### 更新后配置

```bash
# 配置缓存驱动为 redis
sed -i 's/^CACHE_DRIVER=.*/CACHE_STORE=redis/' .env
php artisan config:clear
php artisan config:cache
composer dump-autoload
php artisan horizon:terminate
```

最后进入后台重新保存主题：主题配置 → 选择 default 主题 → 主题设置 → 确定保存

## Document
[安装步骤](https://v2board.com)
[更新步骤](https://github.com/Mcloud136/v2board/blob/master/UPGRADE_GUIDE.md)

## Sponsors
Thanks to the open source project license provided by [Jetbrains](https://www.jetbrains.com/)

## Community
🔔Telegram Group: [@unofficialV2board](https://t.me/unofficialV2board)  

## How to Feedback
Follow the template in the issue to submit your question correctly, and we will have someone follow up with you.
