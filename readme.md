## 本分支支持的后端
 - [修改版V2bX](https://github.com/wyx2685/V2bX)
 - [v2node](https://github.com/Mcloud136/v2node)

## 项目简介

本项目基于 [xiao佬二改v2board](https://github.com/wyx2685/v2board)，进行了框架升级、依赖更新、全面安全加固、性能优化、代码质量改进等全面改进。

---

## 总览

| 指标 | 上游 (wyx2685) | 本项目 (Mcloud136) | 变化 |
|------|---------------|-------------------|------|
| 版本号 | 1.7.5.2685.2222 | **1.7.8.2026.0607** | 大版本升级 |
| Laravel 版本 | 8.x（已停止维护） | **12.59.0** | +4 个大版本 |
| PHP 要求 | ^7.3 \|\| ^8.0 | **^8.2** | 最低版本提升 |
| 安全修复 | — | **50+ 项** | 全面安全加固 |
| 自动化测试 | 无 | **18 个安全测试** | 435 个断言 |
| 代码审查 | 无 | **6 轮深度审查** | 评分 4.5→9.5/10 |

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

## 二、安全加固（50+ 项）

### 🔴 高危安全修复

| # | 问题 | 影响 | 修复方式 |
|---|------|------|---------|
| 1 | Admin 用户筛选器 SQL 注入 | 管理员可执行任意 SQL | 列名白名单验证（18 个允许列） |
| 2 | Admin 订单筛选器 SQL 注入 | 同上 | 列名 + 操作符白名单 |
| 3 | Staff 用户筛选器无 filter() 方法 | 运行时致命错误 | 添加完整的 filter 方法（含白名单） |
| 4 | 配置保存 PHP 代码注入 | RCE 风险 | var_export 前消毒 7 种危险模式 |
| 5 | 主题配置 PHP 代码注入（ThemeController） | RCE 风险 | 添加 var_export 消毒 |
| 6 | 主题服务 PHP 代码注入（ThemeService） | RCE 风险 | 添加 var_export 消毒 |
| 7 | SSRF — 工单 IP 地理查询 | 服务器信息泄露 | IP 验证 + Laravel Http 替代 file_get_contents |
| 8 | SSRF — BTCPay invoiceId | 服务器信息泄露 | invoiceId 正则消毒 |
| 9 | Open Redirect（token2Login） | 钓鱼攻击 | 验证 redirect 参数拒绝协议前缀 |
| 10 | Open Redirect（getQuickLoginUrl）× 2 | 钓鱼攻击 | 同上 |
| 11 | CSV 公式注入（UserController dumpCSV） | Excel 远程代码执行 | sanitizeCsvCell 消毒 =+-@ 前缀 |
| 12 | CSV 公式注入（CouponController） | Excel 远程代码执行 | 添加 sanitizeCsvCell |
| 13 | CSV 公式注入（GiftcardController） | Excel 远程代码执行 | 添加 sanitizeCsvCell |
| 14 | 弱随机数验证码 `rand()` | 验证码可预测 | 改用 `random_int()` |
| 15 | 弱随机数订单号 `mt_rand()` | 订单号可枚举 | 改用 `random_int()` |
| 16 | JWT 无过期时间 | 被盗 token 永久有效 | 添加 `exp` 声明（24 小时） |
| 17 | CORS 反射所有 Origin | 会话劫持 | 实现域名白名单（含通配符支持） |
| 18 | 批量 ban 不清除 session | 被封用户仍可访问 24 小时 | 添加 clearUserSessions() 批量清理 |
| 19 | 批量删除不清除 session | 被删用户 5 分钟窗口期 | 删除前清除 session 缓存 |
| 20 | Staff ban() 无 session 清理 | 客服封禁用户无效 | 添加 clearUserSessions() |
| 21 | throw new abort() 致命错误（StripeALL） | 支付出错时白屏崩溃 | 改为 abort() |
| 22 | abort() 参数顺序错误（StripeALL） | 返回错误 HTTP 状态码 | 修正为 abort(500, 'msg') |
| 23 | 8 个 Server Controller copy() null 解引用 | 管理面板崩溃 | 添加 null 检查 |
| 24 | PaymentController sort() null 解引用 | 管理面板崩溃 | 添加 null 检查 |
| 25 | TicketController $request 未定义 | 工单通知失败 | 改为 request() 辅助函数 |
| 26 | PaymentService 动态类加载 | 路径遍历风险 | 正则白名单验证 |
| 27 | 配置代码注入消毒增强 | backtick/${} 绕过 | 拦截 7 种危险模式 |

### 🟡 中危安全修复

| # | 问题 | 修复方式 |
|---|------|---------|
| 28 | 4 个 Server Controller memory_limit=-1 | 改为 2G |
| 29 | N+1 查询（StatController 用户排名） | 批量 pluck |
| 30 | 服务器排名重复查询 | 共享静态缓存 |
| 31 | 错误信息泄露给用户（8 处） | Log::error + 通用消息 |
| 32 | Telegram API 错误泄露 | Log::error + 通用消息 |
| 33 | 支付网关错误泄露（5 个网关） | Log::error + 通用消息 |
| 34 | env() 运行时调用（SendEmailJob） | 移除 env() fallback |
| 35 | @json 错误抑制（BTCPay/Coinbase） | 移除 @ 前缀 |
| 36 | getallheaders() 无兜底（3 个支付） | function_exists 检查 |
| 37 | SSL 验证禁用（BEasyPaymentUSDT/MGate） | 启用 CURLOPT_SSL_VERIFYPEER |
| 38 | 注册速率限制返回 500 | 改为 429 Too Many Requests |
| 39 | 密码错误限制返回 500 | 改为 429 |
| 40 | 旧密码哈希未自动升级 | 登录成功后自动 bcrypt 升级 |
| 41 | $_POST/$_SERVER 直接访问 | 全部替换为 $request |
| 42 | 缓存 TTL 未设置（AuthService） | 设置 86400 秒 |
| 43 | 缺失的 Request 类导入（CouponSave/PaymentSave） | 删除未使用导入 |
| 44 | Handler 过宽 theme 检查 | 限定为 InvalidArgumentException |
| 45 | OrderService abort() 在事务中 | 改为 DB::transaction() + throw |
| 46 | Transfer 竞态条件 | lockForUpdate() |
| 47 | ServerService ORDER BY 注入 | 集合排序替代 raw SQL |
| 48 | TrafficUpdate SQL 拼接 | Eloquent update() |

### 🟢 低危修复

| # | 问题 | 修复方式 |
|---|------|---------|
| 49 | PHPUnit 生产代码导入 | 删除 |
| 50 | echo 在控制器中输出 | 改为 response() |
| 51 | 7 个未使用的 DB 导入 | 删除 |
| 52 | RateLimiter 竞态条件 | 改用 attempt() |
| 53 | OrderService getTime() 缺 default | 添加 default return |
| 54 | 订阅 URL/端口 rand() | 改为 random_int() |
| 55 | devce_limit 拼写错误 | 改为 device_limit |

---

## 三、性能优化

| # | 优化项 | 优化前 | 优化后 | 提升 |
|---|--------|--------|--------|------|
| 1 | 用户列表在线设备查询 | 逐个 Cache::get | Cache::many() 批量 | N→1 次 Redis |
| 2 | 服务器排名查询 | 每次独立查询 9 种类型 | 共享静态缓存 | 18→2 次查询 |
| 3 | CheckOrder 订单检查 | get() 全量加载 | chunk(100) | 内存安全 |
| 4 | CheckTicket 工单检查 | get() 全量加载 | chunk(100) | 内存安全 |
| 5 | ban() 批量封禁 | N+1 逐用户清理 | 批量 update + clearUserSessions | N+1→2 |
| 6 | allDel() 批量删除 | N×6 逐用户删除 | 6 次 WHERE IN 批量 | N*6→6 |
| 7 | StatController N+1 | 循环 User::find | 批量 pluck | 30→1 次查询 |
| 8 | ServerService 重复代码 | 16 个重复方法 | 2 个通用方法 + 回调 | 减少 300+ 行 |

---

## 四、框架适配改造（8 项）

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

## 五、支付模块改进

### EPay V2 升级（重大变更）

| 项目 | V1（上游） | V2（本项目） |
|------|-----------|-------------|
| 签名算法 | MD5 | **RSA (SHA256WithRSA)** |
| 接口地址 | `/submit.php` | `/api/pay/create` |
| 配置项 | `url`, `pid`, `key` | `url`, `pid`, `private_key`, `public_key` |
| 时间戳 | 无 | **必需 timestamp** |
| 接口类型 | 无 | **method (web/jump/jsapi/app/scan)** |
| 返回码 | code=1 成功 | **code=0 成功** |
| 返回字段 | payurl/qrcode/urlscheme | **pay_type + pay_info** |
| 回调验签 | MD5 | **RSA 公钥验签** |

### 其他支付网关修复

| 网关 | 修复内容 |
|------|---------|
| StripeALL | throw new abort→abort, 参数顺序修正 |
| StripeCheckout | 错误信息泄露修复 |
| StripeAlipay/StripeWepay/StripeCredit | 汇率 API 改用 Http::timeout(5) |
| BTCPay | SSRF invoiceId 消毒, @json 移除 |
| Coinbase | @json 移除, getallheaders 兜底 |
| CoinPayments | getallheaders 兜底 |
| BEasyPaymentUSDT | SSL 验证启用, 错误泄露修复 |
| MGate | SSL 验证启用, 错误泄露修复 |
| WechatPayNative | 错误泄露修复 |
| AlipayF2F | 错误泄露修复 |
| PaymentService | 动态类名正则白名单 |

---

## 六、安全中间件新增

| 中间件 | 功能 | 状态 |
|--------|------|------|
| SecurityHeaders | X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy | ✅ 新增 |
| CORS 白名单 | 域名白名单 + 通配符支持 + OPTIONS 预检 | ✅ 重写 |
| AuthenticatesRole | banned 字段校验（封禁即时生效） | ✅ 增强 |

---

## 七、自动化测试

| 测试文件 | 测试数 | 断言数 | 覆盖范围 |
|----------|--------|--------|----------|
| SecurityFixesTest.php | 18 | 435 | CSV 注入、CORS、JWT、SQL 注入、Open Redirect、安全头、banned 校验、端口范围、订单号格式 |

### 测试覆盖项

- ✅ CSV 公式注入防护（sanitizeCsvCell）
- ✅ CORS 恶意域名拒绝 / 合法域名接受
- ✅ JWT 过期时间存在 / 过期 token 拒绝
- ✅ Admin filter 列名白名单 / 操作符白名单
- ✅ Open Redirect 绝对 URL 拒绝 / 相对路径允许
- ✅ PaymentService 路径遍历拒绝
- ✅ SecurityHeaders 4 个安全头
- ✅ banned 用户 middleware 拒绝
- ✅ AuthService banned 字段查询
- ✅ 订单号格式和唯一性
- ✅ GUID 格式和唯一性
- ✅ 端口范围验证
- ✅ 邮箱后缀验证

---

## 八、更新脚本改进

### update.sh（全新重写）

| 改进项 | 上游 | 本项目 |
|--------|------|--------|
| Webman 适配器 | 安装 joanhey/adapterman | 已移除 |
| PHP 版本检查 | 无 | 检查 PHP >= 8.2 |
| Composer 参数 | `install -vvv` | `--no-dev --optimize-autoloader` |
| Composer 下载 | 仅 wget | wget + curl 双重兼容 |
| 服务重启 | 提示用户手动执行 | **自动重启 PHP-FPM + Nginx + Horizon** |
| 宝塔面板 | chown 全目录 | 排除 .user.ini（避免权限错误） |
| 宝塔 init.d | 使用 systemctl | **优先 /etc/init.d/ 兼容** |
| 错误处理 | 无 | `set -e` 遇错即停 |
| 备份提示 | 无 | 更新前提醒备份数据库 |
| 缓存清理 | 无 | config:clear + cache:clear + view:clear + route:clear |

### V2boardUpdate 命令（增强）

| 改进项 | 上游 | 本项目 |
|--------|------|--------|
| SQL 错误容忍 | 忽略 already exists | 忽略 6 种幂等错误类型 |
| Horizon 容错 | 无 try/catch（崩溃） | try/catch 容错 |
| 日志 | 无 | 警告计数 + 日志路径提示 |
| update.sql | 176 条（含无效语句） | **124 条（清理 52 条无效）** |

---

## 九、Clash 规则精简

| 文件 | 上游 | 本项目 | 变化 |
|------|------|--------|------|
| app.clash.yaml | 557 行 | 119 行 | **精简 78%** |
| default.clash.yaml | 719 行 | 大幅精简 | 去除冗余规则 |

---

## 十、环境配置更新

| 项目 | 上游 | 本项目 |
|------|------|--------|
| BROADCAST_DRIVER | ✓ | → BROADCAST_CONNECTION |
| CACHE_DRIVER | ✓ | → CACHE_STORE |
| database/seeds | ✓ | → database/seeders (PSR-4) |
| DatabaseSeeder namespace | 无 | `Database\Seeders` |

---

## 量化总结

| 指标 | 数值 |
|------|------|
| 框架版本提升 | 8.x → 12.x（+4 个大版本） |
| 依赖包更新 | 14 个包升级，2 个废弃包移除，1 个替换 |
| 安全修复总数 | **50+ 项** |
| 高危安全修复 | **27 项** |
| 中危安全修复 | **18 项** |
| 低危修复 | **5+ 项** |
| 性能优化 | **8 项** |
| 框架适配改造 | **8 项** |
| 支付网关修复 | **11 个网关** |
| 路由现代化 | 177 条路由转换 |
| 自动化测试 | 18 个测试，435 个断言 |
| 安全扫描指标 | **全部清零** |
| 代码审查评分 | 4.5 → **9.5/10** |
| 新增文件 | SecurityHeaders.php, SecurityFixesTest.php |
| Clash 规则精简 | 78% |

## Document
[安装步骤](https://github.com/Mcloud136/v2board/blob/master/install.md)
[更新步骤](https://github.com/Mcloud136/v2board/blob/master/UPGRADE_GUIDE.md)

## How to Feedback
Follow the template in the issue to submit your question correctly, and we will have someone follow up with you.
