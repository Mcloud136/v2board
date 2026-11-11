#!/bin/bash
set -e

echo "=========================================="
echo "  V2Board 安装脚本"
echo "=========================================="
echo ""

# PHP 版本检查（必须在安装依赖之前）
php_main_version=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1)
php_sub_version=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 2)
if [ "$php_main_version" -lt 8 ] || ([ "$php_main_version" -eq 8 ] && [ "$php_sub_version" -lt 3 ]); then
    echo "错误: 需要 PHP 8.3+，当前版本: $(php -v | head -n 1 | cut -d ' ' -f 2)"
    exit 1
fi
echo "✅ PHP 版本检查通过: $(php -v | head -n 1 | cut -d ' ' -f 2)"

# 检查必需 PHP 扩展
echo ""
echo "--- 检查 PHP 扩展 ---"
required_extensions="pdo_mysql redis openssl curl mbstring json xml zip bcmath gd"
missing=0
for ext in $required_extensions; do
    if ! php -m | grep -qi "^${ext}$"; then
        echo "  ❌ 缺少扩展: $ext"
        missing=$((missing + 1))
    fi
done
if [ "$missing" -gt 0 ]; then
    echo "错误: 缺少 $missing 个必需 PHP 扩展，请先安装"
    exit 1
fi
echo "✅ 所有必需 PHP 扩展已安装"

# 检查 .env 文件
echo ""
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "✅ 已从 .env.example 创建 .env 文件"
        echo "⚠️  请编辑 .env 文件配置数据库连接等信息"
    else
        echo "❌ .env 和 .env.example 都不存在，请手动创建 .env"
        exit 1
    fi
else
    echo "✅ .env 文件已存在"
fi

# 下载并安装 Composer 依赖
echo ""
echo "--- 安装 Composer 依赖 ---"
rm -f composer.phar
if command -v wget &> /dev/null; then
    wget -q https://github.com/composer/composer/releases/latest/download/composer.phar -O composer.phar
elif command -v curl &> /dev/null; then
    curl -sL https://github.com/composer/composer/releases/latest/download/composer.phar -o composer.phar
else
    echo "错误：需要 wget 或 curl 来下载 composer"
    exit 1
fi
php composer.phar install --no-dev --optimize-autoloader
echo "✅ Composer 依赖安装完成"

# 运行安装命令
echo ""
echo "--- 运行 V2Board 安装 ---"
php artisan v2board:install

# 重建缓存
echo ""
echo "--- 重建缓存 ---"
php artisan config:cache 2>/dev/null
php artisan route:cache 2>/dev/null
echo "✅ 缓存已重建"

# 宝塔面板权限修复（排除 .user.ini，该文件由 PHP-FPM 管理）
if [ -f "/etc/init.d/bt" ]; then
    echo ""
    echo "--- 修复文件权限 ---"
    find "$(pwd)" -not -name ".user.ini" -not -path "$(pwd)/.git/*" -exec chown www:www {} + 2>/dev/null || true
    echo "✅ 权限已修复"
fi

echo ""
echo "=========================================="
echo "  安装完成！"
echo "=========================================="
echo ""
echo "请手动执行以下操作："
echo "  1. 编辑 .env 文件配置数据库连接"
echo "  2. 重启 PHP-FPM: systemctl restart php-fpm-85"
echo "  3. 重启 Nginx:   systemctl restart nginx"
echo ""
