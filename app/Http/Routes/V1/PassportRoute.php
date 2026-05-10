<?php
namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class PassportRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'passport'
        ], function ($router) {
            // Auth
            $router->post('/auth/register', [\App\Http\Controllers\V1\Passport\AuthController::class, 'register']);
            $router->post('/auth/login', [\App\Http\Controllers\V1\Passport\AuthController::class, 'login']);
            $router->get ('/auth/token2Login', [\App\Http\Controllers\V1\Passport\AuthController::class, 'token2Login']);
            $router->post('/auth/forget', [\App\Http\Controllers\V1\Passport\AuthController::class, 'forget']);
            $router->post('/auth/getQuickLoginUrl', [\App\Http\Controllers\V1\Passport\AuthController::class, 'getQuickLoginUrl']);
            // Comm
            $router->post('/comm/sendEmailVerify', [\App\Http\Controllers\V1\Passport\CommController::class, 'sendEmailVerify']);
            $router->post('/comm/pv', [\App\Http\Controllers\V1\Passport\CommController::class, 'pv']);
        });
    }
}
