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

echo "[1/4] 拉取最新代码..."
git fetch --all
git reset --hard origin/master

echo "[2/4] 安装 Composer 依赖..."
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

echo "[3/4] 清除缓存..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 宝塔面板权限修复（排除 .user.ini，该文件由 PHP-FPM 管理）
if [ -f "/etc/init.d/bt" ]; then
  find "$(pwd)" -not -name ".user.ini" -not -path "$(pwd)/.git/*" -exec chown www:www {} + 2>/dev/null || true
fi

echo "[4/4] 运行更新脚本（含数据库迁移和缓存重建）..."
php artisan v2board:update

echo ""
echo "=========================================="
echo "  更新完成！"
echo "=========================================="
echo ""
echo "[重要] 请手动执行以下操作："
echo "  1. 重启 PHP-FPM: systemctl restart php-fpm-82"
echo "  2. 重启 Nginx:   systemctl restart nginx"
echo "  3. 重启队列:     php artisan horizon"
echo ""
