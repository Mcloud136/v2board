# V2Board - 代理协议管理系统

## 项目概述
基于 Laravel 12 的代理节点管理平台，支持 VLESS/VMess/Trojan/TUIC/Hysteria/Shadowsocks/AnyTLS/V2node 等协议。

## 技术栈
- **PHP**: 8.2+
- **Laravel**: 12.59.0
- **数据库**: MySQL/PostgreSQL
- **缓存/队列**: Redis + Laravel Horizon
- **前端**: 独立前端项目（不在本仓库）

## 目录结构
```
app/
├── Console/Commands/     # Artisan 命令（定时任务、流量重置等）
├── Http/
│   ├── Controllers/
│   │   ├── V1/Admin/     # 管理员 API（需 admin 认证）
│   │   ├── V1/User/      # 用户 API（需 user 认证）
│   │   ├── V1/Staff/     # 客服 API（需 staff 认证）
│   │   ├── V1/Passport/  # 登录/注册/找回密码
│   │   ├── V1/Guest/     # 公开 API（支付回调、Telegram webhook）
│   │   ├── V1/Client/    # 客户端订阅 API
│   │   └── V1/Server/    # 节点后端通信 API（token 认证）
│   ├── Middleware/        # 中间件（认证、CORS、安全头等）
│   └── Requests/         # 表单验证请求
├── Jobs/                 # 队列任务（邮件、流量统计等）
├── Models/               # Eloquent 模型
├── Payments/             # 支付网关（Stripe、支付宝、USDT 等）
├── Protocols/            # 订阅格式（Clash、Surge、Singbox 等）
├── Services/             # 业务逻辑层
└── Utils/                # 工具类
```

## 关键约定
- 所有路由前缀 `api/v1/`，管理面板路由带 `secure_path` 前缀
- 认证使用 JWT（firebase/php-jwt），非 Laravel Sanctum
- 金额单位为**分**（100 = 1 元），流量单位为**字节**（1073741824 = 1GB）
- 时间戳使用 Unix 时间戳（整数），Model 使用 `$dateFormat = 'U'`
- 配置存储在 `config/v2board.php`（运行时由管理员修改）
- 所有用户输入的列名必须加白名单验证
- 所有 `find()` 后必须 null 检查
- 禁止使用 `rand()/mt_rand()`，统一使用 `random_int()`
- 禁止使用 `$_POST/$_SERVER/$_GET`，统一使用 `$request`
- 错误信息禁止暴露 `$e->getMessage()` 给用户，需先 `\Log::error()`

## 常用命令
```bash
php artisan route:list              # 查看路由
php artisan v2board:install         # 安装
php artisan v2board:update          # 更新数据库
php artisan traffic:update          # 流量更新（每分钟）
php artisan reset:traffic           # 流量重置
php artisan check:commission        # 佣金结算
php artisan horizon                 # 启动队列
```
