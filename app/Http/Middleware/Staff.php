<?php

namespace App\Http\Middleware;

use Closure;

class Staff extends AuthenticatesRole
{
    public function handle($request, Closure $next)
    {
        return parent::handle($request, $next, 'staff');
    }
}
