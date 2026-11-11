# V2Board Laravel 13 升级指南

## 一、升级前准备

### 1.1 环境要求

| 项目 | 最低版本 | 推荐版本 | 检查命令 |
|------|---------|---------|---------|
| PHP | 8.3+ | **8.5** | `php -v` |
| Composer | 2.x | 最新版 | `composer --version` |
| MySQL | 5.7+ | 8.0+ | `mysql --version` |
| Redis/Valkey | 6.0+ | 7.0+ | `redis-cli ping` |
| Nginx | 1.20+ | **1.31** | `nginx -v` |

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
- 所有 `pcntl_*` 函数（Horizon 队列需要）

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

### 2.1 拉取最新代码

```bash
cd /path/to/v2board
git pull origin master
```

### 2.2 运行更新脚本

```bash
bash update.sh
```

update.sh 会自动完成：
1. 拉取最新代码
2. 安装 Composer 依赖
3. 清除旧缓存
4. 运行数据库迁移
5. 重启 PHP-FPM、Nginx、Horizon

### 2.3 手动验证（如 update.sh 失败）

```bash
# 安装依赖
composer install --no-dev --optimize-autoloader

# 清除缓存
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 重建缓存
php artisan config:cache

# 重启服务
php artisan horizon:terminate
systemctl restart php-fpm-85
systemctl restart nginx
```

---

## 三、测试步骤

按以下顺序逐项测试，每步确认正常后再进行下一步。

### 3.1 基础验证

```bash
# 验证 Laravel 版本
php artisan --version
# 预期输出：Laravel Framework 13.x.x

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
| 3 | 获取系统状态 | 返回系统信息 |
| 4 | 查看用户列表 | 用户列表正常分页 |
| 5 | 查看套餐列表 | 套餐列表正常 |
| 6 | 查看订单列表 | 订单列表正常 |
| 7 | 查看服务器列表 | 节点列表正常 |
| 8 | 查看统计数据 | 统计数据正常返回 |
| 9 | 保存配置 | 配置保存成功 |

### 3.5 订阅和支付测试

| 步骤 | 操作 | 预期结果 |
|------|------|---------|
| 1 | 获取订阅链接 | 返回正确的订阅内容 |
| 2 | 创建订单 | 订单创建成功 |
| 3 | 支付回调 | 支付状态正确更新 |

---

## 四、Laravel 13 Breaking Changes

### 4.1 PHP 8.3 最低要求

Laravel 13 要求 PHP 8.3+。推荐使用 PHP 8.5。

### 4.2 Symfony 8 组件

所有 Symfony 组件从 7.x 升级到 8.x，包括：
- console、error-handler、finder、http-foundation
- http-kernel、mailer、mime、process、routing、uid、var-dumper

### 4.3 PDO Fetch Modes

查询结果的数组键名可能变化。如使用 `DB::select()` 或 `->toArray()`，请验证输出格式。

### 4.4 Model Boot 限制

不允许在 Model 的 `boot()` 方法中创建新的 Model 实例。

### 4.5 URL 前缀连字符化

`Route::prefix()` 生成的 URL 前缀自动连字符化。

---

## 五、回滚方案

如升级失败，执行以下回滚：

```bash
# 1. 停止服务
systemctl stop php-fpm-85
systemctl stop nginx

# 2. 恢复项目目录
rm -rf /path/to/v2board
cp -r /path/to/v2board_backup_$(date +%Y%m%d) /path/to/v2board

# 3. 恢复数据库
mysql -u root -p v2board < v2board_backup_$(date +%Y%m%d).sql

# 4. 恢复 .env
cp /path/to/.env.backup /path/to/v2board/.env

# 5. 重启服务
systemctl start nginx
systemctl start php-fpm-85
```

---

## 六、常见问题

### Q: 升级后出现 500 错误

A: 检查 Laravel 日志：
```bash
tail -50 storage/logs/laravel.log
```

常见原因：
1. PHP 版本低于 8.3
2. 缺少 PHP 扩展
3. 禁用函数未解除

### Q: Horizon 队列不消费

A: 重启 Horizon：
```bash
php artisan horizon:terminate
```

### Q: 邮件发送失败

A: 检查邮件配置：
```bash
php artisan tinker
Mail::raw('test', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```
