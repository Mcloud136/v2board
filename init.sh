#!/bin/bash

rm -rf composer.phar
wget https://github.com/composer/composer/releases/latest/download/composer.phar -O composer.phar
php composer.phar install --no-dev --optimize-autoloader

php_main_version=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1)
php_sub_version=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 2)
if [ $php_main_version -lt 8 ] || ([ $php_main_version -eq 8 ] && [ $php_sub_version -lt 2 ]); then
    echo "Error: PHP 8.2+ is required. Current version: $(php -v | head -n 1 | cut -d ' ' -f 2)"
    exit 1
fi

php artisan v2board:install

if [ -f "/etc/init.d/bt" ]; then
  chown -R www $(pwd);
fi
