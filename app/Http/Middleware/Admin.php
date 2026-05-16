<?php

namespace App\Http\Middleware;

use Closure;

class Admin extends AuthenticatesRole
{
    public function handle($request, Closure $next, $role = null)
    {
        return parent::handle($request, $next, 'admin');
    }
}
