# 使用宝塔面板手动部署

宝塔面板（aaPanel / bt.cn）安装部署指南。

> 请使用 CentOS 7+ 或 Ubuntu 20.04+ 系统，其他系统可能会有未知问题。

## 1. 安装宝塔面板

```bash
# CentOS
yum install -y wget && wget -O install.sh https://download.bt.cn/install/install_6.0.sh && bash install.sh

# Ubuntu/Debian
wget -O install.sh https://download.bt.cn/install/install-ubuntu_6.0.sh && sudo bash install.sh
```

安装完成后登录宝塔面板，在「软件商店」中安装以下软件：

☑️ Nginx（推荐最新版） ☑️ MySQL 5.7+ ☑️ PHP 8.2 ☑️ Redis

> ⚠️ 本项目要求 PHP 8.2+，不支持 PHP 7.x 或 8.0/8.1。

## 2. 安装 PHP 扩展

宝塔面板 → 软件商店 → 找到 PHP 8.2 点击「设置」→「安装扩展」，安装以下扩展：

- `redis`
- `fileinfo`
- `pcntl`

（`pdo_mysql`、`openssl`、`curl`、`mbstring`、`xml` 通常已内置，如缺失也需安装）

## 3. 解除被禁止的函数

宝塔面板 → 软件商店 → 找到 PHP 8.2 点击「设置」→「禁用函数」，将以下函数从列表中**删除**：

- `putenv`
- `proc_open`
- `pcntl_alarm`
- `pcntl_signal`

修改后重启 PHP 服务。

## 4. 添加站点

宝塔面板 →「网站」→「添加站点」：

- **域名**：填入你指向服务器的域名（如 `api.example.com`）
- **数据库**：选择 MySQL，记下数据库名、用户名、密码
- **PHP 版本**：选择 PHP 8.2

## 5. 安装 V2Board

通过 SSH 登录服务器，进入站点目录（如 `/www/wwwroot/api.example.com`）。

以下所有命令都在站点目录下执行。

```bash
# 删除宝塔自动生成的默认文件
chattr -i .user.ini 2>/dev/null
rm -rf .htaccess 404.html index.html .user.ini
```

从 GitHub 克隆项目到当前目录：

```bash
git clone https://github.com/Mcloud136/v2board.git ./
```

执行安装脚本：

```bash
sh init.sh
```

根据提示完成安装（会自动安装依赖、生成配置、导入数据库）。

安装完成后手动执行以下命令确保缓存生效：

```bash
php artisan config:cache
composer dump-autoload
```

## 6. 配置站点目录及伪静态

编辑添加的站点 →「网站目录」→「运行目录」选择 `/public` 保存。

编辑添加的站点 →「伪静态」，填入以下内容：

```nginx
location /downloads {
}

location / {
    try_files $uri $uri/ /index.php$is_args$query_string;
}

location ~ .*\.(js|css)?$
{
    expires      1h;
    error_log off;
    access_log /dev/null;
}
```

## 7. 配置定时任务

宝塔面板 →「计划任务」：

- **任务类型**：Shell 脚本
- **任务名称**：v2board
- **执行周期**：每 1 分钟
- **脚本内容**：

```bash
php /www/wwwroot/你的站点域名/artisan schedule:run
```

点击「添加任务」即可。

## 8. 启动队列服务

V2Board 的系统强依赖队列服务，正常使用必须启动队列服务。

在宝塔面板「软件商店」→「系统工具」中找到 **Supervisor** 进行安装。

安装完成后点击「设置」→「添加守护进程」，按如下填写：

- **名称**：v2board
- **运行用户**：www
- **运行目录**：你的站点目录（如 `/www/wwwroot/api.example.com`）
- **启动命令**：`php artisan horizon`
- **进程数量**：1

点击「确定」添加后即可自动运行。

## 9. 后续更新

后续更新代码只需执行：

```bash
cd /www/wwwroot/api.example.com
bash update.sh
```

`update.sh` 会自动拉取最新代码、安装依赖、运行数据库更新、重启队列。

## 常见问题

**Q：500 错误**

A：检查站点根目录权限，递归 755，保证目录有可写文件的权限：

```bash
chown -R www:www /www/wwwroot/api.example.com
chmod -R 755 /www/wwwroot/api.example.com
chmod -R 777 /www/wwwroot/api.example.com/storage
```

也可能是 Redis 扩展未安装或 Redis 未启动。可通过以下方式排查：

```bash
# 查看 Laravel 错误日志
tail -50 storage/logs/laravel.log

# 检查 PHP 扩展
php -m | grep -iE "redis|fileinfo|pcntl"
```

**Q：页面空白**

A：清除所有缓存：

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
composer dump-autoload
```

**Q：队列不消费**

A：检查 Supervisor 中 v2board 进程是否在运行，必要时重启：

```bash
php artisan horizon:terminate
```

然后在宝塔 Supervisor 中重启进程。

**Q：更新代码后功能异常**

A：清除 OPcache 并重启 PHP：

```bash
php -r "opcache_reset();"
```

然后在宝塔面板中重启 PHP 服务。

**Q：PHP 版本不对**

A：本项目要求 PHP 8.2+，如果当前版本低于 8.2，需要在宝塔面板安装 PHP 8.2 并将站点切换到该版本。
