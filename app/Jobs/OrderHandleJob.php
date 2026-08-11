<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OrderHandleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $tradeNo;

    public $tries = 3;
    public $backoff = [5, 15, 30];
    public $timeout = 30;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($tradeNo)
    {
        $this->onQueue('order_handle');
        $this->tradeNo = $tradeNo;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $order = Order::where('trade_no', $this->tradeNo)
            ->first();

        if (!$order) return;

        $orderService = new OrderService($order);
        switch ($order->status) {
            case Order::STATUS_PENDING:
                if ($order->created_at <= (time() - 3600 * 2)) {
                    $orderService->cancel();
                }
                break;
            case Order::STATUS_PAID:
                // check:order 每分钟重放已支付订单，open() 内部行锁闸口保证只开通一次；
                // 对长期停留的订单记录日志便于观察 Job 丢失/异常
                if ($order->updated_at && $order->updated_at < time() - 86400) {
                    \Log::warning('订单停留已支付状态超过 24 小时', ['trade_no' => $order->trade_no]);
                }
                $orderService->open();
                break;
        }
    }
}
