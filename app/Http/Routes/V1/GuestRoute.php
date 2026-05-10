<?php
namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class GuestRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'guest'
        ], function ($router) {
            // Telegram
            $router->post('/telegram/webhook', [\App\Http\Controllers\V1\Guest\TelegramController::class, 'webhook']);
            // Payment
            $router->match(['get', 'post'], '/payment/notify/{method}/{uuid}', [\App\Http\Controllers\V1\Guest\PaymentController::class, 'notify']);
            // Comm
            $router->get ('/comm/config', [\App\Http\Controllers\V1\Guest\CommController::class, 'config']);
        });
    }
}
