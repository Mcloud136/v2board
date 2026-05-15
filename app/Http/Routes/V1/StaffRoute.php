<?php
namespace App\Http\Routes\V1;

use App\Http\Controllers\V1\Admin\NoticeController as AdminNoticeController;
use App\Http\Controllers\V1\Staff\PlanController as StaffPlanController;
use App\Http\Controllers\V1\Staff\TicketController as StaffTicketController;
use App\Http\Controllers\V1\Staff\UserController as StaffUserController;
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
            $router->get ('/ticket/fetch', [StaffTicketController::class, 'fetch']);
            $router->post('/ticket/reply', [StaffTicketController::class, 'reply']);
            $router->post('/ticket/close', [StaffTicketController::class, 'close']);
            // User
            $router->post('/user/update', [StaffUserController::class, 'update']);
            $router->get ('/user/getUserInfoById', [StaffUserController::class, 'getUserInfoById']);
            $router->post('/user/sendMail', [StaffUserController::class, 'sendMail']);
            $router->post('/user/ban', [StaffUserController::class, 'ban']);
            // Plan
            $router->get ('/plan/fetch', [StaffPlanController::class, 'fetch']);
            // Notice
            $router->get ('/notice/fetch', [AdminNoticeController::class, 'fetch']);
            $router->post('/notice/save', [AdminNoticeController::class, 'save']);
            $router->post('/notice/update', [AdminNoticeController::class, 'update']);
            $router->post('/notice/drop', [AdminNoticeController::class, 'drop']);
        });
    }
}
