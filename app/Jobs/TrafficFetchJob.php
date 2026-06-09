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
        foreach(array_keys($this->data) as $userId){
            Redis::hincrby('v2board_upload_traffic', $userId, $this->data[$userId][0] * $this->server['rate']);
            Redis::hincrby('v2board_download_traffic', $userId, $this->data[$userId][1] * $this->server['rate']);
        }
        // 设置 Hash 整体 TTL（5 分钟），避免未处理数据无限增长
        // TrafficUpdate 每分钟处理一次，5 分钟 TTL 提供安全余量
        Redis::expire('v2board_upload_traffic', 300);
        Redis::expire('v2board_download_traffic', 300);
    }
}
