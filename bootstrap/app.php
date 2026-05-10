<?php

use App\Utils\CacheKey;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withKernels()
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
            ], function ($router) {
                foreach ((glob(app_path('Http/Routes/V1') . '/*.php') ?: []) as $file) {
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
            ], function ($router) {
                foreach ((glob(app_path('Http/Routes/V2') . '/*.php') ?: []) as $file) {
                    $routeClass = 'App\\Http\\Routes\\V2\\' . basename($file, '.php');
                    if (class_exists($routeClass)) {
                        (new $routeClass)->map($router);
                    }
                }
            });
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => url('/'));

        $middleware->use([
            \Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks::class,
            \App\Http\Middleware\CORS::class,
            \App\Http\Middleware\UAfilter::class,
            \App\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\CheckForMaintenanceMode::class,
            \Illuminate\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        $middleware->group('web', []);

        $middleware->group('api', [
            \App\Http\Middleware\ForceJson::class,
            \App\Http\Middleware\Language::class,
            'bindings',
        ]);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'user' => \App\Http\Middleware\User::class,
            'admin' => \App\Http\Middleware\Admin::class,
            'client' => \App\Http\Middleware\Client::class,
            'staff' => \App\Http\Middleware\Staff::class,
            'log' => \App\Http\Middleware\RequestLog::class,
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\Authenticate::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        Cache::put(CacheKey::get('SCHEDULE_LAST_CHECK_AT', null), time());

        $schedule->command('traffic:update')->everyMinute()->withoutOverlapping();
        $schedule->command('v2board:statistics')->dailyAt('0:10');
        $schedule->command('check:order')->everyMinute()->withoutOverlapping();
        $schedule->command('check:commission')->everyFifteenMinutes();
        $schedule->command('check:ticket')->everyMinute();
        $schedule->command('check:renewal')->dailyAt('22:30');
        $schedule->command('reset:traffic')->daily();
        $schedule->command('reset:log')->daily();
        $schedule->command('send:remindMail')->dailyAt('11:30');
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            //
        });

        $exceptions->render(function (Throwable $e, \Illuminate\Http\Request $request) {
            if (str_contains(get_class($e), 'ViewException')) {
                abort(500, "主题渲染失败。如更新主题，参数可能发生变化请重新配置主题后再试。");
            }
        });
    })->create();

return $app;
