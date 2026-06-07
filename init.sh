#!/bin/bash
set -e

# PHP 版本检查（必须在安装依赖之前）
php_main_version=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1)
php_sub_version=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 2)
if [ "$php_main_version" -lt 8 ] || ([ "$php_main_version" -eq 8 ] && [ "$php_sub_version" -lt 2 ]); then
    echo "错误: 需要 PHP 8.2+，当前版本: $(php -v | head -n 1 | cut -d ' ' -f 2)"
    exit 1
fi

echo "PHP 版本检查通过: $(php -v | head -n 1 | cut -d ' ' -f 2)"

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

php artisan v2board:install

if [ -f "/etc/init.d/bt" ]; then
  chown -R www "$(pwd)"
fi
