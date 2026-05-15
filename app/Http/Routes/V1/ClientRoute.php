<?php
namespace App\Http\Routes\V1;

use App\Http\Controllers\V1\Client\AppController;
use App\Http\Controllers\V1\Client\ClientController;
use Illuminate\Contracts\Routing\Registrar;

class ClientRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'client',
            'middleware' => 'client'
        ], function ($router) {
            // Client
            if (empty(config('v2board.subscribe_path'))) {
                $router->get('/subscribe', [ClientController::class, 'subscribe']);
            }
            // App
            $router->get('/app/getConfig', [AppController::class, 'getConfig']);
            $router->get('/app/getVersion', [AppController::class, 'getVersion']);
        });
    }
}
