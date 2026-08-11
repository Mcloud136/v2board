<?php

namespace Tests\Unit;

use App\Console\Commands\TrafficUpdate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * FIX-B3/B2：流量结算 exactly-once（结算令牌 + DB marker）
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
        Schema::create('v2_traffic_settle_marker', function ($table) {
            $table->string('token', 80)->primary();
            $table->integer('settled_at');
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
        // 首轮残留桶检查（两桶均空 → 直接返回，无任何写操作）
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();
        // 换桶
        Redis::shouldReceive('exists')->with('v2board_upload_traffic')->andReturn(1)->once();
        Redis::shouldReceive('rename')->with('v2board_upload_traffic', 'v2board_upload_traffic:swap')->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic')->andReturn(1)->once();
        Redis::shouldReceive('rename')->with('v2board_download_traffic', 'v2board_download_traffic:swap')->once();
        // 结算令牌生成
        Redis::shouldReceive('exists')->with('v2board_traffic_settle_token')->andReturn(0)->once();
        Redis::shouldReceive('setex')->with('v2board_traffic_settle_token', 86400, Mockery::type('string'))->once();
        // 读取 swap 快照
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('hgetall')->with('v2board_upload_traffic:swap')->andReturn(['2' => '1000'])->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('hgetall')->with('v2board_download_traffic:swap')->andReturn(['1' => '2000'])->once();
        Redis::shouldReceive('get')->with('v2board_traffic_settle_token')->andReturn('ts_test')->once();
        // 提交成功后清理 swap 桶与令牌
        Redis::shouldReceive('del')->with('v2board_upload_traffic:swap', 'v2board_download_traffic:swap', 'v2board_traffic_settle_token')->once();

        (new TrafficUpdate())->handle();

        $user1 = User::find(1);
        $user2 = User::find(2);
        $this->assertSame(2200, (int)$user1->d); // 200 + 2000
        $this->assertSame(100, (int)$user1->u);
        $this->assertSame(1000, (int)$user2->u); // 仅上行用户被结算
        // marker 与增量同事务落库
        $this->assertDatabaseHas('v2_traffic_settle_marker', ['token' => 'ts_test']);
    }

    public function test_duplicate_marker_skips_settlement_and_cleans_bucket(): void
    {
        $this->seedUser(1, 100, 200);

        Redis::shouldReceive('exists')->with('traffic_reset_lock')->andReturn(0);
        // 残留 swap 桶（上轮 commit 后、删桶前崩溃的场景）
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('hgetall')->with('v2board_upload_traffic:swap')->andReturn(['1' => '500'])->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('get')->with('v2board_traffic_settle_token')->andReturn('ts_leftover')->once();
        // marker 主键冲突 → 判定已结算，仅清桶不重复计费
        DB::shouldReceive('beginTransaction')->once();
        $qb = Mockery::mock();
        $qb->shouldReceive('insert')->andThrow(
            new QueryException('sqlite_testing', 'insert into marker', [], new \Exception('Duplicate entry'))
        );
        DB::shouldReceive('table')->with('v2_traffic_settle_marker')->andReturn($qb)->once();
        DB::shouldReceive('rollBack')->once();
        Redis::shouldReceive('del')->with('v2board_upload_traffic:swap', 'v2board_download_traffic:swap', 'v2board_traffic_settle_token')->once();
        // handle 继续：无 live 数据且 swap 已清理 → 结束
        Redis::shouldReceive('exists')->with('v2board_upload_traffic')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();

        (new TrafficUpdate())->handle();

        $user1 = User::find(1);
        $this->assertSame(100, (int)$user1->u); // 未重复结算
        $this->assertSame(200, (int)$user1->d);
    }

    public function test_reset_lock_second_check_aborts_round_keeping_swap(): void
    {
        $this->seedUser(1, 100, 200);

        Redis::shouldReceive('exists')->with('traffic_reset_lock')->andReturn(0)->once();
        // 首轮残留检查（空，直接返回）
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_upload_traffic')->andReturn(1)->once();
        Redis::shouldReceive('rename')->with('v2board_upload_traffic', 'v2board_upload_traffic:swap')->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic')->andReturn(0)->once();
        Redis::shouldReceive('exists')->with('v2board_traffic_settle_token')->andReturn(0)->once();
        Redis::shouldReceive('setex')->with('v2board_traffic_settle_token', 86400, Mockery::type('string'))->once();
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(1)->once();
        Redis::shouldReceive('hgetall')->with('v2board_upload_traffic:swap')->andReturn(['1' => '777'])->once();
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0)->once();
        Redis::shouldReceive('get')->with('v2board_traffic_settle_token')->andReturn('ts_lock')->once();
        // 提交前二次检查：重置锁出现 → 回滚放弃本轮（真实 sqlite 事务，marker 随回滚不落库）
        Redis::shouldReceive('exists')->with('traffic_reset_lock')->andReturn(1)->once();

        (new TrafficUpdate())->handle();

        $user1 = User::find(1);
        $this->assertSame(100, (int)$user1->u); // 本轮放弃，未结算
        $this->assertDatabaseMissing('v2_traffic_settle_marker', ['token' => 'ts_lock']);
    }

    public function test_early_return_when_no_data(): void
    {
        Redis::shouldReceive('exists')->with('traffic_reset_lock')->andReturn(0);
        Redis::shouldReceive('exists')->with('v2board_upload_traffic:swap')->andReturn(0);
        Redis::shouldReceive('exists')->with('v2board_download_traffic:swap')->andReturn(0);
        Redis::shouldReceive('exists')->with('v2board_upload_traffic')->andReturn(0);
        Redis::shouldReceive('exists')->with('v2board_download_traffic')->andReturn(0);
        // 空桶路径无结算无写操作
        Redis::shouldReceive('rename')->never();
        Redis::shouldReceive('hgetall')->never();
        Redis::shouldReceive('del')->never();

        (new TrafficUpdate())->handle();
        $this->assertTrue(true);
    }
}
