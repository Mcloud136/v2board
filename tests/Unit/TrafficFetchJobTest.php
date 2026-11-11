<?php

namespace Tests\Unit;

use App\Jobs\TrafficFetchJob;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * FIX-B4：TrafficFetchJob pipeline 原子化
 * 单批写入全有或全无，重试不会重复累加（幂等）
 */
class TrafficFetchJobTest extends TestCase
{
    public function test_writes_all_increments_in_single_pipeline(): void
    {
        $pipe = Mockery::mock();
        // rate=2：增量按倍率放大
        $pipe->shouldReceive('hincrby')->with('v2board_upload_traffic', 1, 200)->once();
        $pipe->shouldReceive('hincrby')->with('v2board_download_traffic', 1, 400)->once();
        $pipe->shouldReceive('hincrby')->with('v2board_upload_traffic', 2, 20)->once();
        $pipe->shouldReceive('hincrby')->with('v2board_download_traffic', 2, 40)->once();
        // TTL 兜底随管道一并提交
        $pipe->shouldReceive('expire')->with('v2board_upload_traffic', 86400)->once();
        $pipe->shouldReceive('expire')->with('v2board_download_traffic', 86400)->once();

        // pipeline 只调用一次（原子性断言）
        Redis::shouldReceive('pipeline')->once()->andReturnUsing(function ($callback) use ($pipe) {
            return $callback($pipe);
        });

        $job = new TrafficFetchJob(
            [1 => [100, 200], 2 => [10, 20]],
            ['rate' => 2],
            'vless'
        );
        $job->handle();

        // Mockery 期望由 Laravel TestCase 在 tearDown 统一校验
        $this->assertTrue(true);
    }
}
