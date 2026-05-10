<?php
namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class AdminRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key')))),
            'middleware' => ['admin', 'log'],
        ], function ($router) {
            // Config
            $router->get ('/config/fetch', [\App\Http\Controllers\V1\Admin\ConfigController::class, 'fetch']);
            $router->post('/config/save', [\App\Http\Controllers\V1\Admin\ConfigController::class, 'save']);
            $router->get ('/config/getEmailTemplate', [\App\Http\Controllers\V1\Admin\ConfigController::class, 'getEmailTemplate']);
            $router->get ('/config/getThemeTemplate', [\App\Http\Controllers\V1\Admin\ConfigController::class, 'getThemeTemplate']);
            $router->post('/config/setTelegramWebhook', [\App\Http\Controllers\V1\Admin\ConfigController::class, 'setTelegramWebhook']);
            $router->post('/config/testSendMail', [\App\Http\Controllers\V1\Admin\ConfigController::class, 'testSendMail']);
            // Plan
            $router->get ('/plan/fetch', [\App\Http\Controllers\V1\Admin\PlanController::class, 'fetch']);
            $router->post('/plan/save', [\App\Http\Controllers\V1\Admin\PlanController::class, 'save']);
            $router->post('/plan/drop', [\App\Http\Controllers\V1\Admin\PlanController::class, 'drop']);
            $router->post('/plan/update', [\App\Http\Controllers\V1\Admin\PlanController::class, 'update']);
            $router->post('/plan/sort', [\App\Http\Controllers\V1\Admin\PlanController::class, 'sort']);
            // Server
            $router->get ('/server/group/fetch', [\App\Http\Controllers\V1\Admin\Server\GroupController::class, 'fetch']);
            $router->post('/server/group/save', [\App\Http\Controllers\V1\Admin\Server\GroupController::class, 'save']);
            $router->post('/server/group/drop', [\App\Http\Controllers\V1\Admin\Server\GroupController::class, 'drop']);
            $router->get ('/server/route/fetch', [\App\Http\Controllers\V1\Admin\Server\RouteController::class, 'fetch']);
            $router->post('/server/route/save', [\App\Http\Controllers\V1\Admin\Server\RouteController::class, 'save']);
            $router->post('/server/route/drop', [\App\Http\Controllers\V1\Admin\Server\RouteController::class, 'drop']);
            $router->get ('/server/manage/getNodes', [\App\Http\Controllers\V1\Admin\Server\ManageController::class, 'getNodes']);
            $router->post('/server/manage/sort', [\App\Http\Controllers\V1\Admin\Server\ManageController::class, 'sort']);
            $router->group([
                'prefix' => 'server/trojan'
            ], function ($router) {
                $router->post('save', [\App\Http\Controllers\V1\Admin\Server\TrojanController::class, 'save']);
                $router->post('drop', [\App\Http\Controllers\V1\Admin\Server\TrojanController::class, 'drop']);
                $router->post('update', [\App\Http\Controllers\V1\Admin\Server\TrojanController::class, 'update']);
                $router->post('copy', [\App\Http\Controllers\V1\Admin\Server\TrojanController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/vmess'
            ], function ($router) {
                $router->post('save', [\App\Http\Controllers\V1\Admin\Server\VmessController::class, 'save']);
                $router->post('drop', [\App\Http\Controllers\V1\Admin\Server\VmessController::class, 'drop']);
                $router->post('update', [\App\Http\Controllers\V1\Admin\Server\VmessController::class, 'update']);
                $router->post('copy', [\App\Http\Controllers\V1\Admin\Server\VmessController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/shadowsocks'
            ], function ($router) {
                $router->post('save', [\App\Http\Controllers\V1\Admin\Server\ShadowsocksController::class, 'save']);
                $router->post('drop', [\App\Http\Controllers\V1\Admin\Server\ShadowsocksController::class, 'drop']);
                $router->post('update', [\App\Http\Controllers\V1\Admin\Server\ShadowsocksController::class, 'update']);
                $router->post('copy', [\App\Http\Controllers\V1\Admin\Server\ShadowsocksController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/tuic'
            ], function ($router) {
                $router->post('save', [\App\Http\Controllers\V1\Admin\Server\TuicController::class, 'save']);
                $router->post('drop', [\App\Http\Controllers\V1\Admin\Server\TuicController::class, 'drop']);
                $router->post('update', [\App\Http\Controllers\V1\Admin\Server\TuicController::class, 'update']);
                $router->post('copy', [\App\Http\Controllers\V1\Admin\Server\TuicController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/hysteria'
            ], function ($router) {
                $router->post('save', [\App\Http\Controllers\V1\Admin\Server\HysteriaController::class, 'save']);
                $router->post('drop', [\App\Http\Controllers\V1\Admin\Server\HysteriaController::class, 'drop']);
                $router->post('update', [\App\Http\Controllers\V1\Admin\Server\HysteriaController::class, 'update']);
                $router->post('copy', [\App\Http\Controllers\V1\Admin\Server\HysteriaController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/vless'
            ], function ($router) {
                $router->post('save', [\App\Http\Controllers\V1\Admin\Server\VlessController::class, 'save']);
                $router->post('drop', [\App\Http\Controllers\V1\Admin\Server\VlessController::class, 'drop']);
                $router->post('update', [\App\Http\Controllers\V1\Admin\Server\VlessController::class, 'update']);
                $router->post('copy', [\App\Http\Controllers\V1\Admin\Server\VlessController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/anytls'
            ], function ($router) {
                $router->post('save', [\App\Http\Controllers\V1\Admin\Server\AnyTLSController::class, 'save']);
                $router->post('drop', [\App\Http\Controllers\V1\Admin\Server\AnyTLSController::class, 'drop']);
                $router->post('update', [\App\Http\Controllers\V1\Admin\Server\AnyTLSController::class, 'update']);
                $router->post('copy', [\App\Http\Controllers\V1\Admin\Server\AnyTLSController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/v2node'
            ], function ($router) {
                $router->post('save', [\App\Http\Controllers\V1\Admin\Server\V2nodeController::class, 'save']);
                $router->post('drop', [\App\Http\Controllers\V1\Admin\Server\V2nodeController::class, 'drop']);
                $router->post('update', [\App\Http\Controllers\V1\Admin\Server\V2nodeController::class, 'update']);
                $router->post('copy', [\App\Http\Controllers\V1\Admin\Server\V2nodeController::class, 'copy']);
            });
            // Order
            $router->get ('/order/fetch', [\App\Http\Controllers\V1\Admin\OrderController::class, 'fetch']);
            $router->post('/order/update', [\App\Http\Controllers\V1\Admin\OrderController::class, 'update']);
            $router->post('/order/assign', [\App\Http\Controllers\V1\Admin\OrderController::class, 'assign']);
            $router->post('/order/paid', [\App\Http\Controllers\V1\Admin\OrderController::class, 'paid']);
            $router->post('/order/cancel', [\App\Http\Controllers\V1\Admin\OrderController::class, 'cancel']);
            $router->post('/order/detail', [\App\Http\Controllers\V1\Admin\OrderController::class, 'detail']);
            // User
            $router->get ('/user/fetch', [\App\Http\Controllers\V1\Admin\UserController::class, 'fetch']);
            $router->post('/user/update', [\App\Http\Controllers\V1\Admin\UserController::class, 'update']);
            $router->get ('/user/getUserInfoById', [\App\Http\Controllers\V1\Admin\UserController::class, 'getUserInfoById']);
            $router->post('/user/generate', [\App\Http\Controllers\V1\Admin\UserController::class, 'generate']);
            $router->post('/user/dumpCSV', [\App\Http\Controllers\V1\Admin\UserController::class, 'dumpCSV']);
            $router->post('/user/sendMail', [\App\Http\Controllers\V1\Admin\UserController::class, 'sendMail']);
            $router->post('/user/ban', [\App\Http\Controllers\V1\Admin\UserController::class, 'ban']);
            $router->post('/user/resetSecret', [\App\Http\Controllers\V1\Admin\UserController::class, 'resetSecret']);
            $router->post('/user/delUser', [\App\Http\Controllers\V1\Admin\UserController::class, 'delUser']);
            $router->post('/user/allDel', [\App\Http\Controllers\V1\Admin\UserController::class, 'allDel']);
            $router->post('/user/setInviteUser', [\App\Http\Controllers\V1\Admin\UserController::class, 'setInviteUser']);
            // Stat
            $router->get ('/stat/getStat', [\App\Http\Controllers\V1\Admin\StatController::class, 'getStat']);
            $router->get ('/stat/getOverride', [\App\Http\Controllers\V1\Admin\StatController::class, 'getOverride']);
            $router->get ('/stat/getServerLastRank', [\App\Http\Controllers\V1\Admin\StatController::class, 'getServerLastRank']);
            $router->get ('/stat/getServerTodayRank', [\App\Http\Controllers\V1\Admin\StatController::class, 'getServerTodayRank']);
            $router->get ('/stat/getUserLastRank', [\App\Http\Controllers\V1\Admin\StatController::class, 'getUserLastRank']);
            $router->get ('/stat/getUserTodayRank', [\App\Http\Controllers\V1\Admin\StatController::class, 'getUserTodayRank']);
            $router->get ('/stat/getOrder', [\App\Http\Controllers\V1\Admin\StatController::class, 'getOrder']);
            $router->get ('/stat/getStatUser', [\App\Http\Controllers\V1\Admin\StatController::class, 'getStatUser']);
            $router->get ('/stat/getRanking', [\App\Http\Controllers\V1\Admin\StatController::class, 'getRanking']);
            $router->get ('/stat/getStatRecord', [\App\Http\Controllers\V1\Admin\StatController::class, 'getStatRecord']);
            // Notice
            $router->get ('/notice/fetch', [\App\Http\Controllers\V1\Admin\NoticeController::class, 'fetch']);
            $router->post('/notice/save', [\App\Http\Controllers\V1\Admin\NoticeController::class, 'save']);
            $router->post('/notice/update', [\App\Http\Controllers\V1\Admin\NoticeController::class, 'update']);
            $router->post('/notice/drop', [\App\Http\Controllers\V1\Admin\NoticeController::class, 'drop']);
            $router->post('/notice/show', [\App\Http\Controllers\V1\Admin\NoticeController::class, 'show']);
            // Ticket
            $router->get ('/ticket/fetch', [\App\Http\Controllers\V1\Admin\TicketController::class, 'fetch']);
            $router->post('/ticket/reply', [\App\Http\Controllers\V1\Admin\TicketController::class, 'reply']);
            $router->post('/ticket/close', [\App\Http\Controllers\V1\Admin\TicketController::class, 'close']);
            // Coupon
            $router->get ('/coupon/fetch', [\App\Http\Controllers\V1\Admin\CouponController::class, 'fetch']);
            $router->post('/coupon/generate', [\App\Http\Controllers\V1\Admin\CouponController::class, 'generate']);
            $router->post('/coupon/drop', [\App\Http\Controllers\V1\Admin\CouponController::class, 'drop']);
            $router->post('/coupon/show', [\App\Http\Controllers\V1\Admin\CouponController::class, 'show']);
            // Giftcard
            $router->get ('/giftcard/fetch', [\App\Http\Controllers\V1\Admin\GiftcardController::class, 'fetch']);
            $router->post('/giftcard/generate', [\App\Http\Controllers\V1\Admin\GiftcardController::class, 'generate']);
            $router->post('/giftcard/drop', [\App\Http\Controllers\V1\Admin\GiftcardController::class, 'drop']);
            // Knowledge
            $router->get ('/knowledge/fetch', [\App\Http\Controllers\V1\Admin\KnowledgeController::class, 'fetch']);
            $router->get ('/knowledge/getCategory', [\App\Http\Controllers\V1\Admin\KnowledgeController::class, 'getCategory']);
            $router->post('/knowledge/save', [\App\Http\Controllers\V1\Admin\KnowledgeController::class, 'save']);
            $router->post('/knowledge/show', [\App\Http\Controllers\V1\Admin\KnowledgeController::class, 'show']);
            $router->post('/knowledge/drop', [\App\Http\Controllers\V1\Admin\KnowledgeController::class, 'drop']);
            $router->post('/knowledge/sort', [\App\Http\Controllers\V1\Admin\KnowledgeController::class, 'sort']);
            // Payment
            $router->get ('/payment/fetch', [\App\Http\Controllers\V1\Admin\PaymentController::class, 'fetch']);
            $router->get ('/payment/getPaymentMethods', [\App\Http\Controllers\V1\Admin\PaymentController::class, 'getPaymentMethods']);
            $router->post('/payment/getPaymentForm', [\App\Http\Controllers\V1\Admin\PaymentController::class, 'getPaymentForm']);
            $router->post('/payment/save', [\App\Http\Controllers\V1\Admin\PaymentController::class, 'save']);
            $router->post('/payment/drop', [\App\Http\Controllers\V1\Admin\PaymentController::class, 'drop']);
            $router->post('/payment/show', [\App\Http\Controllers\V1\Admin\PaymentController::class, 'show']);
            $router->post('/payment/sort', [\App\Http\Controllers\V1\Admin\PaymentController::class, 'sort']);
            // System
            $router->get ('/system/getSystemStatus', [\App\Http\Controllers\V1\Admin\SystemController::class, 'getSystemStatus']);
            $router->get ('/system/getQueueStats', [\App\Http\Controllers\V1\Admin\SystemController::class, 'getQueueStats']);
            $router->get ('/system/getQueueWorkload', [\App\Http\Controllers\V1\Admin\SystemController::class, 'getQueueWorkload']);
            $router->get ('/system/getQueueMasters', \Laravel\Horizon\Http\Controllers\MasterSupervisorController::class . '@index');
            $router->get ('/system/getSystemLog', [\App\Http\Controllers\V1\Admin\SystemController::class, 'getSystemLog']);
            // Theme
            $router->get ('/theme/getThemes', [\App\Http\Controllers\V1\Admin\ThemeController::class, 'getThemes']);
            $router->post('/theme/saveThemeConfig', [\App\Http\Controllers\V1\Admin\ThemeController::class, 'saveThemeConfig']);
            $router->post('/theme/getThemeConfig', [\App\Http\Controllers\V1\Admin\ThemeController::class, 'getThemeConfig']);
        });
    }
}
