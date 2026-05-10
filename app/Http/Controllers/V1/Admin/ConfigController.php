<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfigSave;
use App\Jobs\SendEmailJob;
use App\Services\TelegramService;
use App\Utils\Dict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class ConfigController extends Controller
{
    public function getEmailTemplate()
    {
        $path = resource_path('views/mail/');
        $files = array_map(function ($item) use ($path) {
            return str_replace($path, '', $item);
        }, glob($path . '*'));
        return response([
            'data' => $files
        ]);
    }

    public function getThemeTemplate()
    {
        $path = public_path('theme/');
        $files = array_map(function ($item) use ($path) {
            return str_replace($path, '', $item);
        }, glob($path . '*'));
        return response([
            'data' => $files
        ]);
    }

    public function testSendMail(Request $request)
    {
        $obj = new SendEmailJob([
            'email' => $request->user['email'],
            'subject' => 'This is v2board test email',
            'template_name' => 'notify',
            'template_value' => [
                'name' => config('v2board.app_name', 'V2Board'),
                'content' => 'This is v2board test email',
                'url' => config('v2board.app_url')
            ]
        ]);
        return response([
            'data' => true,
            'log' => $obj->handle()
        ]);
    }

    public function setTelegramWebhook(Request $request)
    {
        $hookUrl = secure_url('/api/v1/guest/telegram/webhook?access_token=' . md5(config('v2board.telegram_bot_token', $request->input('telegram_bot_token'))));
        $telegramService = new TelegramService($request->input('telegram_bot_token'));
        $telegramService->getMe();
        $telegramService->setWebhook($hookUrl);
        return response([
            'data' => true
        ]);
    }

    public function fetch(Request $request)
    {
        $key = $request->input('key');
        $defaultConfig = Dict::DEFAULT_CONFIG;
        $data = [];

        foreach ($defaultConfig as $section => $defaults) {
            $data[$section] = [];
            foreach ($defaults as $configKey => $defaultValue) {
                // 特殊处理 secure_path 的默认值
                if ($configKey === 'secure_path') {
                    $configValue = config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key'))));
                } else {
                    $configValue = config('v2board.' . $configKey, $defaultValue);
                }
                
                if (is_int($defaultValue) || in_array($configKey, ['invite_force', 'force_https', 'stop_register', 'try_out_plan_id', 'try_out_hour', 'plan_change_enable', 'reset_traffic_method', 'surplus_enable', 'allow_new_period', 'new_order_event_id', 'renew_order_event_id', 'change_order_event_id', 'show_info_to_server_enable', 'show_subscribe_method', 'show_subscribe_expire', 'email_verify', 'safe_mode_enable', 'email_whitelist_enable', 'email_gmail_limit_enable', 'recaptcha_enable', 'register_limit_by_ip_enable', 'password_limit_enable', 'telegram_bot_enable'])) {
                    $data[$section][$configKey] = (int)$configValue;
                } else {
                    $data[$section][$configKey] = $configValue;
                }
            }
        }
        
        if ($key && isset($data[$key])) {
            return response([
                'data' => [
                    $key => $data[$key]
                ]
            ]);
        }
        return response([
            'data' => $data
        ]);
    }

    public function save(ConfigSave $request)
    {
        $data = $request->validated();
        $config = config('v2board');
        foreach (ConfigSave::RULES as $k => $v) {
            if (!in_array($k, array_keys(ConfigSave::RULES))) {
                unset($config[$k]);
                continue;
            }
            if (array_key_exists($k, $data)) {
                $config[$k] = $data[$k];
            }
        }
        $data = var_export($config, 1);
        if (!File::put(base_path() . '/config/v2board.php', "<?php\n return $data ;")) {
            abort(500, '修改失败');
        }
        if (function_exists('opcache_reset')) {
            if (opcache_reset() === false) {
                abort(500, '缓存清除失败，请卸载或检查opcache配置状态');
            }
        }
        Artisan::call('config:cache');
        if(Cache::has('WEBMANPID')) {
            $pid = Cache::get('WEBMANPID');
            Cache::forget('WEBMANPID');
            return response([
                'data' => posix_kill($pid, 15)
            ]);
        }
        return response([
            'data' => true
        ]);
    }
}
