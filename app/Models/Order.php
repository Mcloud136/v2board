<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    // 订单类型常量
    const TYPE_NEW = 1;        // 新购
    const TYPE_RENEW = 2;      // 续费
    const TYPE_CHANGE = 3;     // 升级/变更
    const TYPE_RESET = 4;      // 重置流量
    const TYPE_DEPOSIT = 9;    // 充值

    // 订单状态常量
    const STATUS_PENDING = 0;  // 待支付
    const STATUS_PAID = 1;     // 已支付
    const STATUS_CANCELLED = 2; // 已取消
    const STATUS_COMPLETED = 3; // 已完成
    const STATUS_PROCESSING = 4; // 处理中

    protected $table = 'v2_order';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'surplus_order_ids' => 'array'
    ];
}
