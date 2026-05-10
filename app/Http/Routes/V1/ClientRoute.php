<?php
namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class ClientRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'client',
            'middleware' => ['client']
        ], function ($router) {
            $router->get('/config', [\App\Http\Controllers\V1\Client\ClientController::class, 'config']);
            $router->post('/config', [\App\Http\Controllers\V1\Client\ClientController::class, 'config']);
        });
        $router->group([
            'prefix' => 'app',
            'middleware' => ['client']
        ], function ($router) {
            $router->get('/version', [\App\Http\Controllers\V1\Client\AppController::class, 'version']);
        });
    }
}
