<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Force HTTPS if enabled
            if (config('v2board.force_https')) {
                resolve(\Illuminate\Routing\UrlGenerator::class)->forceScheme('https');
            }

            // Map API V1 routes
            Route::group([
                'prefix' => '/api/v1',
                'middleware' => 'api',
                'namespace' => 'App\\Http\\Controllers'
            ], function ($router) {
                foreach (glob(app_path('Http/Routes/V1') . '/*.php') as $file) {
                    $routeClass = 'App\\Http\\Routes\\V1\\' . basename($file, '.php');
                    if (class_exists($routeClass)) {
                        (new $routeClass)->map($router);
                    }
                }
            });

            // Map API V2 routes
            Route::group([
                'prefix' => '/api/v2',
                'middleware' => 'api',
                'namespace' => 'App\\Http\\Controllers'
            ], function ($router) {
                foreach (glob(app_path('Http/Routes/V2') . '/*.php') as $file) {
                    $routeClass = 'App\\Http\\Routes\\V2\\' . basename($file, '.php');
                    if (class_exists($routeClass)) {
                        (new $routeClass)->map($router);
                    }
                }
            });
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            //
        });
        
        $exceptions->render(function ($request, Throwable $e) {
            if (str_contains(get_class($e), 'ViewException')) {
                abort(500, "主题渲染失败。如更新主题，参数可能发生变化请重新配置主题后再试。");
            }
        });
    })->create();

return $app;
