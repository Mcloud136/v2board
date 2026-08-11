# Phase 1 开发日志 — 架构审计修复实施（2026-08）

> 依据：`docs/architecture/architecture-audit-2026-08.md`（审计报告）+ 修复方案 v2（UltraPlan 综合版，含 F1-F6 纰漏复核）。
> 提交规范：每项 FIX 一个独立提交，前缀 `[fix-audit v2 FIX-xx]`，可单独 revert。

## 一、已实施清单

| FIX | 内容 | 变更文件 |
|-----|------|----------|
| FIX-09 | phpunit.xml 升级 PHPUnit 11 schema；删除废弃 tests/Bootstrap.php；新增 tests/README.md 环境说明 | phpunit.xml、tests/ |
| FIX-01 | 支付幂等双闸口：`paid()` 条件更新（affected rows）+ `open()` 事务内行锁复检；网关重复回调对网关应答 success 且不重复通知；`check:order` 重放被闸口二拦截；魔法数字统一为 Order 常量 | app/Services/OrderService.php、app/Http/Controllers/V1/Guest/PaymentController.php、app/Jobs/OrderHandleJob.php |
| FIX-02 | 流量结算 RENAME 换桶：swap 残留优先 drain、落库成功后才删桶、用户集取上/下行并集、保留 traffic_reset_lock | app/Console/Commands/TrafficUpdate.php |
| FIX-03 | 流量 Hash TTL 300→86400 兜底化（可追回优先） | app/Jobs/TrafficFetchJob.php |
| FIX-04 | 批量会话清理 clearUserSessionsBatch（一次 MGET），接入 Admin ban/allDel 与 Staff ban | app/Services/AuthService.php、Admin/UserController.php、Staff/UserController.php |
| FIX-05 | sing-box 版本分流改 version_compare，提取 resolveSingboxClass 静态方法 | app/Http/Controllers/V1/Client/ClientController.php |
| FIX-06 | CacheKey 字符串值改 sha256（前 32 位），消除邮箱键碰撞；整型直通 | app/Utils/CacheKey.php |
| FIX-07 | 下单并发原子化：事务内用户行锁 + 未完成订单复检（充值/普通两分支） | app/Http/Controllers/V1/User/OrderController.php |
| FIX-10 | check:server 入调度（每 15 分钟，部署前确认生产 crontab 无外部调度） | app/Console/Kernel.php |
| FIX-11 | 节点 API 加固：5 处 token 改 hash_equals；动态路由类白名单+声明方法过滤；V2 ServerController exit 改抛 ServerApiException（Handler 渲染为原 {status:'fail'} 结构） | Server 控制器×5、ServerRoute.php、Exceptions/ |
| FIX-12 | ECH 抽象：Singbox::buildEchConfig 静态共享，替换 7 处复制块；删除 Singbox.php.bak | app/Protocols/Singbox/ |
| FIX-13a | getbounus 去重：OrderService::getDepositBonus/calcDepositBonus 公共化 | OrderService.php、User/OrderController.php |
| FIX-13b | 删除 options.cache 安慰剂配置；Horizon 补 production 环境块 | config/database.php、config/horizon.php |
| FIX-13c | 删除 webman.php/start.php、WEBMANPID 分支、UAfilter isWEBMAN 死代码 | 根目录、ConfigController.php、UAfilter.php |
| FIX-13d | 从 88d09c27^ 恢复 composer.lock 入库（构建可复现） | composer.lock |
| FIX-13e | CHANGELOG 测试覆盖表述加勘误；Valkey 计划文档标注已被 FIX-02/03 取代；核实文件编码均为 UTF-8（乱码系 PowerShell 控制台显示问题，审计报告"GBK 乱码"结论修正） | CHANGELOG-1.7.9.md、docs/superpowers/plans/ |
| FIX-13f | 计费/统计口径注释（rate 结算 vs 原始值报表） | TrafficFetchJob.php、StatUserJob.php |
| FIX-13g | 信息节点 hack 保留现状，归档 ADR-001 | docs/decisions/ADR-001-subscribe-info-node.md |
| FIX-13h | 订阅性能：节点配置按 type 聚合 Cache::remember（60s）+ flushServersCache，排序入口主动失效 | app/Services/ServerService.php、Admin/Server/ManageController.php |

## 二、新增测试（7 个文件）

- `tests/Unit/OrderServicePaidTest.php`：支付幂等三分支（sqlite 内存 + Bus::fake）
- `tests/Unit/TrafficUpdateCommandTest.php`：换桶结算/残留 drain/空数据（Redis facade mock + sqlite）
- `tests/Unit/AuthServiceBatchTest.php`：批量会话清理（array 缓存）
- `tests/Unit/CacheKeyTest.php`：键碰撞/稳定性/整型直通/字符集
- `tests/Unit/SingboxVersionDispatchTest.php`：版本分流边界（含旧缺陷形态固化）
- `tests/Unit/DepositBonusTest.php`：档位边界/空配置/[null]
- `tests/Unit/SingboxEchTest.php`（扩充）：buildEchConfig 三分支 + 新 Singbox 类集成

## 三、本地验证结果

- 36 个变更 PHP 文件 `php -l` 语法检查全部通过（PHP 8.2.31）。
- 一致性核查：`v2board_*_traffic` 键仅 TrafficFetchJob（写）与 TrafficUpdate（读/换桶）使用；swap 键无第三方引用。
- PHPUnit 未能本地运行：vendor 未安装（本地无 composer，规则限制不下载安装器）；本地 PHP 缺 pdo_sqlite。运行前置条件见 tests/README.md。

## 四、服务器部署与验证清单（按批次执行）

### 部署实测记录（2026-08-11，服务器 101.36.125.148:2026，/www/wwwroot/wxmuma.cn）

| 项目 | 结果 |
|------|------|
| 备份 | `/www/wwwroot/wxmuma.cn.pre-audit-fix.202608110918` |
| 传输 | tar + scp 增量同步 46 个文件，删除 4 个废弃文件，属主 www:www |
| 激活 | config:cache / route:cache 成功，178 条 api/v1 路由，php-fpm reload + horizon:terminate 重启正常 |
| PHPUnit | **50 tests, 538 assertions, OK**（PHP 8.5.7，服务器 composer install 补装 dev 依赖） |
| check:server 调度 | schedule:list 确认每 15 分钟注册；crontab 仅 schedule:run + SSL 续期，无外部重复调度 |
| stat_user 唯一索引 | 生产已存在（FIX-08 无需补）；v2_user.email 唯一键存在 |
| 订阅 UA 分流 | sing-box 1.9.0/1.10.5 → SingboxOld（domain_resolver:0）；1.12.0 → Singbox（domain_resolver:42）✅ |
| 流量结算演练 | HINCRBY 注入 +5000/+7000 → traffic:update 结算正确、无残留 swap 桶；反向冲销后 u/d 完全恢复基线 ✅ |
| 支付幂等闸口实测 | 测试订单双回调：PAID1:true / PAID2:false / 首个 callback_no 保留 ✅；测试订单与 failed_jobs 残留已清理，余额无影响 |
| 站点健康 | https://www.wxmuma.cn/ 200，Horizon running |

已知环境事实（回填审计待确认项）：
- `QUEUE_CONNECTION=redis`（非 sync，FIX-01 的 dispatch 失败回置分支有效）
- Redis 单机、前缀 `v2board_database_`，phpredis 客户端
- 订阅路径为自定义 `/Delta/Force/BigRED`（v2board.subscribe_path）
- opcache validate_timestamps=Off：部署后必须重启 php-fpm 才能生效（本次已 restart）
- 服务器存在未提交本地改动 app/Payments/EPay.php（+9 行，不在本次变更集，未触碰）

部署动作：git pull 单提交 → `php artisan config:cache && route:cache` → php-fpm reload → `horizon:terminate`（systemd 拉起）。

### 第一批（P0：FIX-01 → FIX-03 → FIX-02，每步观察 24h）

前置：确认生产 `.env` 的 `QUEUE_CONNECTION`（sync 时 Job 同步执行，验证表现不同）、`REDIS_CLIENT`、Redis 单机/集群模式（RENAME 不支持跨 slot）。

1. 支付幂等：同一 trade_no 双并发 `curl` 打 notify → `v2_order.status=3` 且 `v2_user` 时长/余额只叠加一次、Telegram 只收到一条；重放历史已支付订单 notify → 返回 success 且订单不变。
2. 流量链路：`redis-cli HINCRBY <prefix>v2board_download_traffic 1 1000` → `php artisan traffic:update` → `v2_user` 增量正确、`KEYS '*traffic*'` 无残留 swap；临时改错 DB 密码制造落库失败 → swap 桶保留，恢复后下轮追回。
3. `php vendor/bin/phpunit tests/Unit` 全绿（需 pdo_sqlite）。

### 第二批（P1：FIX-04/05/06/07，可同一发布窗口、独立提交）

4. 封禁生效：批量封禁在线测试账号 → 旧 JWT 立即 403；封禁 500 用户耗时对比。
5. 订阅兼容：`curl -H 'User-Agent: sing-box/1.9.0'`、`sing-box/1.12.0`、`ClashforWindows/...` 三种 UA 部署前后输出 diff（1.9.0 应回到旧格式）。
6. 下单并发：双并发 save 应只成功一笔。
7. （DB 低峰窗口）`SHOW INDEX FROM v2_stat_user`；缺失时查重删重后补唯一索引 `server_rate_user_id_record_at`。
8. 注意：FIX-06 上线瞬间旧限流/验证码键成孤儿（TTL ≤600s 自然过期），部署窗口内个别用户需重发验证码。

### 第三批（P2：FIX-10/11/12）

前置：确认生产 crontab 未外部调度 check:server；用测试节点确认 V2 接口错误响应体格式被节点端接受（`{status:'fail',message:...}`，HTTP 200）。

9. 测试节点 5 分钟 push/pull 无 4xx；错误 token 请求返回结构符合节点端预期。
10. ECH 订阅输出 diff（3 种 UA）无意外变化。

### 第四批（P3：FIX-13*）

11. FIX-13c 已随代码删除 webman 死代码：部署后确认配置保存功能正常（`config:cache` 无报错）。

## 五、残余风险与后续

- 调度停摆 >24h 的流量丢失（FIX-03 兜底上限）——建议对 schedule 心跳（SCHEDULE_LAST_CHECK_AT）加告警。
- 生产 `.env` 未知项需在第一批部署前回填本日志。
- FIX-08（stat_user 索引）为 DB 操作，完成后在第二节清单 7 打钩。
- 后续迭代：订阅信息节点 ADR-001 触发条件；TUIC/Hysteria2 的 ECH 支持（需确认 sing-box 兼容性）。

## 六、线上事故记录（2026-08-11，FIX-11 首版白名单缺陷）

- **现象**：部署后各节点 v2node 报错 `decode user list error: jsontext: invalid character '<' at start of value`，用户列表拉取全部失败。
- **根因**：FIX-11 首版 ServerRoute 白名单存在两个缺陷：
  1. 声明类检查用带前导反斜杠的类名（`\App\...`）与 `ReflectionClass::getName()` 返回值（无前导反斜杠）做严格比较，**全部请求被误判拦截**（直接致命缺陷）；
  2. 白名单严格区分大小写，而节点端历史上以 `UniProxy` 大写形式调用（nginx 日志可证），旧代码 `ucfirst()` 天然兼容而新白名单不兼容（兼容性缺陷）。
  Laravel `abort(404)` 经 nginx `error_page 404 /404.html`（文件不存在）转为默认 HTML 404 页，节点按 JSON 解析即报 `invalid character '<'`。
- **修复**：类名 `ltrim` 规范化 + 白名单 `strtolower` 大小写不敏感比较；部署后用真实节点流量验证 user/config/alivelist/push 全部恢复 200/304。
- **教训**：节点对接类路由的任何拦截逻辑变更，上线前必须用节点实际调用的 URL 大小写形式做端到端验证；反射类名比较必须先规范化双方格式。

## 七、服务器全功能点端到端验证（2026-08-11，60+ 检查点）

验证方式：服务器内 Laravel 全栈派发（路由→中间件→控制器）+ nginx 真实链路，只读/无副作用探针。

| 域 | 结果 |
|----|------|
| Web：首页主题渲染、admin secure path | ✅ 200 |
| Passport：登录错误拒绝、注册参数校验、token2Login 无效令牌 | ✅ |
| Guest：公共配置、伪造支付回调安全拦截、Telegram webhook 鉴权 | ✅ |
| 用户端 17 个接口（JWT）：info/getSubscribe/订单/套餐/节点/邀请/公告/工单/会话等 | ✅ |
| 管理端 16 个接口（管理员 JWT）：配置/套餐/用户/订单/节点/支付/统计/系统状态/队列等 | ✅ |
| 越权防护：非管理员访问 admin 路由 403、非客服访问 staff 路由 403 | ✅ |
| 节点 API：UniProxy user/config/alivelist（含大写形式）、V2 config、非法类名拦截、错误 token 拒绝、V2 错误响应兼容旧格式 | ✅ |
| 订阅：sing-box 1.9.0/1.12.0（新旧生成器分流正确）、Clash/Shadowrocket/Quantumult X/Surge/v2rayN 全部 200 | ✅ |
| 分组过滤：不同 group 用户均正常返回 | ✅ |
| 调度器：traffic:update/check:order/check:server 等 10 项全部注册 | ✅ |
| Horizon running；节点拉取/推送时间戳新鲜（<60s）；user1 流量结算延迟 19s | ✅ |

发现并修复的上游遗留缺陷（非本次改造引入）：
1. `User/KnowledgeController::getCategory` 路由已注册但方法缺失 → 用户帮助中心分类 500，已补齐实现；
2. `Admin/StatController::getStat` 同类缺陷 → 管理端每日统计 500，已基于 v2_stat 补齐实现；
3. 已知悉不影响业务的遗留项：`v2_server_log` 表不存在但 `ServerService::log()` 无调用方（死代码）；failed_jobs 存在一条 2026-05-22 的历史 StatUserJob 失败记录（远早于本次变更）。

提交：`8dbc6d79`（缺失方法补齐，生产复验 200）。

## 八、管理面板功能测试（2026-08-11，管理员账号实测，全部使用测试对象并已清理）

| 功能 | 结果 |
|------|------|
| 管理员登录 + 仪表盘接口（config/fetch、systemStatus） | ✅ |
| 套餐生命周期：创建 → 开关切换（plan/update 仅 show/renew，设计如此）→ 全字段编辑（plan/save+id）→ 删除 | ✅ |
| 优惠券生命周期：生成（码由系统随机生成，自定义 code 参数不生效，属设计）→ 删除 | ✅ |
| 用户生命周期：生成 → 登录 → 封禁 → 删除 | ✅ |
| **FIX-04 封禁即时失效闭环**：测试用户登录后被管理员封禁，其既有 JWT 立即被拒（403） | ✅ 核心断言通过 |
| 越权防护：普通用户访问 admin/staff 路由 403 | ✅ |
| 残留清理 | ✅ plans/coupons/users 全部 0 残留 |

结论：管理面板读写功能全部正常，未发现新缺陷；测试中 3 个初报 FAIL 均为测试脚本对接口设计的误判（已逐一核实源码确认）。

## 九、P1/P2/P3 全量修复实施（2026-08-11，基于独立审查报告）

### 实施清单（共 13 个提交，测试 55 项/564 断言全绿）

| 批次 | 修复项 | 核心改动 |
|------|--------|---------|
| 1 流量 | B2/B3/B4/B5 | TrafficUpdate 结算令牌+DB marker 表实现 exactly-once（新增 v2_traffic_settle_marker），提交前二次查重置锁；ResetTraffic 锁 try/finally 闭环+abort 改日志；TrafficFetchJob pipeline 原子写入；StatServerJob 重抛 | 
| 2 支付 | B7/B8 | 4 个 Stripe 驱动汇率失败返回 null（原有 abort 分支生效，杜绝 1:1 少收）；metadata 改 toArray()（修复 source.chargeable 扣款 TypeError）；5 处 catch 改 SDK v20 异常类名 |
| 3 调度/补齐 | B6/B9/B10/B11/B12 | check:commission withoutOverlapping+事务内行锁复检；补齐 getRanking/getStatRecord/setInviteUser（含 type 白名单），删 notice/update 死路由；分组删除全 8 协议检查；AlipayF2F H:i:s；composer php ^8.3 |
| 4 P2 | B1/C1-C10 | mail.php 迁移 Laravel 13 mailers 结构+SendEmailJob purge/超时/tries；CACHE_STORE 等 env 双名兼容；日志默认 env 可配；CORS 收敛；TrustProxies env 可配；日志 warning+脱敏；黑名单消毒移除；CheckRenewal 异常分类+行锁；提醒邮件 Cache::add 去重；filter 白名单一致性；分页上限 500+排序白名单 |
| 5 P3 | D1-D8 | 删 Test.php（含泄露测试密钥文件）/pm2.yaml/死 Request 类/Staff NoticeController；session secure+lax；cors.php 收敛；删统计死缓存方法；audit.ignore 移除（audit 仍 0 公告）；节点告警 6h 冷却 |

### 生产验证（全部通过）

- 结算演练：注入 111/222 → 增量精确落库，marker 表记录结算批次
- 重置锁演练：锁存续期间 live 桶保留不结算，解锁后追回（111+55=166 精确）
- 行为保持：log.channel=mysql（后台系统日志页不受影响）、mail.default=log（SMTP 凭据确认前维持现状）
- 节点对接零影响：UniProxy/V2 接口 200，真实节点流量正常；首页 200；Horizon running；failed_jobs 无新增
- composer audit 移除 ignore 后仍 0 公告

### 待用户确认事项

1. **邮件切换 SMTP**：确认 SMTP 凭据后，将生产 .env 的 MAIL_MAILER 从 log 改为 smtp（或在后台配置 v2board.email_host，Job 会自动切换）
2. **Stripe 渠道**：B7/B8 修复在启用外币/Source 渠道时生效，当前生产未启用属预防性修复
3. **GitHub secret 告警**：Test.php 已从 HEAD 移除，建议在 Stripe 控制台滚动该测试密钥后以 revoked 关闭告警
