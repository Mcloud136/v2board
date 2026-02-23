<img src="https://avatars.githubusercontent.com/u/56885001?s=200&v=4" alt="logo" width="130" height="130" align="right"/>

[![](https://img.shields.io/badge/TgChat-@UnOfficialV2board讨论-blue.svg)](https://t.me/unofficialV2board)

## 本分支支持的后端
 - [修改版V2bX](https://github.com/wyx2685/V2bX)
 - [v2node](https://github.com/wyx2685/v2node)

## 原版迁移步骤

按以下步骤进行面板代码文件迁移：

    git remote set-url origin https://github.com/Mcloud136/v2board  
    git checkout master  
    ./update.sh  


按以下步骤配置缓存驱动为redis，然后刷新设置缓存，重启队列:

    sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=redis/' .env
    php artisan config:clear
    php artisan config:cache
    php artisan horizon:terminate

最后进入后台重新保存主题： 主题配置-选择default主题-主题设置-确定保存

# **V2Board**

- PHP7.3+
- Composer
- MySQL5.5+
- Redis
- Laravel

修改数据库地址
V2Board通过.env文件管理数据库连接信息，修改以下参数：

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
DB_DATABASE=v2board
DB_USERNAME=root
DB_PASSWORD=your_password

## Demo
[Demo_user](https://v2bdemo.v-50.me/)
[Demo_admin](https://v2bdemo.v-50.me/admindashboard)
邮箱和密码可随意输入

## Document
[Click](https://v2board.com)

    git clone https://github.com/Mcloud136/v2board ./

## Sponsors
Thanks to the open source project license provided by [Jetbrains](https://www.jetbrains.com/)

## Community
🔔Telegram Group: [@unofficialV2board](https://t.me/unofficialV2board)  

## How to Feedback
Follow the template in the issue to submit your question correctly, and we will have someone follow up with you.
