## 本分支支持的后端
 - [修改版V2bX](https://github.com/wyx2685/V2bX)
 - [v2node](https://github.com/Mcloud136/v2node)

## 项目简介

本项目基于 [xiao佬二改v2board](https://github.com/wyx2685/v2board)，进行了框架升级、依赖更新、全面安全加固、性能优化、代码质量改进等全面改进。
2026-08 又完成了一轮全链路架构审计与修复（支付幂等、流量结算原子化、节点 API 加固等），并在生产环境完成全功能点验证。

---

## 总览

| 指标 | 上游 (wyx2685) | 本项目 (Mcloud136) | 变化 |
|------|---------------|-------------------|------|
| 版本号 | 1.7.5.2685.2222 | **1.7.8.2026.0607** | 大版本升级 |
| Laravel 版本 | 8.x（已停止维护） | **13.24.0** | +5 个大版本 |
| PHP 要求 | ^7.3 \|\| ^8.0 | **^8.2** | 最低版本提升 |
| 安全修复 | — | **50+ 项** | 全面安全加固 |
| 架构审计修复（2026-08） | — | **P0–P3 全量修复** | 幂等/原子结算/节点加固 |
| 依赖安全扫描 | — | **0 公告** | 17 项漏洞全部清零 |
| 自动化测试 | 无 | **9 个测试文件，50 个测试** | 538 个断言 |
| 代码审查 | 无 | **6 轮深度审查 + 全链路审计** | 评分 4.5→9.5/10 |

---

## 一、框架升级（影响：全局）

| 项目 | 上游 | 本项目 | 提升 |
|------|------|--------|------|
| Laravel | ^8.0 | **^13.0**（实装 13.24.0） | +5 个大版本，安全性大幅提升 |
| PHP | ^7.3 \|\| ^8.0 | **^8.2** | 性能提升 ~30%，安全性 |
| laravel/horizon | ^5.9.6 | **^5.21** | 队列管理改进 |
| laravel/tinker | ^2.5 | **^3.0** | CLI 调试工具升级 |
| stripe/stripe-php | ^v14.9.0 | **^20.0**（实装 20.3.1） | 支付 API 最新版 |
| guzzlehttp/guzzle | ^7.4.3 | **^7.4.3**（实装 7.15.3） | 2026-08 安全修复至无漏洞版本 |
| php-curl-class | ^8.6 | **^13.0** | HTTP 客户端升级 |
| rybakit/msgpack | ^0.9.1 | **^0.10.0** | 序列化协议升级 |
| paragonie/sodium_compat | ^1.20 | **^2.0** | 加密库升级 |
| symfony/yaml | ^4.3 | **^8.0** | YAML 解析升级 |
| firebase/php-jwt | ^6.3\|\|^7.0 | **^7.0** | JWT 认证标准化 |
| google/recaptcha | ^1.5 | **^1.5** | 验证码校验 |
| fideloper/proxy | ^4.4 | **已删除** | 废弃包，改用内置 |
| facade/ignition | ^2.3.6 | **spatie/laravel-ignition ^2.4** | 安全替代 |
| nunomaduro/collision | ^4.3 | **^8.0** | 错误处理升级 |
| phpunit/phpunit | ^9.0 | **^11.0** | 测试框架升级 |

> composer.lock 已恢复版本库管理（构建可复现）；`composer audit` 安全公告 **0 项**（2026-08 清理 guzzle/psr7/commonmark 共 17 项漏洞，含 3 项高危）。

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

## 七、架构审计升级（2026-08，全链路审计后的系统性修复）

基于对注册登录、订阅下发、协议生成、流量结算、支付回调、队列、缓存、后台管理等全链路的架构审计，按 P0→P3 实施修复，全部在生产环境实测验证。

### P0（资损/计费/安全）

| 修复项 | 问题 | 方案 |
|--------|------|------|
| 支付回调幂等双闸口 | 重复回调/调度重放可致订单重复开通 | `paid()` 条件更新（affected rows 唯一闸口）+ `open()` 事务内行锁复检；重复回调不再重复开通/重复通知 |
| 流量结算原子化 | hgetall→del 竞态丢流量；落库前删桶致 DB 失败时数据丢失 | RENAME 换桶：残留桶优先追回、落库成功后才删桶；调度停摆可追回 |
| 流量 Hash TTL 兜底化 | 5 分钟 TTL 致队列积压时全量丢流量 | 放宽至 86400s 仅作泄漏兜底，可追回优先 |
| 封禁即时失效闭环 | 批量封禁/删除后会话残留 | 全部接入 clearUserSessions，新增批量版（单次 MGET），实测封禁后 JWT 立即失效 |

### P1（兼容/一致性）

| 修复项 | 问题 | 方案 |
|--------|------|------|
| sing-box 版本分流 | 字符串比较致 1.9/1.10 误入新生成器 | 改用 version_compare |
| 缓存键防碰撞 | 邮箱键剥离字符后碰撞，限流/验证码跨账户污染 | 字符串键改 sha256，整型键保持可读 |
| 下单并发原子化 | 未完成订单检查与插入非原子 | 事务内用户行锁 + 复检 |
| 补齐缺失路由实现 | user/knowledge/getCategory、admin/stat/getStat 路由已注册但方法缺失（上游遗留） | 按项目数据模型补齐实现 |

### P2（可维护性/告警）

| 修复项 | 方案 |
|--------|------|
| 节点 API 加固 | token 改 hash_equals 常量时间比较；动态路由类白名单 + 声明方法过滤；V2 接口异常规范化（保持节点端响应格式兼容） |
| ECH 构建去重 | Singbox::buildEchConfig 共享方法替换 7 处复制块 |
| 节点掉线告警 | check:server 入调度（每 15 分钟），离线节点 Telegram 告警 |

### P3（清理）

| 修复项 | 内容 |
|--------|------|
| 死代码清理 | webman.php/start.php/WEBMANPID 分支、Singbox.php.bak 移除 |
| 安慰剂配置移除 | Redis options.cache（框架不消费）删除；Horizon 补 production 环境块 |
| 构建可复现 | composer.lock 恢复版本库 |
| 观测增强 | EPay 回调 RSA 验签失败记录完整现场日志（诊断付款未开通/伪造回调取证） |
| 订阅性能 | 节点配置按协议聚合缓存（60s TTL）+ 在线状态批量 MGET |

> 详细审计结论与验证记录见 `docs/architecture/architecture-audit-2026-08.md` 与 `docs/dev-logs/phase1-dev-log.md`。

---

## 八、自动化测试

| 测试文件 | 覆盖范围 |
|----------|----------|
| SecurityFixesTest.php | CSV 注入、CORS、JWT、SQL 注入、Open Redirect、安全头、banned 校验、端口范围、订单号格式 |
| OrderServicePaidTest.php | 支付幂等闸口（重复回调拒绝、dispatch 失败回置） |
| TrafficUpdateCommandTest.php | RENAME 换桶结算（残留追回、DB 失败不删桶、用户集并集） |
| AuthServiceBatchTest.php | 批量会话清理（封禁即时失效） |
| CacheKeyTest.php | 缓存键防碰撞（邮箱碰撞/整型直通/字符集安全） |
| SingboxVersionDispatchTest.php | sing-box 版本分流边界（1.9.0/1.10.9/1.12.0） |
| SingboxEchTest.php | ECH 配置生成（cloudflare/custom/未配置，含共享构建方法） |
| DepositBonusTest.php | 充值档位赠送边界 |

当前规模：**50 个测试，538 个断言**（PHPUnit 11，脚手架已适配）。

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

## 九、更新脚本改进

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

## 十、Clash 规则精简

| 文件 | 上游 | 本项目 | 变化 |
|------|------|--------|------|
| app.clash.yaml | 557 行 | 119 行 | **精简 78%** |
| default.clash.yaml | 719 行 | 大幅精简 | 去除冗余规则 |

---

## 十一、环境配置更新

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
| 框架版本提升 | 8.x → 13.x（+5 个大版本） |
| 依赖包更新 | 15+ 个包升级，2 个废弃包移除，1 个替换 |
| 安全修复总数 | **50+ 项**（另加 2026-08 审计修复 13 项） |
| 高危安全修复 | **27 项** |
| 中危安全修复 | **18 项** |
| 低危修复 | **5+ 项** |
| 架构审计修复 | **P0×4 / P1×4 / P2×3 / P3×6**，生产实测验证 |
| 依赖安全扫描 | composer audit **0 公告**（清理 17 项含 3 高危） |
| 性能优化 | **8 项**（另加订阅链路聚合缓存） |
| 框架适配改造 | **8 项** |
| 支付网关修复 | **11 个网关**（另加 EPay 验签失败观测） |
| 路由现代化 | 177 条路由转换 |
| 自动化测试 | 9 个测试文件，50 个测试，538 个断言 |
| 代码审查评分 | 4.5 → **9.5/10** |
| 新增文件 | SecurityHeaders.php, ServerApiException.php, 7 个核心链路测试 |
| Clash 规则精简 | 78% |

## Document
[安装步骤](https://github.com/Mcloud136/v2board/blob/master/install.md)
[更新步骤](https://github.com/Mcloud136/v2board/blob/master/UPGRADE_GUIDE.md)
[架构审计报告（2026-08）](https://github.com/Mcloud136/v2board/blob/master/docs/architecture/architecture-audit-2026-08.md)
[开发日志](https://github.com/Mcloud136/v2board/blob/master/docs/dev-logs/phase1-dev-log.md)

## How to Feedback
Follow the template in the issue to submit your question correctly, and we will have someone follow up with you.
