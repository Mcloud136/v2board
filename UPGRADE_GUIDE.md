# V2Board Laravel 11 升级指南

## 一、升级前准备

### 1.1 环境要求

| 项目 | 最低版本 | 检查命令 |
|------|---------|---------|
| PHP | 8.2+ | `php -v` |
| Composer | 2.x | `composer --version` |
| MySQL | 5.5+ | `mysql --version` |
| Redis | - | `redis-cli ping` |

### 1.2 必需 PHP 扩展

```bash
php -m | grep -iE "redis|fileinfo|pdo_mysql|openssl|curl|mbstring|xml|pcntl"
```

全部应有输出，缺少任何一个都需要先安装。

### 1.3 禁用函数检查

```bash
php -r "echo ini_get('disable_functions');"
```

以下函数**不能**出现在 disable_functions 中：
- `putenv`
- `proc_open`
- `pcntl_alarm`
- `pcntl_signal`

如果被禁用，编辑 `php.ini`，从 `disable_functions` 中移除这些函数，然后重启 PHP-FPM。

### 1.4 备份（必须执行）

```bash
# 备份数据库
mysqldump -u root -p v2board > v2board_backup_$(date +%Y%m%d).sql

# 备份整个项目目录
cp -r /path/to/v2board /path/to/v2board_backup_$(date +%Y%m%d)

# 备份 .env 文件
cp /path/to/v2board/.env /path/to/v2board/.env.backup
```

---

## 二、升级步骤

### 2.1 拉取升级分支

```bash
cd /path/to/v2board

# 暂存本地改动（如有）
git stash

# 拉取升级分支
git fetch origin
git checkout upgrade/laravel-11
```

### 2.2 安装依赖

```bash
# 安装/更新 Composer 依赖
composer install --no-dev --optimize-autoloader

# 如果 PHP 版本检查失败，使用：
# composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-pcntl
```

### 2.3 清除旧缓存并重建

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 重新生成缓存
php artisan config:cache
php artisan route:cache
```

### 2.4 重启服务

```bash
# 重启队列
php artisan horizon:terminate

# 重启 PHP-FPM（根据实际安装方式选择）
systemctl restart php8.2-fpm
# 或
service php8.2-fpm restart

# 如果使用 Nginx
systemctl restart nginx
```

---

## 三、测试步骤

按以下顺序逐项测试，每步确认正常后再进行下一步。

### 3.1 基础验证

```bash
# 验证 Laravel 版本
php artisan --version
# 预期输出：Laravel Framework 11.x.x

# 验证路由数量
php artisan route:list | wc -l
# 预期：200+ 行

# 验证队列状态
php artisan horizon:status
# 预期：running
```

### 3.2 前台页面测试

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 访问网站首页 `/` | 正常显示主题页面，无 500 错误 |
| 2 | 检查页面标题和 Logo | 显示正确配置的名称和 Logo |
| 3 | 检查知识库页面 | 文章列表正常加载 |

### 3.3 用户认证测试

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 用户登录 `POST /api/v1/passport/auth/login` | 返回 token 和用户信息 |
| 2 | 用户注册 `POST /api/v1/passport/auth/register` | 注册成功，收到验证邮件 |
| 3 | 忘记密码 `POST /api/v1/passport/auth/forget` | 发送重置邮件 |
| 4 | 获取用户信息 `GET /api/v1/user/info` | 返回正确的用户数据 |
| 5 | 修改密码 `POST /api/v1/user/changePassword` | 密码修改成功 |

### 3.4 管理后台测试

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 访问管理后台页面 `/{secure_path}` | 正常加载后台界面 |
| 2 | 管理员登录 | 登录成功，显示仪表盘 |
| 3 | 获取系统状态 `GET /api/v1/{path}/system/getSystemStatus` | 返回系统信息 |
| 4 | 查看用户列表 `GET /api/v1/{path}/user/fetch` | 用户列表正常分页 |
| 5 | 查看套餐列表 `GET /api/v1/{path}/plan/fetch` | 套餐列表正常 |
| 6 | 查看订单列表 `GET /api/v1/{path}/order/fetch` | 订单列表正常 |
| 7 | 查看工单列表 `GET /api/v1/{path}/ticket/fetch` | 工单列表正常 |
| 8 | 查看服务器列表 `GET /api/v1/{path}/server/manage/getNodes` | 节点列表正常 |
| 9 | 查看统计数据 `GET /api/v1/{path}/stat/getStat` | 统计数据正常返回 |
| 10 | 保存配置 `POST /api/v1/{path}/config/save` | 配置保存成功 |

### 3.5 订阅和支付测试

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 获取套餐 `GET /api/v1/user/plan/fetch` | 套餐列表正常 |
| 2 | 获取订阅链接 `GET /api/v1/user/getSubscribe` | 返回正确的订阅 URL |
| 3 | 客户端订阅 `GET /api/v1/client/subscribe` | 返回节点配置信息 |
| 4 | 创建订单 `POST /api/v1/user/order/save` | 订单创建成功 |
| 5 | 获取支付方式 `GET /api/v1/user/order/getPaymentMethod` | 支付方式列表正常 |

### 3.6 Telegram Bot 测试

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 获取 Bot 信息 `GET /api/v1/user/telegram/getBotInfo` | 返回 Bot 配置 |
| 2 | 发送测试消息 | Telegram 收到消息 |

### 3.7 定时任务测试

```bash
# 统计任务
php artisan v2board:statistics --force
# 预期：无报错，正常执行

# 订单检查
php artisan check:order
# 预期：无报错

# 流量更新
php artisan traffic:update
# 预期：无报错
```

### 3.8 队列测试

```bash
# 检查 Horizon 状态
php artisan horizon:status

# 查看队列是否有堆积
php artisan horizon:supervisors

# 手动发送测试邮件，确认邮件队列正常消费
```

---

## 四、常见问题排查

### 4.1 白屏或 500 错误

```bash
# 查看 Laravel 日志
tail -50 storage/logs/laravel.log

# 查看 PHP-FPM 错误日志
tail -50 /var/log/php8.2-fpm.log

# 查看 Nginx 错误日志
tail -50 /var/log/nginx/error.log
```

### 4.2 路由 404

```bash
# 清除路由缓存
php artisan route:clear

# 验证路由注册
php artisan route:list | grep "你的路由"
```

### 4.3 数据库连接失败

```bash
# 检查 .env 数据库配置
cat .env | grep DB_

# 测试数据库连接
php artisan migrate:status
```

### 4.4 Redis 连接失败

```bash
# 检查 Redis 是否运行
redis-cli ping

# 检查 .env Redis 配置
cat .env | grep REDIS_
```

### 4.5 队列不消费

```bash
# 重启 Horizon
php artisan horizon:terminate
php artisan horizon &

# 查看 Horizon 日志
tail -50 storage/logs/horizon.log
```

### 4.6 Composer 依赖冲突

```bash
# 清除 vendor 重新安装
rm -rf vendor composer.lock
composer install --no-dev
```

---

## 五、回退步骤

如果升级后出现无法解决的问题，按以下步骤回退到原版本。

### 5.1 切换回 master 分支

```bash
cd /path/to/v2board

# 切换回 master
git checkout master
```

### 5.2 重新安装旧版依赖

```bash
# 删除新版本的 vendor 和 lock 文件
rm -rf vendor composer.lock

# 安装旧版依赖
composer install --no-dev --optimize-autoloader
```

### 5.3 恢复 .env（如有修改）

```bash
cp .env.backup .env
```

### 5.4 清除缓存并重启

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 重启服务
php artisan horizon:terminate
systemctl restart php8.2-fpm
systemctl restart nginx
```

### 5.5 验证回退成功

```bash
php artisan --version
# 预期输出：Laravel Framework 8.x.x

php artisan route:list | wc -l
# 预期：与升级前一致
```

### 5.6 数据库说明

升级过程**不涉及数据库变更**，无需回退数据库。如果已执行数据库备份且确认需要回退：

```bash
mysql -u root -p v2board < v2board_backup_YYYYMMDD.sql
```

---

## 六、回退检查清单

| 检查项 | 命令 | 预期 |
|--------|------|------|
| Laravel 版本 | `php artisan --version` | 8.x.x |
| 前台页面 | 浏览器访问 `/` | 正常显示 |
| 管理后台 | 浏览器访问 `/{secure_path}` | 正常登录 |
| API 登录 | `POST /api/v1/passport/auth/login` | 返回 token |
| 用户信息 | `GET /api/v1/user/info` | 返回数据 |
| 定时任务 | `php artisan check:order` | 无报错 |
| 队列 | `php artisan horizon:status` | running |
