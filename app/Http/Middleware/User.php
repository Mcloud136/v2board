<?php

namespace App\Http\Middleware;

class User extends AuthenticatesRole
{
    public function handle($request, $next)
    {
        return parent::handle($request, $next);
    }
}
