<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class TrafficFetchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $data;
    protected $server;
    protected $protocol;

    public $tries = 3;
    public $backoff = [5, 15, 30];
    public $timeout = 10;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data, array $server, $protocol)
    {
        $this->onQueue('traffic_fetch');
        $this->data =$data;
        $this->server = $server;
        $this->protocol = $protocol;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 计费口径：用户流量余额按节点倍率 rate 结算（StatUserJob/StatServerJob 记录原始值，仅用于报表）
        foreach(array_keys($this->data) as $userId){
            Redis::hincrby('v2board_upload_traffic', $userId, $this->data[$userId][0] * $this->server['rate']);
            Redis::hincrby('v2board_download_traffic', $userId, $this->data[$userId][1] * $this->server['rate']);
        }
        // 长 TTL 仅作内存泄漏兜底：正常情况 traffic:update 每分钟 RENAME 清空；
        // 写入方每次刷新 TTL，仅在调度停摆超过 24 小时才丢失累计流量（可追回优先于防泄漏）
        Redis::expire('v2board_upload_traffic', 86400);
        Redis::expire('v2board_download_traffic', 86400);
    }
}
