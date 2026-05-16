<?php

namespace App\Http\Middleware;

class Staff extends AuthenticatesRole
{
    public function handle($request, $next)
    {
        return parent::handle($request, $next, 'staff');
    }
}
