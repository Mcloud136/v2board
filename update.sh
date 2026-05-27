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
echo "  mysqldump -u root -p v2board > v2board_backup_\$(date +%Y%m%d).sql"
echo ""

git config --global --add safe.directory "$(pwd)"

echo "[1/5] 拉取最新代码..."
git fetch --all
git reset --hard origin/master

echo "[2/5] 安装 Composer 依赖..."
rm -f composer.phar
wget -q https://github.com/composer/composer/releases/latest/download/composer.phar -O composer.phar
php composer.phar install --no-dev --optimize-autoloader

echo "[3/5] 清除缓存..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo "[4/5] 重建缓存..."
php artisan config:cache
php artisan route:cache

echo "[5/5] 运行更新脚本..."
php artisan v2board:update

# 宝塔面板权限修复
if [ -f "/etc/init.d/bt" ]; then
  chown -R www "$(pwd)"
fi

echo ""
echo "=========================================="
echo "  更新完成！"
echo "=========================================="
echo ""
echo "[重要] 请手动执行以下操作："
echo "  1. 重启 PHP-FPM: systemctl restart php8.2-fpm"
echo "  2. 重启 Nginx:   systemctl restart nginx"
echo "  3. 重启队列:     php artisan horizon:terminate"
echo ""
