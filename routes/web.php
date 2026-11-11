<?php

use App\Http\Controllers\V1\Admin\AdminLoginController;
use App\Http\Middleware\AdminCsrf;
use App\Services\AuthService;
use App\Services\ThemeService;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function (Request $request) {
    if (config('v2board.app_url') && config('v2board.safe_mode_enable', 0)) {
        if ($request->getHost() !== parse_url(config('v2board.app_url'))['host']) {
            abort(403);
        }
    }
    $renderParams = [
        'title' => config('v2board.app_name', 'V2Board'),
        'theme' => config('v2board.frontend_theme', 'default'),
        'version' => config('app.version'),
        'description' => config('v2board.app_description', 'V2Board is best'),
        'logo' => config('v2board.logo')
    ];

    if (!config("theme.{$renderParams['theme']}")) {
        $themeService = new ThemeService($renderParams['theme']);
        $themeService->init();
    }

    $renderParams['theme_config'] = config('theme.' . config('v2board.frontend_theme', 'default'));
    return view('theme::' . config('v2board.frontend_theme', 'default') . '.dashboard', $renderParams);
});

$adminPath = config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key'))));

//TODO:: 兼容
Route::get('/' . $adminPath, function (Request $request) use ($adminPath) {
    $sessionId = (string)$request->cookie(config('v2board.web_session_cookie', 'v2board_sid'), '');
    $user = $sessionId !== '' ? AuthService::resolveWebSession($sessionId) : false;
    if (!$user || ((int)($user['is_admin'] ?? 0) !== 1 && (int)($user['is_staff'] ?? 0) !== 1)) {
        return redirect('/' . $adminPath . '/login');
    }
    return view('admin', [
        'title' => config('v2board.app_name', 'V2Board'),
        'theme_sidebar' => config('v2board.frontend_theme_sidebar', 'light'),
        'theme_header' => config('v2board.frontend_theme_header', 'dark'),
        'theme_color' => config('v2board.frontend_theme_color', 'default'),
        'background_url' => config('v2board.frontend_background_url'),
        'version' => config('app.version'),
        'logo' => config('v2board.logo'),
        'secure_path' => $adminPath
    ]);
})->middleware(AdminCsrf::class);

Route::get('/' . $adminPath . '/login', [AdminLoginController::class, 'showLogin']);
Route::post('/' . $adminPath . '/login', [AdminLoginController::class, 'login']);

if (!empty(config('v2board.subscribe_path'))) {
    Route::get(config('v2board.subscribe_path'), [\App\Http\Controllers\V1\Client\ClientController::class, 'subscribe'])->middleware('client');
}