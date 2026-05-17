## 本分支支持的后端
 - [修改版V2bX](https://github.com/wyx2685/V2bX)
 - [v2node](https://github.com/Mcloud136/v2node)

## 项目简介

本项目基于 [xiao佬二改v2board](https://github.com/wyx2685/v2board)，进行了框架升级、依赖更新、代码质量修复等全面改进。


## 总览

| 指标 | 上游 (wyx2685) | 本项目 (Mcloud136) | 变化 |
|------|---------------|-------------------|------|
| Laravel 版本 | 8.x（已停止维护） | **12.59.0** | +4 个大版本 |
| PHP 要求 | ^7.3 \|\| ^8.0 | **^8.2** | 最低版本提升 |
| 独有提交 | - | 35 个 | - |
| 修改文件 | - | 48 个 | - |
| 新增文件 | - | 3 个 | - |
| 删除文件 | - | 1 个 | - |
| 代码变化 | - | +1,364 / -1,773 行 | 净减少 409 行 |

---

## 一、框架升级（影响：全局）

| 项目 | 上游 | 本项目 | 提升 |
|------|------|--------|------|
| Laravel | ^8.0 | **^12.0** | +4 个大版本，安全性大幅提升 |
| PHP | ^7.3 \|\| ^8.0 | **^8.2** | 性能提升 ~30%，安全性 |
| laravel/horizon | ^5.9.6 | **^5.21** | 队列管理改进 |
| laravel/tinker | ^2.5 | **^3.0** | CLI 调试工具升级 |
| stripe/stripe-php | ^v14.9.0 | **^20.0** | 支付 API 最新版 |
| php-curl-class | ^8.6 | **^13.0** | HTTP 客户端升级 |
| rybakit/msgpack | ^0.9.1 | **^0.10.0** | 序列化协议升级 |
| paragonie/sodium_compat | ^1.20 | **^2.0** | 加密库升级 |
| symfony/yaml | ^4.3 | **^7.0** | YAML 解析升级 |
| firebase/php-jwt | ^6.3\|\|^7.0 | **^7.0** | JWT 认证标准化 |
| fideloper/proxy | ^4.4 | **已删除** | 废弃包，改用内置 |
| facade/ignition | ^2.3.6 | **spatie/laravel-ignition ^2.4** | 安全替代 |
| nunomaduro/collision | ^4.3 | **^8.0** | 错误处理升级 |
| phpunit/phpunit | ^9.0 | **^11.0** | 测试框架升级 |

---

## 二、安全修复（17 项）

### 高危修复

| # | 问题 | 风险 | 修复方式 |
|---|------|------|---------|
| 1 | ResetTraffic switch case 3 缺 break | 流量重置逻辑错误 | 添加 `break` |
| 2 | CheckRenewal VIP 折扣失效 | 用户被扣原价 | 修复 total_amount 和折扣计算顺序 |
| 3 | SendEmailJob 异常被吞没 | 邮件丢失不重试 | 添加 `throw $e` 让队列重试 |
| 4 | SendEmailJob 泄露 SMTP 密码 | 安全风险 | 删除 return 中的 config 泄露 |
| 5 | SQL 注入风险（sort 参数） | 管理后台 SQL 注入 | 添加 sort 白名单验证 |
| 6 | 权限提升（is_admin 批量赋值） | 越权风险 | 从 validated() 移除，显式处理 |

### 中危修复

| # | 问题 | 修复方式 |
|---|------|---------|
| 7 | 3 个 Model 缺少 $guarded | 添加 `protected $guarded = ['id']` |
| 8 | CouponService 过早加锁 | lockForUpdate 延迟到 use() 方法 |
| 9 | StatUserJob 冗余查询 | 改用直接 update 替代 first+update |
| 10 | User::all() 全量加载 | 改用 chunk(200) 分批处理 |
| 11 | OrderHandleJob timeout 5 秒 | 增至 30 秒 |
| 12 | CheckCommission 未保存 | 添加缺失的 $order->save() |

### 低危修复

| # | 问题 | 修复方式 |
|---|------|---------|
| 13 | StatServerJob 双重 lockForUpdate | 移除冗余调用 |
| 14 | plan_name O(N*M) 查询 | 改用 keyBy('id') O(N+M) |
| 15 | 计时 /1000 错误 | 移除多余除法 |
| 16 | ini_set memory_limit=-1 | 9 个文件移除 |
| 17 | 中间件代码重复 | 提取 AuthenticatesRole 基类 |

---

## 三、框架适配改造（8 项）

| 改造项 | 上游 | 本项目 | 影响文件数 |
|--------|------|--------|-----------|
| 路由语法 | `'Controller@method'` 字符串 | `[Controller::class, 'method']` | 7 个路由文件，177 条路由 |
| RouteServiceProvider | `$namespace` + `map()` 模式 | `boot()` 模式 | 1 个文件完全重写 |
| HTTP Kernel | CheckForMaintenanceMode | PreventRequestsDuringMaintenance | 1 个文件删除，1 个修改 |
| TrustProxies | Fideloper\Proxy 包 | Illuminate 内置 | 1 个文件重写 |
| MysqlLoggerHandler | `array $record` (Monolog 2) | `LogRecord $record` (Monolog 3) | 1 个文件 |
| TelegramController | 构造函数 abort(401) | 认证移至 webhook() | 1 个文件 |
| DB 操作 | `DB::select(DB::raw())` | `DB::statement()` | 2 个文件 |
| 中间件属性 | `$routeMiddleware` | `$middlewareAliases` | 1 个文件 |

---

## 四、支付模块改进 (EPay)

| 改进项 | 上游 | 本项目 |
|--------|------|--------|
| 配置校验 | 无 | 构造函数检查 url/pid/key |
| 类型声明 | 无 | `declare(strict_types=1)` + 返回类型 |
| 签名逻辑 | 重复代码 | 提取 `buildSign()` 方法 |
| 表单标签 | 英文 | 中文化 |
| 参数 | 冗余 type 参数 | 移除 |
| 回调验证 | 无 | 添加参数存在性检查 |

---

## 五、Clash 规则精简

| 文件 | 上游 | 本项目 | 变化 |
|------|------|--------|------|
| app.clash.yaml | 557 行 | 119 行 | **精简 78%** |
| default.clash.yaml | 719 行 | 大幅精简 | 去除冗余规则 |

---

## 六、环境配置更新

| 项目 | 上游 | 本项目 |
|------|------|--------|
| BROADCAST_DRIVER | ✓ | → BROADCAST_CONNECTION |
| CACHE_DRIVER | ✓ | → CACHE_STORE |
| database/seeds | ✓ | → database/seeders (PSR-4) |
| DatabaseSeeder namespace | 无 | `Database\Seeders` |

---

## 七、文档

| 文件 | 说明 |
|------|------|
| UPGRADE_GUIDE.md | 升级测试和回退指南（345 行） |
| install.md | 宝塔面板安装部署指南 |

---

## 八、init.sh 改进

| 改进项 | 上游 | 本项目 |
|--------|------|--------|
| Webman 适配器 | 安装 joanhey/adapterman | 已移除（Laravel 不需要） |
| PHP 版本检查 | 无 | 检查 PHP >= 8.2 |
| Composer 参数 | `install -vvv` | `--no-dev --optimize-autoloader` |

---

## 量化总结

| 指标 | 数值 |
|------|------|
| 框架版本提升 | 8.x → 12.x（+4 个大版本） |
| 依赖包更新 | 14 个包升级，2 个废弃包移除，1 个替换 |
| 安全修复 | 17 项（6 高危 + 6 中危 + 5 低危） |
| 框架适配改造 | 8 项 |
| 路由现代化 | 177 条路由转换 |
| 代码净减少 | 409 行 |
| 新增文档 | 2 个（升级指南 + 安装指南） |
| Clash 规则精简 | 78% |

## Document
[安装步骤](https://github.com/Mcloud136/v2board/blob/master/install.md)
[更新步骤](https://github.com/Mcloud136/v2board/blob/master/UPGRADE_GUIDE.md)

## How to Feedback
Follow the template in the issue to submit your question correctly, and we will have someone follow up with you.
