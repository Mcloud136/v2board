<?php
namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class StaffRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'staff',
            'middleware' => 'staff'
        ], function ($router) {
            // Ticket
            $router->get ('/ticket/fetch', [\App\Http\Controllers\V1\Staff\TicketController::class, 'fetch']);
            $router->post('/ticket/reply', [\App\Http\Controllers\V1\Staff\TicketController::class, 'reply']);
            $router->post('/ticket/close', [\App\Http\Controllers\V1\Staff\TicketController::class, 'close']);
            // User
            $router->post('/user/update', [\App\Http\Controllers\V1\Staff\UserController::class, 'update']);
            $router->get ('/user/getUserInfoById', [\App\Http\Controllers\V1\Staff\UserController::class, 'getUserInfoById']);
            $router->post('/user/sendMail', [\App\Http\Controllers\V1\Staff\UserController::class, 'sendMail']);
            $router->post('/user/ban', [\App\Http\Controllers\V1\Staff\UserController::class, 'ban']);
            // Plan
            $router->get ('/plan/fetch', [\App\Http\Controllers\V1\Staff\PlanController::class, 'fetch']);
            // Notice
            $router->get ('/notice/fetch', [\App\Http\Controllers\V1\Admin\NoticeController::class, 'fetch']);
            $router->post('/notice/save', [\App\Http\Controllers\V1\Admin\NoticeController::class, 'save']);
            $router->post('/notice/update', [\App\Http\Controllers\V1\Admin\NoticeController::class, 'update']);
            $router->post('/notice/drop', [\App\Http\Controllers\V1\Admin\NoticeController::class, 'drop']);
        });
    }
}
