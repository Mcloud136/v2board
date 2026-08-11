<?php

namespace App\Services;

use App\Jobs\OrderHandleJob;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    CONST STR_TO_TIME = [
        'month_price' => 1,
        'quarter_price' => 3,
        'half_year_price' => 6,
        'year_price' => 12,
        'two_year_price' => 24,
        'three_year_price' => 36
    ];
    public $order;
    public $user;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function open()
    {
        $order = $this->order;
        $this->user = User::find($order->user_id);
        if (!$this->user) abort(500, '用户不存在');
        if ($order->type == Order::TYPE_DEPOSIT) {
            DB::transaction(function () use ($order) {
                // 幂等闸口二：行锁 + 状态复检，原子拦截并发 Job 与 check:order 重放
                $locked = Order::lockForUpdate()->find($order->id);
                if (!$locked || $locked->status !== Order::STATUS_PAID) return;
                $order = $locked;
                $this->order = $locked;
                $this->user->balance += $order->total_amount + self::getDepositBonus($order->total_amount);
                if (!$this->user->save()) {
                    throw new \Exception('充值失败');
                }
                $order->status = Order::STATUS_COMPLETED;
                if (!$order->save()) {
                    throw new \Exception('充值失败');
                }
            });
            return;
        }

        $plan = Plan::find($order->plan_id);
        if (!$plan) abort(500, '订阅计划不存在');

        DB::transaction(function () use ($order, $plan) {
            // 幂等闸口二：行锁 + 状态复检，原子拦截并发 Job 与 check:order 重放
            $locked = Order::lockForUpdate()->find($order->id);
            if (!$locked || $locked->status !== Order::STATUS_PAID) return;
            $order = $locked;
            $this->order = $locked;

            if ($order->refund_amount) {
                $this->user->balance = $this->user->balance + $order->refund_amount;
            }

            if ($order->surplus_order_ids) {
                Order::whereIn('id', $order->surplus_order_ids)->update([
                    'status' => 4
                ]);
            }

            switch ((string)$order->period) {
                case 'onetime_price':
                    $this->buyByOneTime($order, $plan);
                    break;
                case 'reset_price':
                    $this->buyByResetTraffic();
                    break;
                default:
                    $this->buyByPeriod($order, $plan);
            }

            switch ((int)$order->type) {
                case Order::TYPE_NEW:
                    $this->openEvent(config('v2board.new_order_event_id', 0));
                    break;
                case Order::TYPE_RENEW:
                    $this->openEvent(config('v2board.renew_order_event_id', 0));
                    break;
                case Order::TYPE_CHANGE:
                    $this->openEvent(config('v2board.change_order_event_id', 0));
                    break;
            }

            $this->setSpeedLimit($plan->speed_limit);

            if (!$this->user->save()) {
                throw new \Exception('开通失败');
            }
            $order->status = Order::STATUS_COMPLETED;
            if (!$order->save()) {
                throw new \Exception('开通失败');
            }
        });
    }


    public function setOrderType(User $user)
    {
        $order = $this->order;
        if ($order->period === 'deposit'){
            $order->type = Order::TYPE_DEPOSIT;
        } else if ($order->period === 'reset_price') {
            $order->type = Order::TYPE_RESET;
        } else if ($user->plan_id !== NULL && $order->plan_id !== $user->plan_id && ($user->expired_at > time() || $user->expired_at === NULL)) {
            if (!(int)config('v2board.plan_change_enable', 1)) abort(500, '目前不允许更改订阅，请联系客服或提交工单操作');
            $order->type = Order::TYPE_CHANGE;
            if ((int)config('v2board.surplus_enable', 1)) $this->getSurplusValue($user, $order);
            if ($order->surplus_amount >= $order->total_amount) {
                $order->refund_amount = $order->surplus_amount - $order->total_amount;
                $order->total_amount = 0;
            } else {
                $order->total_amount = $order->total_amount - $order->surplus_amount;
            }
        } else if ($user->expired_at > time() && $order->plan_id == $user->plan_id) { // 用户订阅未过期且购买订阅与当前订阅相同 === 续费
            $order->type = Order::TYPE_RENEW;
        } else { // 新购
            $order->type = Order::TYPE_NEW;
        }
    }

    public function setVipDiscount(User $user)
    {
        $order = $this->order;
        if ($user->discount) {
            $order->discount_amount = $order->discount_amount + ($order->total_amount * ($user->discount / 100));
        }
        $order->total_amount = $order->total_amount - $order->discount_amount;
    }

    public function setInvite(User $user):void
    {
        $order = $this->order;
        if ($user->invite_user_id && ($order->total_amount <= 0)) return;
        $order->invite_user_id = $user->invite_user_id;
        $inviter = User::find($user->invite_user_id);
        if (!$inviter) return;
        $isCommission = false;
        switch ((int)$inviter->commission_type) {
            case 0:
                $commissionFirstTime = (int)config('v2board.commission_first_time_enable', 1);
                $isCommission = (!$commissionFirstTime || ($commissionFirstTime && !$this->haveValidOrder($user)));
                break;
            case 1:
                $isCommission = true;
                break;
            case 2:
                $isCommission = !$this->haveValidOrder($user);
                break;
        }

        if (!$isCommission) return;
        if ($inviter && $inviter->commission_rate) {
            $order->commission_balance = $order->total_amount * ($inviter->commission_rate / 100);
        } else {
            $order->commission_balance = $order->total_amount * (config('v2board.invite_commission', 10) / 100);
        }
    }

    private function haveValidOrder(User $user)
    {
        return Order::where('user_id', $user->id)
            ->whereNotIn('status', [0, 2])
            ->first();
    }

    private function getSurplusValue(User $user, Order $order)
    {
        if ($user->expired_at === NULL) {
            $this->getSurplusValueByOneTime($user, $order);
        } else {
            $this->getSurplusValueByPeriod($user, $order);
        }
    }


    private function getSurplusValueByOneTime(User $user, Order $order)
    {
        $lastOneTimeOrder = Order::where('user_id', $user->id)
            ->where('period', 'onetime_price')
            ->where('status', 3)
            ->orderBy('id', 'DESC')
            ->first();
        if (!$lastOneTimeOrder) return;
        $nowUserTraffic = $user->transfer_enable / 1073741824;
        if ($nowUserTraffic == 0) return;
        $paidTotalAmount = ($lastOneTimeOrder->total_amount + $lastOneTimeOrder->balance_amount);
        if ($paidTotalAmount == 0) return;
        $notUsedTraffic = $nowUserTraffic - (($user->u + $user->d) / 1073741824);
        $remainingTrafficRatio = $notUsedTraffic / $nowUserTraffic;
        $result = $remainingTrafficRatio * $paidTotalAmount;
        $order->surplus_amount = max($result, 0);
        $orderModel = Order::where('user_id', $user->id)->where('period', '!=', 'reset_price')->where('status', 3);
        $order->surplus_order_ids = array_column($orderModel->get()->toArray(), 'id');
    }

    private function getSurplusValueByPeriod(User $user, Order $order)
    {
        $orders = Order::where('user_id', $user->id)
            ->where('period', '!=', 'reset_price')
            ->where('period', '!=', 'onetime_price')
            ->where('period', '!=', 'deposit')
            ->where('status', 3)
            ->get()
            ->toArray();
        if (!$orders) return;
        $orderAmountSum = 0;
        $orderMonthSum = 0;
        $lastValidateAt = null;
        foreach ($orders as $item) {
            if (!isset(self::STR_TO_TIME[$item['period']])) continue;
            $period = self::STR_TO_TIME[$item['period']];
            $orderEndTime = strtotime("+{$period} month", $item['created_at']);
            if ($orderEndTime < time()) continue;
            $lastValidateAt = $item['created_at'] > $lastValidateAt ? $item['created_at'] : $lastValidateAt;
            $orderMonthSum += $period;
            $orderAmountSum += $item['total_amount'] + $item['balance_amount'] + $item['surplus_amount'] - $item['refund_amount'];
        }
        if ($lastValidateAt === null) return;
    
        $expiredAtByOrder = strtotime("+{$orderMonthSum} month", $lastValidateAt);
        $expiredAtByUser = $user->expired_at;
        if ($expiredAtByOrder < time() || $expiredAtByUser < time()) return;
        $orderSurplusSecond = $expiredAtByUser - time();
        $orderRangeSecond = $expiredAtByOrder - $lastValidateAt;
    
        $totalTraffic = $user->transfer_enable;
        $usedTraffic = ($user->u + $user->d);
        if ($totalTraffic == 0) return;
    
        $remainingTrafficRatio = ($totalTraffic - $usedTraffic) / $totalTraffic;
    
        $avgPricePerSecond = $orderAmountSum / $orderRangeSecond;
        if ($orderRangeSecond <= 31 * 86400) {
            $remainingExpiredTimeRatio = $orderSurplusSecond / $orderRangeSecond;
            $surplusRatio = min($remainingExpiredTimeRatio, $remainingTrafficRatio);
            $orderSurplusAmount = $avgPricePerSecond * $orderSurplusSecond * $surplusRatio;
        } else {
            $monthSeconds = 30 * 86400;
            $firstMonthRemainSeconds = $orderSurplusSecond % $monthSeconds;
            $surplusRatio = min($firstMonthRemainSeconds / $monthSeconds, $remainingTrafficRatio);
            $laterMonthsSeconds = $orderSurplusSecond - $firstMonthRemainSeconds;
            $orderSurplusAmount = $avgPricePerSecond * $monthSeconds * $surplusRatio +
                                  $avgPricePerSecond * $laterMonthsSeconds;
        }
    
        $order->surplus_amount = max($orderSurplusAmount, 0);
        $order->surplus_order_ids = array_column($orders, 'id');
    }

    /**
     * 标记订单已支付（幂等闸口一）
     * 使用条件更新的 affected rows 作为唯一闸口：仅成功完成 待支付->已支付 转换的请求返回 true
     *
     * @return bool true=本次完成支付转换；false=已处理过或转换失败（调用方应对网关返回成功，防止无限重试）
     */
    public function paid(string $callbackNo): bool
    {
        $order = $this->order;
        $affected = Order::where('trade_no', $order->trade_no)
            ->where('status', Order::STATUS_PENDING)
            ->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => time(),
                'callback_no' => $callbackNo,
            ]);
        if ($affected !== 1) return false;
        $order->status = Order::STATUS_PAID;
        try {
            OrderHandleJob::dispatch($order->trade_no);
        } catch (\Exception $e) {
            \Log::error('订单处理任务分发失败: ' . $e->getMessage(), ['trade_no' => $order->trade_no]);
            // 分发失败回置待支付，交由网关重试与 check:order 兜底
            Order::where('trade_no', $order->trade_no)
                ->where('status', Order::STATUS_PAID)
                ->update(['status' => Order::STATUS_PENDING]);
            return false;
        }
        return true;
    }

    public function cancel():bool
    {
        $order = $this->order;
        try {
            DB::transaction(function () use ($order) {
                $order->status = Order::STATUS_CANCELLED;
                if (!$order->save()) {
                    throw new \Exception('Cancel failed');
                }
                if ($order->balance_amount) {
                    $userService = new UserService();
                    if (!$userService->addBalance($order->user_id, $order->balance_amount)) {
                        throw new \Exception('Balance restore failed');
                    }
                }
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function setSpeedLimit($speedLimit)
    {
        $this->user->speed_limit = $speedLimit;
    }

    private function buyByResetTraffic()
    {
        $this->user->u = 0;
        $this->user->d = 0;
    }

    private function buyByPeriod(Order $order, Plan $plan)
    {
        // change plan process
        if ((int)$order->type === Order::TYPE_CHANGE) {
            $this->user->expired_at = time();
        }
        $this->user->transfer_enable = $plan->transfer_enable * 1073741824;
        $this->user->device_limit = $plan->device_limit;
        // 从一次性转换到循环
        if ($this->user->expired_at === NULL) $this->buyByResetTraffic();
        // 新购
        if ($order->type === Order::TYPE_NEW) $this->buyByResetTraffic();

        // 到期当天续费刷新流量
        $expireDay = date('d', $this->user->expired_at);
        $expireMonth = date('m', $this->user->expired_at);
        $today = date('d');
        $currentMonth = date('m');
        if ($order->type === Order::TYPE_RENEW && $expireMonth == $currentMonth && $expireDay === $today ) {
            $this->buyByResetTraffic();
        }

        $this->user->plan_id = $plan->id;
        $this->user->group_id = $plan->group_id;
        $this->user->expired_at = $this->getTime($order->period, $this->user->expired_at);
    }

    private function buyByOneTime(Order $order, Plan $plan)
    {
        $transfer_enable = $plan->transfer_enable;
        if (!$order->surplus_order_ids) {
            $notUsedTraffic = ($this->user->transfer_enable - ($this->user->u + $this->user->d)) / 1073741824;
            if ($notUsedTraffic > 0 && $this->user->expired_at == NULL) {
                $transfer_enable += $notUsedTraffic;
            }
        }
        $this->buyByResetTraffic();
        $this->user->transfer_enable = $transfer_enable * 1073741824;
        $this->user->device_limit = $plan->device_limit;
        $this->user->plan_id = $plan->id;
        $this->user->group_id = $plan->group_id;
        $this->user->expired_at = NULL;
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
            default:
                return time();
        }
    }

    private function openEvent($eventId)
    {
        switch ((int) $eventId) {
            case 0:
                break;
            case 1:
                $this->buyByResetTraffic();
                break;
        }
    }

    /**
     * 充值档位赠送计算（配置键 deposit_bounus，前端依赖该键名，勿改）
     * 提升为公共静态方法，供 User/OrderController 等复用，消除重复实现
     */
    public static function getDepositBonus($total_amount) {
        return self::calcDepositBonus(config('v2board.deposit_bounus', []), $total_amount);
    }

    /**
     * 档位赠送纯计算核心（不依赖 config，便于单元测试）
     * 档位格式 "金额:赠送比例"，金额单位元，内部统一转为分比较
     */
    public static function calcDepositBonus($deposit_bounus, $total_amount) {
        if (empty($deposit_bounus) || $deposit_bounus[0] === null) {
            return 0;
        }
        $add = 0;
        foreach ($deposit_bounus as $tier) {
            list($amount, $bounus) = explode(':', $tier);
            $amount = (float)$amount * 100;
            $bounus = (float)$bounus * 100;
            $amount = (int)$amount;
            $bounus = (int)$bounus;
            if ($total_amount >= $amount) {
                $add = max($add, $bounus);
            }
        }
        return $add;
    }
}
