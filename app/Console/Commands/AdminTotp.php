<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthService;
use App\Utils\Totp;
use Illuminate\Console\Command;

class AdminTotp extends Command
{
    protected $signature = 'admin:totp {email} {--disable}';

    protected $description = '为管理员/员工绑定或解除 TOTP';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user || (!$user->is_admin && !$user->is_staff)) {
            $this->error('管理员账号不存在');
            return self::FAILURE;
        }

        if ($this->option('disable')) {
            $user->totp_secret = null;
            $user->totp_enabled = 0;
            $user->totp_last_used_counter = null;
            $user->save();
            AuthService::clearUserSessions($user->id);
            $this->info('已解除 TOTP，该账号旧会话已失效');
            return self::SUCCESS;
        }

        $secret = Totp::generateSecret();
        $issuer = (string)config('v2board.app_name', 'V2Board');
        $this->line('Secret: ' . $secret);
        $this->line('otpauth: ' . Totp::provisioningUri($secret, $user->email, $issuer));
        $code = (string)$this->ask('请输入认证器中的 6 位验证码以确认绑定');
        $counter = Totp::verify($secret, $code);
        if ($counter === false) {
            $this->error('验证码不正确，未绑定');
            return self::FAILURE;
        }

        $user->totp_secret = $secret;
        $user->totp_enabled = 1;
        $user->totp_last_used_counter = $counter;
        $user->save();
        AuthService::clearUserSessions($user->id);

        $this->info('绑定成功，该账号旧会话已失效，请重新通过管理端闸口登录');
        return self::SUCCESS;
    }
}
