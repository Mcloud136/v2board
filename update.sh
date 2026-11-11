#!/bin/bash
set -e

if [ ! -d ".git" ]; then
  echo "错误：请使用 Git 部署。"
  exit 1
fi

if ! command -v git &> /dev/null; then
    echo "错误：Git 未安装！请先安装 git。"
    exit 1
fi

if ! command -v php &> /dev/null; then
    echo "错误：PHP 未安装！"
    exit 1
fi

# 检查 PHP 版本
php_version=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
echo "当前 PHP 版本: $php_version"

echo "=========================================="
echo "  V2Board 更新脚本"
echo "=========================================="
echo ""

# 备份提示
echo "[提示] 建议更新前备份数据库和项目目录："
echo "  mysqldump -u root -p v2board > v2board_backup_$(date +%Y%m%d).sql"
echo ""

git config --global --add safe.directory "$(pwd)"

echo "[1/5] 拉取最新代码..."
git fetch --all
git reset --hard origin/master

echo "[2/5] 安装 Composer 依赖..."
rm -f composer.phar
# 兼容 wget 和 curl
if command -v wget &> /dev/null; then
    wget -q https://github.com/composer/composer/releases/latest/download/composer.phar -O composer.phar
elif command -v curl &> /dev/null; then
    curl -sL https://github.com/composer/composer/releases/latest/download/composer.phar -o composer.phar
else
    echo "错误：需要 wget 或 curl 来下载 composer"
    exit 1
fi
php composer.phar install --no-dev --optimize-autoloader

echo "[3/5] 清除缓存..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 宝塔面板权限修复（排除 .user.ini，该文件由 PHP-FPM 管理）
if [ -f "/etc/init.d/bt" ]; then
  find "$(pwd)" -not -name ".user.ini" -not -path "$(pwd)/.git/*" -exec chown www:www {} + 2>/dev/null || true
fi

echo "[4/5] 运行更新脚本（含数据库迁移和缓存重建）..."
php artisan v2board:update

echo "[5/5] 重启服务..."

# 重启 PHP-FPM（兼容宝塔面板）
if [ -f "/etc/init.d/php-fpm-85" ]; then
    /etc/init.d/php-fpm-85 restart && echo "  ✅ PHP-FPM 已重启" || echo "  ⚠️ PHP-FPM 重启失败，请手动执行: /etc/init.d/php-fpm-85 restart"
elif systemctl is-active --quiet php-fpm-85 2>/dev/null; then
    systemctl restart php-fpm-85 && echo "  ✅ PHP-FPM 已重启" || echo "  ⚠️ PHP-FPM 重启失败，请手动执行: systemctl restart php-fpm-85"
else
    echo "  ⚠️ php-fpm-85 服务未找到，跳过"
fi

# 重启 Nginx（兼容宝塔面板）
if [ -f "/etc/init.d/nginx" ]; then
    /etc/init.d/nginx restart && echo "  ✅ Nginx 已重启" || echo "  ⚠️ Nginx 重启失败，请手动执行: /etc/init.d/nginx restart"
elif systemctl is-active --quiet nginx 2>/dev/null; then
    systemctl restart nginx && echo "  ✅ Nginx 已重启" || echo "  ⚠️ Nginx 重启失败，请手动执行: systemctl restart nginx"
else
    echo "  ⚠️ Nginx 服务未找到，跳过"
fi

# 重启队列（容错：Horizon 可能未运行）
if [ -f artisan ]; then
    php artisan horizon:terminate 2>/dev/null && echo "  ✅ 队列已重启" || echo "  ⚠️ Horizon 未运行，跳过"
fi

echo ""
echo "=========================================="
echo "  更新完成！所有服务已自动重启。"
echo "=========================================="
