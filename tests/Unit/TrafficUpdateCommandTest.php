<?php

namespace Tests\Unit;

use App\Console\Commands\TrafficUpdate;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FIX-02：流量结算 RENAME 换桶原子化
 * Redis 以 facade mock 驱动，落库用内存 sqlite 隔离生产库
 */
class TrafficUpdateCommandTest extends TestCase
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
        Schema::create('v2_user', function ($table) {
            $table->increments('id');
            $table->bigInteger('u')->default(0);
            $table->bigInteger('d')->default(0);
            $table->integer('t')->nullable();
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
    }

    private function seedUser(int $id, int $u, int $d): void
    {
        User::forceCreate(['id' => $id, 'u' => $u, 'd' => $d, 'created_at' => time(), 'updated_at' => time()]);
    }

    public function test_settles_traffic_union_and_deletes_swap_after_commit(): void
    {
        $this->seedUser(1, 100, 200);
        $this->seedUser(2, 0, 0); // 仅有上行流量的用户也必须被结算（用户集取并集）

        Redis::shouldReceive('exists')->with('traffic_reset_lock')->andReturn(0);
        // 换桶阶段：live 存在、swap 不存在，执行 rename
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_upload_traffic')->andReturn(1)->once();
        Redis::shouldReceive('rename')->with('v2board_upload_traffic', 'v2board_upload_traffic:swap')->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic')->andReturn(1)->once();
        Redis::shouldReceive('rename')->with('v2board_download_traffic', 'v2board_download_traffic:swap')->once();
        // 读取 swap 快照
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('hgetall')->with('v2board_upload_traffic:swap')->andReturn(['2' => '1000'])->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('hgetall')->with('v2board_download_traffic:swap')->andReturn(['1' => '2000'])->once();
        // 落库成功后清理
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('del')->with('v2board_upload_traffic:swap')->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('del')->with('v2board_download_traffic:swap')->once();

        (new TrafficUpdate())->handle();

        $user1 = User::find(1);
        $user2 = User::find(2);
        $this->assertSame(2200, (int)$user1->d); // 200 + 2000
        $this->assertSame(100, (int)$user1->u);
        $this->assertSame(1000, (int)$user2->u); // 仅上行用户被结算
    }

    public function test_leftover_swap_bucket_is_drained_before_rename(): void
    {
        Redis::shouldReceive('exists')->with('traffic_reset_lock')->andReturn(0);
        // 上轮崩溃残留 swap 桶：优先合并 live 数据而不是 rename 覆盖
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('exists')->with('v2board_upload_traffic')->andReturn(1)->once();
        Redis::shouldReceive('hgetall')->with('v2board_upload_traffic')->andReturn(['1' => '5'])->once();
        Redis::shouldReceive('hincrby')->with('v2board_upload_traffic:swap', '1', 5)->once();
        Redis::shouldReceive('del')->with('v2board_upload_traffic')->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic')->andReturn(0)->once();
        // 两个 swap 桶均无最终数据（download 无残留、upload 被 drain 后无 hgetall 数据场景走空）
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('hgetall')->with('v2board_upload_traffic:swap')->andReturn([])->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();

        (new TrafficUpdate())->handle();
        $this->assertTrue(true); // 断言点：上述 mock 序列全部按序命中（Mockery 校验）
    }

    public function test_early_return_when_no_data(): void
    {
        Redis::shouldReceive('exists')->with('traffic_reset_lock')->andReturn(0);
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_upload_traffic')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();
        // 无数据不应触发任何 del
        Redis::shouldReceive('del')->never();

        (new TrafficUpdate())->handle();
        $this->assertTrue(true);
    }
}
