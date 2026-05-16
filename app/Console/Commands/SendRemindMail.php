<?php

namespace App\Console\Commands;

use App\Services\MailService;
use Illuminate\Console\Command;
use App\Models\User;

class SendRemindMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:remindMail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '发送提醒邮件';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $mailService = new MailService();
        User::chunk(200, function ($users) use ($mailService) {
            foreach ($users as $user) {
                if ($user->remind_expire) $mailService->remindExpire($user);
                if (!($user->expired_at !== NULL && $user->expired_at < time()) && $user->remind_traffic) {
                    $mailService->remindTraffic($user);
                }
            }
        });
    }
}
