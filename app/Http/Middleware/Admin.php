<?php

namespace App\Http\Middleware;

class Admin extends AuthenticatesRole
{
    public function handle($request, $next)
    {
        return parent::handle($request, $next, 'admin');
    }
}
