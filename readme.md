## 本分支支持的后端
 - [修改版V2bX](https://github.com/wyx2685/V2bX)
 - [v2node](https://github.com/Mcloud136/v2node)

## 项目简介

本项目基于 [xiao佬二改v2board](https://github.com/wyx2685/v2board)，进行了框架升级、依赖更新、代码质量修复等全面改进。

## 一、框架升级

| 项目 | 上游 (wyx2685) | 本项目 |
|------|---------------|--------|
| Laravel | 8.x（已停止维护） | **12.59.0** |
| PHP 要求 | ^7.3 \|\| ^8.0 | **^8.2** |
| Monolog | 2.x | **3.x** |
| 安全状态 | 存在已知 CVE | 已消除框架漏洞 |

## 二、依赖包版本对比

| 依赖包 | 上游 | 本项目 | 说明 |
|--------|------|--------|------|
| laravel/framework | ^8.0 | **^12.0** | 最新稳定版 |
| laravel/horizon | ^5.9.6 | **^5.21** | 队列管理 |
| laravel/tinker | ^2.5 | **^3.0** | CLI 调试工具 |
| stripe/stripe-php | ^v14.9.0 | **^20.0** | 支付接口 |
| php-curl-class | ^8.6 | **^13.0** | HTTP 客户端 |
| rybakit/msgpack | ^0.9.1 | **^0.10.0** | 节点通信协议 |
| firebase/php-jwt | ^6.3\|\|^7.0 | **^7.0** | JWT 认证 |
| paragonie/sodium_compat | ^1.20 | **^2.0** | 加密兼容层 |
| symfony/yaml | ^4.3 | **^7.0** | YAML 解析 |
| fideloper/proxy | ^4.4 | **已删除** | 废弃包 |
| facade/ignition | ^2.3.6 | **spatie/laravel-ignition ^2.4** | 安全替代 |
| paragonie/random_compat | ^9.99 | **已删除** | PHP 8.2 不需要 |
| nunomaduro/collision | ^4.3 | **^8.0** | |
| phpunit/phpunit | ^9.0 | **^11.0** | |

## 三、代码质量修复（两轮共 17 项）

### 第一轮：零风险 + 低风险修复

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

### 第二轮：功能修复 + 性能优化

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

## 四、框架适配改造

| 改造项 | 说明 |
|--------|------|
| 路由系统 | 300+ 条路由从字符串语法转为 `[Controller::class, 'method']` |
| RouteServiceProvider | 重写为 Laravel 12 的 `boot()` 模式 |
| HTTP Kernel | `CheckForMaintenanceMode` → `PreventRequestsDuringMaintenance` |
| TrustProxies | 从废弃的 Fideloper 包改为 Illuminate 内置 |
| MysqlLoggerHandler | 适配 Monolog 3.x 的 `LogRecord` API |
| TelegramController | 认证检查从构造函数移至 `webhook()` 方法 |
| DB 操作 | `DB::select(DB::raw())` 改为 `DB::statement()` |
| 中间件属性 | `$routeMiddleware` → `$middlewareAliases` |

## 五、EPay 支付改进

| 改进项 | 说明 |
|--------|------|
| 配置校验 | 构造函数检查 url/pid/key 是否为空 |
| 类型声明 | 添加 `declare(strict_types=1)` 和返回类型 |
| 签名逻辑 | 提取 `buildSign()` 方法，消除重复代码 |
| 表单标签 | 中文化：`URL` → `易支付接口地址`，`PID` → `商户ID` |
| 参数简化 | 移除 `type` 支付类型参数 |
| 回调验证 | 添加必要参数存在性检查 |

## 六、Clash 规则精简

| 文件 | 上游 | 本项目 | 变化 |
|------|------|--------|------|
| app.clash.yaml | 557 行 | 119 行 | 精简 78% |
| default.clash.yaml | 719 行 | 大幅精简 | 去除冗余规则 |

## 七、环境配置更新

- `.env.example`：`BROADCAST_DRIVER` → `BROADCAST_CONNECTION`，`CACHE_DRIVER` → `CACHE_STORE`
- `database/seeds/` 重命名为 `database/seeders/`，添加正确的 namespace

## 统计

| 指标 | 数值 |
|------|------|
| 修改文件数 | 40+ |
| 新增行数 | 1,200+ |
| 删除行数 | 1,800+ |
| 净减少代码 | 600+ 行 |

## Document
[安装步骤](https://github.com/Mcloud136/v2board/blob/master/install.md)
[更新步骤](https://github.com/Mcloud136/v2board/blob/master/UPGRADE_GUIDE.md)

## How to Feedback
Follow the template in the issue to submit your question correctly, and we will have someone follow up with you.
