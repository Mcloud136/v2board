<?php

namespace Tests\Unit;

use App\Jobs\OrderHandleJob;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FIX-01：支付幂等闸口一（OrderService::paid 条件更新）
 * 使用内存 sqlite 隔离生产库；OrderHandleJob 以 Bus::fake 拦截
 */
class OrderServicePaidTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite_testing']);
        config(['database.connections.sqlite_testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);
        Schema::create('v2_order', function ($table) {
            $table->increments('id');
            $table->string('trade_no');
            $table->integer('status')->default(0);
            $table->integer('paid_at')->nullable();
            $table->string('callback_no')->nullable();
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
    }

    private function createPendingOrder(string $tradeNo): Order
    {
        $order = new Order();
        $order->trade_no = $tradeNo;
        $order->status = Order::STATUS_PENDING;
        $order->created_at = time();
        $order->updated_at = time();
        $order->save();
        return $order;
    }

    public function test_paid_transitions_pending_to_paid_and_dispatches_once(): void
    {
        Bus::fake();
        $order = $this->createPendingOrder('TX-FIRST');

        $this->assertTrue((new OrderService($order))->paid('cb-1'));

        $fresh = Order::find($order->id);
        $this->assertSame(Order::STATUS_PAID, (int)$fresh->status);
        $this->assertSame('cb-1', $fresh->callback_no);
        Bus::assertDispatchedTimes(OrderHandleJob::class, 1);
    }

    public function test_duplicate_callback_does_not_transition_or_redispatch(): void
    {
        Bus::fake();
        $order = $this->createPendingOrder('TX-DUP');

        $this->assertTrue((new OrderService($order))->paid('cb-1'));
        // 重复回调：返回 false（本次未发生转换），调用方对网关仍应答成功
        $this->assertFalse((new OrderService(Order::find($order->id)))->paid('cb-2'));

        $fresh = Order::find($order->id);
        $this->assertSame('cb-1', $fresh->callback_no, '首个回调号必须保留');
        Bus::assertDispatchedTimes(OrderHandleJob::class, 1);
    }

    public function test_paid_order_from_concurrent_check_order_replay_is_rejected(): void
    {
        Bus::fake();
        $order = $this->createPendingOrder('TX-REPLAY');
        $this->assertTrue((new OrderService($order))->paid('cb-1'));

        // 模拟 check:order 每分钟重放：订单已是已支付态，paid 不应再次转换
        $this->assertFalse((new OrderService(Order::find($order->id)))->paid('manual_operation'));
        Bus::assertDispatchedTimes(OrderHandleJob::class, 1);
    }
}
