<?php
namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class UserRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'user',
            'middleware' => 'user'
        ], function ($router) {
            // User
            $router->get ('/unbindTelegram', [\App\Http\Controllers\V1\User\UserController::class, 'unbindTelegram']);
            $router->get ('/resetSecurity', [\App\Http\Controllers\V1\User\UserController::class, 'resetSecurity']);
            $router->get ('/info', [\App\Http\Controllers\V1\User\UserController::class, 'info']);
            $router->post('/newPeriod', [\App\Http\Controllers\V1\User\UserController::class, 'newPeriod']);
            $router->post('/redeemgiftcard', [\App\Http\Controllers\V1\User\UserController::class, 'redeemgiftcard']);
            $router->post('/changePassword', [\App\Http\Controllers\V1\User\UserController::class, 'changePassword']);
            $router->post('/update', [\App\Http\Controllers\V1\User\UserController::class, 'update']);
            $router->get ('/getSubscribe', [\App\Http\Controllers\V1\User\UserController::class, 'getSubscribe']);
            $router->get ('/getStat', [\App\Http\Controllers\V1\User\UserController::class, 'getStat']);
            $router->get ('/checkLogin', [\App\Http\Controllers\V1\User\UserController::class, 'checkLogin']);
            $router->post('/transfer', [\App\Http\Controllers\V1\User\UserController::class, 'transfer']);
            $router->post('/getQuickLoginUrl', [\App\Http\Controllers\V1\User\UserController::class, 'getQuickLoginUrl']);
            $router->get ('/getActiveSession', [\App\Http\Controllers\V1\User\UserController::class, 'getActiveSession']);
            $router->post('/removeActiveSession', [\App\Http\Controllers\V1\User\UserController::class, 'removeActiveSession']);
            // Order
            $router->post('/order/save', [\App\Http\Controllers\V1\User\OrderController::class, 'save']);
            $router->post('/order/checkout', [\App\Http\Controllers\V1\User\OrderController::class, 'checkout']);
            $router->get ('/order/check', [\App\Http\Controllers\V1\User\OrderController::class, 'check']);
            $router->get ('/order/detail', [\App\Http\Controllers\V1\User\OrderController::class, 'detail']);
            $router->get ('/order/fetch', [\App\Http\Controllers\V1\User\OrderController::class, 'fetch']);
            $router->get ('/order/getPaymentMethod', [\App\Http\Controllers\V1\User\OrderController::class, 'getPaymentMethod']);
            $router->post('/order/cancel', [\App\Http\Controllers\V1\User\OrderController::class, 'cancel']);
            // Plan
            $router->get ('/plan/fetch', [\App\Http\Controllers\V1\User\PlanController::class, 'fetch']);
            // Invite
            $router->get ('/invite/save', [\App\Http\Controllers\V1\User\InviteController::class, 'save']);
            $router->get ('/invite/fetch', [\App\Http\Controllers\V1\User\InviteController::class, 'fetch']);
            $router->get ('/invite/details', [\App\Http\Controllers\V1\User\InviteController::class, 'details']);
            // Notice
            $router->get ('/notice/fetch', [\App\Http\Controllers\V1\User\NoticeController::class, 'fetch']);
            // Ticket
            $router->post('/ticket/reply', [\App\Http\Controllers\V1\User\TicketController::class, 'reply']);
            $router->post('/ticket/close', [\App\Http\Controllers\V1\User\TicketController::class, 'close']);
            $router->post('/ticket/save', [\App\Http\Controllers\V1\User\TicketController::class, 'save']);
            $router->get ('/ticket/fetch', [\App\Http\Controllers\V1\User\TicketController::class, 'fetch']);
            $router->post('/ticket/withdraw', [\App\Http\Controllers\V1\User\TicketController::class, 'withdraw']);
            // Server
            $router->get ('/server/fetch', [\App\Http\Controllers\V1\User\ServerController::class, 'fetch']);
            // Coupon
            $router->post('/coupon/check', [\App\Http\Controllers\V1\User\CouponController::class, 'check']);
            // Telegram
            $router->get ('/telegram/getBotInfo', [\App\Http\Controllers\V1\User\TelegramController::class, 'getBotInfo']);
            // Comm
            $router->get ('/comm/config', [\App\Http\Controllers\V1\User\CommController::class, 'config']);
            $router->post('/comm/getStripePublicKey', [\App\Http\Controllers\V1\User\CommController::class, 'getStripePublicKey']);
            // Knowledge
            $router->get ('/knowledge/fetch', [\App\Http\Controllers\V1\User\KnowledgeController::class, 'fetch']);
            $router->get ('/knowledge/getCategory', [\App\Http\Controllers\V1\User\KnowledgeController::class, 'getCategory']);
            // Stat
            $router->get ('/stat/getTrafficLog', [\App\Http\Controllers\V1\User\StatController::class, 'getTrafficLog']);
        });
    }
}
