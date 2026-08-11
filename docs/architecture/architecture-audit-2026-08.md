# V2Board 架构审计报告（2026-08）

> 审计范围：master 分支最新提交 `5d423d86`。覆盖此前多轮 AI 升级（Laravel 12→13、Valkey 流量优化、Singbox ECH、6 轮 review 修复）。
> 审计方式：静态代码审计 + 提交历史 + 升级计划文档交叉验证。未做运行时验证（需生产环境信息，见第七节）。

---

## 一、项目架构现状总结

### 1.1 技术栈与运行时

| 维度 | 现状 |
|------|------|
| 框架 | Laravel 13（composer `^13.0`）+ PHP `^8.2`（CHANGELOG 声称 8.5） |
| 队列 | Horizon + Redis 驱动，分队列：`traffic_fetch` / `stat` / `order_handle` 等 |
| 缓存 | Redis/Valkey（`CACHE_STORE`），JWT 会话、限流、节点状态均存缓存 |
| 调度 | `app/Console/Kernel.php`：traffic:update、check:order、reset:traffic 等 |
| 入口 | PHP-FPM + Nginx（生产）；`webman.php`/`start.php` 为上游遗留死代码 |
| 配置 | 管理端配置写入运行时生成的 `config/v2board.php`（`ConfigController::save`） |

### 1.2 模块职责

- **Passport**（注册/登录/找回密码）：JWT(HS256) + Redis 会话白名单（`AuthService`）
- **Client**（订阅下发）：`Client` 中间件按 token 认证 → `ClientController::subscribe` 按 UA 分发到 `app/Protocols/*`
- **Server**（节点 API）：V1 `UniProxy/Deepbwork/Tidalab` + V2 `ServerController`，静态 `server_token` 认证
- **流量链路**：节点 push → `UserService::trafficFetch` → `TrafficFetchJob`(hincrby) + `StatUserJob`/`StatServerJob` → `traffic:update` 命令每分钟落库
- **支付链路**：`Guest/PaymentController::notify` → `PaymentService` → `OrderService::paid` → `OrderHandleJob::open`
- **Admin/Staff**：`AuthenticatesRole` 中间件按 `is_admin`/`is_staff` 鉴权

### 1.3 此前 AI 升级的总体评价

方向基本正确（框架升级、Job 重试退避、密码哈希自动升级、开放重定向防护、CSV 注入防护），但存在三类系统性问题：

1. **文档/实现不一致**：计划文档描述的"字段级 TTL"实际实现为整 Hash `expire(300)`；计划中的 `TrafficOptimizationTest` 未落库。
2. **安慰剂式优化**：`config/database.php` 的 `options.cache`（Valkey 客户端缓存）Laravel 并不消费该配置项，纯摆设。
3. **局部修补未闭环**：`clearUserSessions` 助手函数已建好但批量封禁/删除路径未接入。

---

## 二、关键功能链路分析

### 2.1 注册/登录

调用链：`AuthController::register/login` → `AuthService::generateAuthData` → JWT + `USER_SESSIONS` 缓存。

- 正向设计合理：JWT exp 24h、会话白名单可撤销、登录失败限流、旧 md5/sha256 密码登录时自动升级 bcrypt（`AuthController::login:175`）。
- **问题 A（缓存键碰撞）**：`CacheKey::get()` 用 `preg_replace('/[^a-zA-Z0-9_\-]/','')` 清洗（`CacheKey.php:50`）。邮箱作为键时 `a.b@c.com` 与 `ab@c.com` 会归一为同一键 `abccom`，导致 `PASSWORD_ERROR_LIMIT`、`EMAIL_VERIFY_CODE`、`FORGET_REQUEST_LIMIT` 跨邮箱互相污染——攻击者可通过注册/试错相近邮箱对目标邮箱实施锁定（DoS）或验证码串扰。
- **问题 B（注册限流非原子）**：`register` 先 `Cache::get` 后 `Cache::put(count+1)`（`AuthController.php:26,121`），并发下计数不准，且邮箱重复检查 `User::where('email')->first()` 与 `save()` 之间存在 TOCTOU 窗口（若 DB 无唯一索引则产生重复邮箱）。
- **问题 C（封禁延迟）**：`AuthenticatesRole` 的 `banned` 判断来自 JWT 缓存的用户快照（TTL 900s，`AuthService.php:60`）。封禁后最长 15 分钟内旧会话仍可访问（仅 `Admin/UserController::update` 封禁时清了会话，见 2.6）。

### 2.2 订阅下发

调用链：`Client` 中间件（token/OTP/TOTP 三种模式）→ `ClientController::subscribe` → `ServerService::getAvailableServers` → 协议类渲染。

- **问题 D（版本比较 Bug，确凿）**：`ClientController.php:44` `$version >= '1.12.0'` 为**字符串比较**，`'1.9.0' >= '1.12.0'` 为真（逐字符比 '9' > '1'）。sing-box 1.9.x/1.10.x 客户端会被错误路由到新 `Singbox` 生成器，可能下发不兼容配置。应使用 `version_compare()`。
- **问题 E（信息节点 hack）**：`setSubscribeInfoToServers` 把"剩余流量/到期时间"伪造成节点条目插到列表头部（复制 `$servers[0]` 的真实节点配置仅改名）。用户客户端可以真实连接这些"节点"，属于绕过式实现；更规范的做法是订阅响应头（`Subscription-Userinfo`）或独立信息位。
- **问题 F（性能）**：每次订阅执行 8 种协议表全表查询 + PHP 侧 group 过滤 + 每协议一次 `Cache::get(last_check_at)`；`glob` 实例化全部协议类匹配 UA。节点/用户量大时是热点路径，无任何缓存层。

### 2.3 协议配置生成（ECH）

- ECH 代码块在 `SingboxOld.php` 中 **4 处逐字复制**（buildVmess:156、buildVless:227、buildTrojan:286、buildHysteria:366），`Singbox.php` 中同样重复；`buildTuic`、`buildHysteria2`、`buildShadowsocks` **未覆盖**——功能半成品且后续维护要同步改 N 处。
- `app/Protocols/Singbox/Singbox.php.bak` 被提交入库（`git ls-files` 确认），属残留物。
- 服务端密钥处理正确：`ServerService::getAvailableVless/V2node` 下发前剥离 `private_key`/`ech_key`；`Helper::generateEchKeyPair` 在 Admin 保存时自动生成密钥对。

### 2.4 流量统计与上报

调用链：节点 push → `UniProxyController::push` → `UserService::trafficFetch` → 3 个 Job → `traffic:update` 落库。

- **问题 G（丢流量窗口未消除，反被放大）**：
  - `TrafficUpdate.php:46-53`：`hgetall` 与 `del` 之间写入的流量仍会丢失（升级计划文档声称"消除丢失窗口"，实际只是把无条件 DEL 改成非空才 DEL，竞态原样保留）。全局最优解是 `RENAME` 换桶或 Lua 脚本原子读取并清零。
  - `TrafficFetchJob.php:49-50` 对整个 Hash `expire(300)`：一旦 `traffic:update`（调度器）或队列停摆超过 5 分钟，**累计流量全部被 TTL 抹掉**。旧实现最多丢竞态窗口内的数据，新实现把"小概率丢一点"换成了"故障时全丢"——典型局部最优。
  - `TrafficUpdate.php:55`：落库用户集只取 `array_keys($downloads)`，只有上行流量的用户不会被结算（实际场景中 u/d 同报可缓解，但属隐藏边界）。
- **问题 H（统计语义不一致）**：`TrafficFetchJob` 乘了 `$server['rate']`，而 `StatUserJob`/`StatServerJob` 记录原始值——用户余额与报表统计口径不同，前端展示时易误读。
- **问题 I（upsert 依赖不存在性未知）**：`StatUserJob` 用 `upsert(..., ['user_id','server_rate','record_at'])`，MySQL 无对应唯一索引时 upsert 退化为纯 insert，产生重复行（需核对生产 schema，见第七节）。
- `traffic_reset_lock` 无任何代码路径会设置它——死机制。

### 2.5 订单与支付回调

调用链：`User/OrderController::save/checkout` → 网关 → `Guest/PaymentController::notify` → `OrderService::paid` → `OrderHandleJob` → `OrderService::open`。

- **问题 J（支付回调非幂等，P0）**：`Guest/PaymentController::handle:35` 与 `OrderService::paid:254` 都是"先查 status 再更新"，无行锁、无条件更新。网关重试/并发回调时两个请求都能通过检查，各自 dispatch 一个 `OrderHandleJob`；而 `OrderHandleJob::handle` 加载订单后直接 `open()`，`open()` 内部也不校验状态、不加锁——**同一订单可能被开通两次**（时长叠加、充值余额双倍）。应使用 `Order::where('trade_no',x)->where('status',0)->update(...)` 条件更新作为幂等闸口，或 `lockForUpdate`。
- **问题 K（下单并发）**：`OrderController::save:77` 的"已有未完成订单"检查与下单非原子，并发可产生两笔待付订单（影响小但同源问题）。
- 正向点：`paid()` 对 dispatch 失败有 catch 并返回 false 触发网关重试（`OrderService.php:259-264`）；`addBalance` 使用 `lockForUpdate`；`cancel()` 事务化退余额。

### 2.6 后台管理

- **问题 L（封禁闭环不完整）**：`Admin/UserController::update`（单用户，`L190-193`）封禁时清会话 ✅；但批量 `ban()`（`L354-367`，`User::whereIn->update`）**不清会话**，`allDel`/`delUser` 也不清。`clearUserSessions` 静态方法（`AuthService.php:115`）注释明确写着"可用于批量操作（封禁、删除等）"，实际零调用——修复只做了一半。叠加问题 C，批量封禁用户最长 15 分钟仍在线。
- **问题 M（动态路由面）**：`ServerRoute` 的 `/api/v1/server/{class}/{action}` 反射解析控制器并调用任意 public 方法（含继承方法），仅靠构造函数 token 拦截。token 比较用 `!==`（`UniProxyController.php:27` 等）而非 `hash_equals`，理论上存在时序侧信道；且 `V2/ServerController` 构造函数用 `response()->send(); exit;` 直接终止（`ServerController.php:22-49`），绕过中间件收尾与日志。
- **问题 N（审计日志形同虚设）**：`RequestLog` 中间件只 `info("POST {$path}")`，无操作者、无参数；`MysqlLogger` 存在但未见挂载到 admin 写操作链。管理员高危操作（封禁、删用户、改配置）无有效审计。

### 2.7 调度与死代码

- `check:server`（节点掉线 Telegram 告警）**未注册进 schedule**（`Kernel.php` 无此项），命令存在但永不调度——除非生产 crontab 单独调用（需确认）。
- `webman.php`/`start.php` 死代码仍被 `ConfigController::save:219-224` 引用（WEBMANPID 分支），增加理解成本。
- `composer.lock` 已从版本库移除（`88d09c27`），生产构建不再可复现，且 `composer.json` 显式 ignore 了安全公告 `PKSA-y2cr-5h3j-g3ys`（未说明理由）。

### 2.8 测试覆盖

仓库内仅 4 个有效测试文件：`ExampleTest×2`、`SecurityFixesTest`（CSV 注入/CORS/JWT exp）、`SingboxEchTest`。**注册、订阅、流量结算、支付回调、订单开通等核心链路零测试**。Valkey 优化计划中承诺的 `TrafficOptimizationTest` 根本不存在。CHANGELOG 宣称的"测试覆盖"与实际严重不符。

---

## 三、局部最优风险清单

| # | 实现 | 局部看 | 全局视角的问题 | 判定 |
|---|------|--------|----------------|------|
| 1 | 流量 Hash `expire(300)`（TrafficFetchJob） | 防止 Hash 无限增长 | 调度停摆 5min 即全量丢流量，放大故障损失 | 局部最优 |
| 2 | TrafficUpdate "非空才 DEL" | 减少无效 DEL | hgetall→del 竞态未动，文档宣称的"消除丢失窗口"不实 | 局部最优 |
| 3 | `redis.options.cache` Valkey 客户端缓存 | 配置看起来支持了 | Laravel 不消费该选项，无实际效果，误导后续维护 | 安慰剂 |
| 4 | `clearUserSessions` 静态助手 | 提供了批量清理能力 | 批量封禁/删除未接入，封禁一致性仍破缺 | 半闭环 |
| 5 | JWT 缓存 TTL 300→900 | 减少 DB 查询 | 封禁/删除生效延迟同步放大到 15min，未与其他机制联动 | 代价未对冲 |
| 6 | ECH 四处复制粘贴 | 四个方法都有了 | Tuic/Hysteria2 缺失、改一处漏三处、.bak 入库 | 局部最优 |
| 7 | CacheKey 键清洗 | 防缓存键注入 | 邮箱键碰撞导致限流/验证码跨账户污染 | 引入新风险 |
| 8 | sing-box 版本字符串比较 | 实现了版本分流 | 1.9/1.10 被误判为 ≥1.12，错误下发新格式 | Bug |
| 9 | Job 统一 tries/backoff/timeout | — | 瞬时故障恢复能力真实提升 | 全局改进 |
| 10 | 登录时旧哈希自动升级 bcrypt | — | 存量密码安全面收敛 | 全局改进 |
| 11 | token2Login/getQuickLoginUrl 开放重定向防护 | — | 有效 | 全局改进 |
| 12 | `paid()` dispatch 失败返回 false 触发网关重试 | — | 可靠性提升 | 全局改进 |

---

## 四、全局优化建议

1. **支付幂等（最高优先）**：`OrderService::paid` 改为条件更新
   `Order::where('trade_no',$no)->where('status',0)->update(['status'=>1,...])`，以 affected rows 作为唯一闸口；`OrderHandleJob::open` 入口重新加载并校验 `status===1`。
2. **流量结算原子化**：`traffic:update` 改用 `RENAME v2board_upload_traffic v2board_upload_traffic:swap`（不存在时忽略）再 hgetall swap 桶；移除或大幅放宽 Hash TTL（仅作兜底，如 24h），恢复"队列积压可追回"的特性。
3. **会话失效闭环**：批量 `ban()`/`allDel`/`delUser` 接入 `AuthService::clearUserSessions`；或将 JWT 缓存 TTL 降回 300s 并在封禁路径统一清理，二者取一形成明确策略。
4. **订阅版本判断**：改 `version_compare($version, '1.12.0', '>=')`。
5. **缓存键策略**：邮箱类键改用 `hash('sha256', $email)` 而非剥离字符，消除碰撞。
6. **ECH 抽象**：提取 `buildEchConfig(array $tlsSettings): ?array` 到 Singbox 基类/Helper，所有 build* 与两种 Singbox 版本共用，并补齐 Tuic/Hysteria2。
7. **调度补全**：`check:server` 加入 schedule（每 5-15 分钟）；删除 `traffic_reset_lock` 死代码或实现其加锁场景。
8. **节点 API 加固**：token 比较改 `hash_equals`；`{class}/{action}` 动态路由改为白名单映射；V2 ServerController 构造函数改抛 AbortException 而非 `exit`。
9. **清理与可复现**：删除 `Singbox.php.bak`、评估移除 webman.php/start.php 及 WEBMANPID 分支；恢复 composer.lock 入库或固化部署镜像；说明 audit ignore 公告的理由。
10. **测试补强**：优先补支付回调幂等（并发模拟）、流量 hgetall/DEL 竞态、订单开通三类 Feature 测试；其次注册限流与订阅 UA 分流。
11. **文档卫生**：`CHANGELOG-1.7.9.md`、多个 PHP 文件内注释为 GBK 乱码，统一 UTF-8；升级计划中引用的路径（C:/Users/XOS、/www/wwwroot）与仓库脱钩。

---

## 五、优先级排序

| 级别 | 事项 | 理由 |
|------|------|------|
| P0 | 支付回调幂等（#1） | 直接资损风险：重复开通/双倍充值 |
| P0 | 流量结算原子化 + TTL 策略（#2） | 计费准确性，当前方案故障时全量丢数据 |
| P0 | 封禁会话失效闭环（#3） | 安全控制失效窗口 15min |
| P1 | sing-box 版本比较（#4） | 存量 1.9/1.10 客户端可能拿到不兼容配置 |
| P1 | 缓存键碰撞（#5） | 可被利用的跨账户锁定/验证码串扰 |
| P1 | StatUser 唯一索引核实（三-9） | upsert 退化导致统计失真 |
| P2 | ECH 抽象与补齐、check:server 调度、节点 API 加固 | 可维护性与告警可用性 |
| P3 | 死代码清理、测试体系、文档编码 | 长期维护成本 |

---

## 六、潜在风险汇总

- **资损**：支付重复回调 → 重复开通（问题 J）。
- **计费丢失**：调度/队列停摆 >5min → 流量全丢（问题 G）。
- **安全窗口**：批量封禁后 15min 内可继续使用（C+L）；缓存键碰撞可跨账户触发锁定（A）。
- **兼容性**：sing-box 1.9/1.10 订阅配置可能错误（D）。
- **运维**：节点掉线无告警（check:server 未调度）；composer.lock 缺失导致构建不可复现。
- **审计**：管理端高危操作无有效日志（N）。

---

## 七、仅凭代码无法确认、需补充的信息

1. **生产 `.env`**：`QUEUE_CONNECTION`（若为 sync，则 OrderHandleJob 退化为同步执行，幂等问题表现不同）、`REDIS_CLIENT`、`CACHE_STORE`、实际 Redis 还是 Valkey 及版本。
2. **数据库 schema**：`v2board_stat_user` 是否有 `(user_id, server_rate, record_at)` 唯一索引；`v2board_user.email` 是否有唯一索引。
3. **生产 crontab**：是否独立调度了 `check:server`（代码内 schedule 未包含）。
4. **composer audit 忽略的公告 `PKSA-y2cr-5h3j-g3ys`** 对应组件与忽略理由。
5. **用户/节点规模量级**：订阅链路 8 表全扫与 `getAvailableUsers` 内存拉全量（`ini_set memory_limit 2G`）是否需要提前做缓存/分页。
