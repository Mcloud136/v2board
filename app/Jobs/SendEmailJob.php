<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\MailLog;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $params;

    public $backoff = [5, 15, 30];
    // SMTP 握手+渲染+发送 10s 偏紧，超时被杀后重试会造成重复投递
    public $timeout = 30;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($params, $queue = 'send_email', $tries = 3)
    {
        $this->onQueue($queue);
        $this->params = $params;
        // 验证码类邮件传 tries=1，避免重试重复发码
        $this->tries = $tries;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('v2board.email_host')) {
            // Laravel 13 mailers 结构：运行时覆盖 smtp mailer 并强制切换为默认
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', config('v2board.email_host'));
            Config::set('mail.mailers.smtp.port', config('v2board.email_port'));
            Config::set('mail.mailers.smtp.encryption', config('v2board.email_encryption'));
            Config::set('mail.mailers.smtp.username', config('v2board.email_username'));
            Config::set('mail.mailers.smtp.password', config('v2board.email_password'));
            Config::set('mail.from.address', config('v2board.email_from_address'));
            Config::set('mail.from.name', config('v2board.app_name', 'V2Board'));
            // 长驻 worker 内 MailManager 会缓存已解析的 mailer，清除以使运行时配置生效
            if (app()->bound('mail.manager')) {
                app('mail.manager')->purge('smtp');
            }
        }
        $params = $this->params;
        $email = $params['email'];
        $subject = $params['subject'];
        $params['template_name'] = 'mail.' . config('v2board.email_template', 'default') . '.' . $params['template_name'];
        try {
            Mail::send(
                $params['template_name'],
                $params['template_value'],
                function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                }
            );
        } catch (\Exception $e) {
            $log = [
                'email' => $params['email'],
                'subject' => $params['subject'],
                'template_name' => $params['template_name'],
                'error' => $e->getMessage()
            ];
            MailLog::create($log);
            throw $e;
        }

        $log = [
            'email' => $params['email'],
            'subject' => $params['subject'],
            'template_name' => $params['template_name'],
            'error' => NULL
        ];

        MailLog::create($log);
        return $log;
    }
}
