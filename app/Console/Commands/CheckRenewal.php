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
                        // 行锁：防止余额读改写与充值/其他扣减并发丢失更新
                        $user = User::lockForUpdate()->find($user->id);
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
                        \Log::error('自动续费失败: ' . $e->getMessage(), ['user_id' => $user->id]);
                        // 仅业务性原因（无订单/无套餐/不可续费/余额不足）才关闭自动续费；
                        // DB 抖动等瞬时故障不关，留待下一轮调度重试
                        $businessReasons = ['No valid order', 'No such plan', 'This subscription cannot be renewed', 'No enough balance'];
                        if (in_array($e->getMessage(), $businessReasons, true)) {
                            $user->auto_renewal = 0;
                            if (!$user->save()) {
                                \Log::error('关闭自动续费失败', ['user_id' => $user->id]);
                            }
                        }
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
