<?php

namespace App\Console\Commands;

use App\Services\MailService;
use App\Services\PlanService;
use App\Services\OrderService;
use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Order;
use App\Utils\Helper;
use Illuminate\Support\Facades\DB;

use Exception;

class CheckRenewal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:renewal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动续费';

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
        User::where('auto_renewal', 1)
            ->whereNotNull('plan_id')
            ->whereNotNull('expired_at')
            ->where('expired_at', '>', time())
            ->where('expired_at', '<', time() + 86400 * 2)
            ->chunk(200, function ($users) {
                foreach ($users as $user) {
                    try {
                        $latestOrder = Order::where('user_id', $user->id)
                            ->where('period', '!=', 'reset_price')
                            ->where('period', '!=', 'onetime_price')
                            ->where('period', '!=', 'deposit')
                            ->where('status', 3)
                            ->orderBy('created_at', 'desc')
                            ->first();
                        if (!$latestOrder) {
                            throw new Exception("No valid order");
                        }
                        $latestPeriod = $latestOrder->period;

                        $planService = new PlanService($user->plan_id);
                        $plan = $planService->plan;
                        if (!$plan) {
                            throw new Exception("No such plan");
                        }
                        if (!$plan->renew) {
                            throw new Exception('This subscription cannot be renewed');
                        }

                        DB::beginTransaction();
                        $order = new Order();
                        $orderService = new OrderService($order);
                        $order->user_id = $user->id;
                        $order->plan_id = $plan->id;
                        $order->period = $latestPeriod;
                        $order->trade_no = Helper::generateOrderNo();
                        $order->total_amount = $plan[$latestPeriod];
                        $orderService->setVipDiscount($user);
                        $order->type = 2;

                        if ($user->balance < $order->total_amount) {
                            DB::rollback();
                            throw new Exception('No enough balance');
                        }

                        $user->balance = $user->balance - $order->total_amount;
                        $user->expired_at = $this->getTime($latestPeriod, $user->expired_at);
                        if (!$user->save()) {
                            DB::rollback();
                            throw new Exception('自动续费失败');
                        }
                        $order->status = 3;
                        if (!$order->save()) {
                            DB::rollback();
                            throw new Exception('自动续费失败');
                        }
                        DB::commit();
                    } catch (\Exception $e) {
                        if (DB::transactionLevel() > 0) {
                            DB::rollback();
                        }
                        $user->auto_renewal = 0;
                        if(!$user->save()){
                            info('用户自动续费失败,调整设置失败', [$e->getMessage() , $user]);
                        };
                    }
                }
            });
    }

    private function getTime($str, $timestamp)
    {
        if ($timestamp < time()) {
            $timestamp = time();
        }
        switch ($str) {
            case 'month_price':
                return strtotime('+1 month', $timestamp);
            case 'quarter_price':
                return strtotime('+3 month', $timestamp);
            case 'half_year_price':
                return strtotime('+6 month', $timestamp);
            case 'year_price':
                return strtotime('+12 month', $timestamp);
            case 'two_year_price':
                return strtotime('+24 month', $timestamp);
            case 'three_year_price':
                return strtotime('+36 month', $timestamp);
        }
    }
}
