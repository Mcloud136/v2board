<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot(): void
    {
        if (config('v2board.force_https')) {
            resolve(\Illuminate\Routing\UrlGenerator::class)->forceScheme('https');
        }

        Route::middleware('web')
            ->group(base_path('routes/web.php'));

        Route::prefix('/api/v1')
            ->middleware('api')
            ->group(function ($router) {
                (new \App\Http\Routes\V1\AdminRoute())->map($router);
                (new \App\Http\Routes\V1\ClientRoute())->map($router);
                (new \App\Http\Routes\V1\GuestRoute())->map($router);
                (new \App\Http\Routes\V1\PassportRoute())->map($router);
                (new \App\Http\Routes\V1\ServerRoute())->map($router);
                (new \App\Http\Routes\V1\StaffRoute())->map($router);
                (new \App\Http\Routes\V1\UserRoute())->map($router);
            });

        Route::prefix('/api/v2')
            ->middleware('api')
            ->group(function ($router) {
                (new \App\Http\Routes\V2\ServerRoute())->map($router);
            });
    }
}
