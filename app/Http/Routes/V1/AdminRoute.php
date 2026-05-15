<?php
namespace App\Http\Routes\V1;

use App\Http\Controllers\V1\Admin\ConfigController;
use App\Http\Controllers\V1\Admin\CouponController;
use App\Http\Controllers\V1\Admin\GiftcardController;
use App\Http\Controllers\V1\Admin\KnowledgeController;
use App\Http\Controllers\V1\Admin\NoticeController;
use App\Http\Controllers\V1\Admin\OrderController;
use App\Http\Controllers\V1\Admin\PaymentController;
use App\Http\Controllers\V1\Admin\PlanController;
use App\Http\Controllers\V1\Admin\StatController;
use App\Http\Controllers\V1\Admin\SystemController;
use App\Http\Controllers\V1\Admin\ThemeController;
use App\Http\Controllers\V1\Admin\TicketController;
use App\Http\Controllers\V1\Admin\UserController;
use App\Http\Controllers\V1\Admin\Server\AnyTLSController;
use App\Http\Controllers\V1\Admin\Server\GroupController;
use App\Http\Controllers\V1\Admin\Server\HysteriaController;
use App\Http\Controllers\V1\Admin\Server\ManageController;
use App\Http\Controllers\V1\Admin\Server\RouteController;
use App\Http\Controllers\V1\Admin\Server\ShadowsocksController;
use App\Http\Controllers\V1\Admin\Server\TrojanController;
use App\Http\Controllers\V1\Admin\Server\TuicController;
use App\Http\Controllers\V1\Admin\Server\V2nodeController;
use App\Http\Controllers\V1\Admin\Server\VlessController;
use App\Http\Controllers\V1\Admin\Server\VmessController;
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
            $router->get ('/config/fetch', [ConfigController::class, 'fetch']);
            $router->post('/config/save', [ConfigController::class, 'save']);
            $router->get ('/config/getEmailTemplate', [ConfigController::class, 'getEmailTemplate']);
            $router->get ('/config/getThemeTemplate', [ConfigController::class, 'getThemeTemplate']);
            $router->post('/config/setTelegramWebhook', [ConfigController::class, 'setTelegramWebhook']);
            $router->post('/config/testSendMail', [ConfigController::class, 'testSendMail']);
            // Plan
            $router->get ('/plan/fetch', [PlanController::class, 'fetch']);
            $router->post('/plan/save', [PlanController::class, 'save']);
            $router->post('/plan/drop', [PlanController::class, 'drop']);
            $router->post('/plan/update', [PlanController::class, 'update']);
            $router->post('/plan/sort', [PlanController::class, 'sort']);
            // Server
            $router->get ('/server/group/fetch', [GroupController::class, 'fetch']);
            $router->post('/server/group/save', [GroupController::class, 'save']);
            $router->post('/server/group/drop', [GroupController::class, 'drop']);
            $router->get ('/server/route/fetch', [RouteController::class, 'fetch']);
            $router->post('/server/route/save', [RouteController::class, 'save']);
            $router->post('/server/route/drop', [RouteController::class, 'drop']);
            $router->get ('/server/manage/getNodes', [ManageController::class, 'getNodes']);
            $router->post('/server/manage/sort', [ManageController::class, 'sort']);
            $router->group([
                'prefix' => 'server/trojan'
            ], function ($router) {
                $router->post('save', [TrojanController::class, 'save']);
                $router->post('drop', [TrojanController::class, 'drop']);
                $router->post('update', [TrojanController::class, 'update']);
                $router->post('copy', [TrojanController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/vmess'
            ], function ($router) {
                $router->post('save', [VmessController::class, 'save']);
                $router->post('drop', [VmessController::class, 'drop']);
                $router->post('update', [VmessController::class, 'update']);
                $router->post('copy', [VmessController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/shadowsocks'
            ], function ($router) {
                $router->post('save', [ShadowsocksController::class, 'save']);
                $router->post('drop', [ShadowsocksController::class, 'drop']);
                $router->post('update', [ShadowsocksController::class, 'update']);
                $router->post('copy', [ShadowsocksController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/tuic'
            ], function ($router) {
                $router->post('save', [TuicController::class, 'save']);
                $router->post('drop', [TuicController::class, 'drop']);
                $router->post('update', [TuicController::class, 'update']);
                $router->post('copy', [TuicController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/hysteria'
            ], function ($router) {
                $router->post('save', [HysteriaController::class, 'save']);
                $router->post('drop', [HysteriaController::class, 'drop']);
                $router->post('update', [HysteriaController::class, 'update']);
                $router->post('copy', [HysteriaController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/vless'
            ], function ($router) {
                $router->post('save', [VlessController::class, 'save']);
                $router->post('drop', [VlessController::class, 'drop']);
                $router->post('update', [VlessController::class, 'update']);
                $router->post('copy', [VlessController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/anytls'
            ], function ($router) {
                $router->post('save', [AnyTLSController::class, 'save']);
                $router->post('drop', [AnyTLSController::class, 'drop']);
                $router->post('update', [AnyTLSController::class, 'update']);
                $router->post('copy', [AnyTLSController::class, 'copy']);
            });
            $router->group([
                'prefix' => 'server/v2node'
            ], function ($router) {
                $router->post('save', [V2nodeController::class, 'save']);
                $router->post('drop', [V2nodeController::class, 'drop']);
                $router->post('update', [V2nodeController::class, 'update']);
                $router->post('copy', [V2nodeController::class, 'copy']);
            });
            // Order
            $router->get ('/order/fetch', [OrderController::class, 'fetch']);
            $router->post('/order/update', [OrderController::class, 'update']);
            $router->post('/order/assign', [OrderController::class, 'assign']);
            $router->post('/order/paid', [OrderController::class, 'paid']);
            $router->post('/order/cancel', [OrderController::class, 'cancel']);
            $router->post('/order/detail', [OrderController::class, 'detail']);
            // User
            $router->get ('/user/fetch', [UserController::class, 'fetch']);
            $router->post('/user/update', [UserController::class, 'update']);
            $router->get ('/user/getUserInfoById', [UserController::class, 'getUserInfoById']);
            $router->post('/user/generate', [UserController::class, 'generate']);
            $router->post('/user/dumpCSV', [UserController::class, 'dumpCSV']);
            $router->post('/user/sendMail', [UserController::class, 'sendMail']);
            $router->post('/user/ban', [UserController::class, 'ban']);
            $router->post('/user/resetSecret', [UserController::class, 'resetSecret']);
            $router->post('/user/delUser', [UserController::class, 'delUser']);
            $router->post('/user/allDel', [UserController::class, 'allDel']);
            $router->post('/user/setInviteUser', [UserController::class, 'setInviteUser']);
            // Stat
            $router->get ('/stat/getStat', [StatController::class, 'getStat']);
            $router->get ('/stat/getOverride', [StatController::class, 'getOverride']);
            $router->get ('/stat/getServerLastRank', [StatController::class, 'getServerLastRank']);
            $router->get ('/stat/getServerTodayRank', [StatController::class, 'getServerTodayRank']);
            $router->get ('/stat/getUserLastRank', [StatController::class, 'getUserLastRank']);
            $router->get ('/stat/getUserTodayRank', [StatController::class, 'getUserTodayRank']);
            $router->get ('/stat/getOrder', [StatController::class, 'getOrder']);
            $router->get ('/stat/getStatUser', [StatController::class, 'getStatUser']);
            $router->get ('/stat/getRanking', [StatController::class, 'getRanking']);
            $router->get ('/stat/getStatRecord', [StatController::class, 'getStatRecord']);
            // Notice
            $router->get ('/notice/fetch', [NoticeController::class, 'fetch']);
            $router->post('/notice/save', [NoticeController::class, 'save']);
            $router->post('/notice/update', [NoticeController::class, 'update']);
            $router->post('/notice/drop', [NoticeController::class, 'drop']);
            $router->post('/notice/show', [NoticeController::class, 'show']);
            // Ticket
            $router->get ('/ticket/fetch', [TicketController::class, 'fetch']);
            $router->post('/ticket/reply', [TicketController::class, 'reply']);
            $router->post('/ticket/close', [TicketController::class, 'close']);
            // Coupon
            $router->get ('/coupon/fetch', [CouponController::class, 'fetch']);
            $router->post('/coupon/generate', [CouponController::class, 'generate']);
            $router->post('/coupon/drop', [CouponController::class, 'drop']);
            $router->post('/coupon/show', [CouponController::class, 'show']);
            // Giftcard
            $router->get ('/giftcard/fetch', [GiftcardController::class, 'fetch']);
            $router->post('/giftcard/generate', [GiftcardController::class, 'generate']);
            $router->post('/giftcard/drop', [GiftcardController::class, 'drop']);
            // Knowledge
            $router->get ('/knowledge/fetch', [KnowledgeController::class, 'fetch']);
            $router->get ('/knowledge/getCategory', [KnowledgeController::class, 'getCategory']);
            $router->post('/knowledge/save', [KnowledgeController::class, 'save']);
            $router->post('/knowledge/show', [KnowledgeController::class, 'show']);
            $router->post('/knowledge/drop', [KnowledgeController::class, 'drop']);
            $router->post('/knowledge/sort', [KnowledgeController::class, 'sort']);
            // Payment
            $router->get ('/payment/fetch', [PaymentController::class, 'fetch']);
            $router->get ('/payment/getPaymentMethods', [PaymentController::class, 'getPaymentMethods']);
            $router->post('/payment/getPaymentForm', [PaymentController::class, 'getPaymentForm']);
            $router->post('/payment/save', [PaymentController::class, 'save']);
            $router->post('/payment/drop', [PaymentController::class, 'drop']);
            $router->post('/payment/show', [PaymentController::class, 'show']);
            $router->post('/payment/sort', [PaymentController::class, 'sort']);
            // System
            $router->get ('/system/getSystemStatus', [SystemController::class, 'getSystemStatus']);
            $router->get ('/system/getQueueStats', [SystemController::class, 'getQueueStats']);
            $router->get ('/system/getQueueWorkload', [SystemController::class, 'getQueueWorkload']);
            $router->get ('/system/getQueueMasters', [\Laravel\Horizon\Http\Controllers\MasterSupervisorController::class, 'index']);
            $router->get ('/system/getSystemLog', [SystemController::class, 'getSystemLog']);
            // Theme
            $router->get ('/theme/getThemes', [ThemeController::class, 'getThemes']);
            $router->post('/theme/saveThemeConfig', [ThemeController::class, 'saveThemeConfig']);
            $router->post('/theme/getThemeConfig', [ThemeController::class, 'getThemeConfig']);
        });
    }
}
