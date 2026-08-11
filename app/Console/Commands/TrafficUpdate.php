<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TrafficUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'traffic:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '流量更新任务';

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
        if (Redis::exists('traffic_reset_lock')) {
            return;
        }

        $uploadSwap = 'v2board_upload_traffic:swap';
        $downloadSwap = 'v2board_download_traffic:swap';
        $tokenKey = 'v2board_traffic_settle_token';

        // 先结算上轮崩溃残留的 swap 桶（结算令牌 + DB marker 保证 exactly-once）
        $this->settleSwaps($uploadSwap, $downloadSwap, $tokenKey);

        // RENAME 换桶：原子切换读写，消除 hgetall->del 竞态丢流量
        $renamed = false;
        if (Redis::exists('v2board_upload_traffic')) {
            try {
                Redis::rename('v2board_upload_traffic', $uploadSwap);
                $renamed = true;
            } catch (\Throwable $e) {
                // 源键在 exists 后被清空等竞态情形，留待下一轮处理
            }
        }
        if (Redis::exists('v2board_download_traffic')) {
            try {
                Redis::rename('v2board_download_traffic', $downloadSwap);
                $renamed = true;
            } catch (\Throwable $e) {
                // 同上
            }
        }
        if (!$renamed && !Redis::exists($uploadSwap) && !Redis::exists($downloadSwap)) {
            return;
        }

        // 为本轮生成结算令牌（标识唯一结算批次，与 DB marker 配对防重复结算；TTL 防泄漏兜底）
        if (!Redis::exists($tokenKey)) {
            Redis::setex($tokenKey, 86400, uniqid('ts_', true));
        }

        $this->settleSwaps($uploadSwap, $downloadSwap, $tokenKey);
    }

    /**
     * 结算 swap 桶：marker 行与流量增量在同一 DB 事务提交 → exactly-once
     * - 提交后、删桶前崩溃：下轮 marker 冲突 → 跳过结算仅清桶，不重复计费
     * - 提交前崩溃：事务回滚无 marker，下轮重结算，不丢数据
     */
    private function settleSwaps(string $uploadSwap, string $downloadSwap, string $tokenKey): void
    {
        $uploads = Redis::exists($uploadSwap) ? (Redis::hgetall($uploadSwap) ?: []) : [];
        $downloads = Redis::exists($downloadSwap) ? (Redis::hgetall($downloadSwap) ?: []) : [];
        if (empty($uploads) && empty($downloads)) {
            // 空桶直接清理（del 不存在的键无害）
            Redis::del($uploadSwap, $downloadSwap, $tokenKey);
            return;
        }

        $token = Redis::get($tokenKey);
        if (!$token) {
            // rename 后未写入令牌即崩溃的残留：此时必定未结算，补令牌后结算
            $token = uniqid('ts_', true);
            Redis::setex($tokenKey, 86400, $token);
        }

        // 结算用户集取上传/下载并集，修复仅有上行流量的用户不被结算
        $userIds = array_unique(array_merge(array_keys($uploads), array_keys($downloads)));
        $users = User::whereIn('id', $userIds)->get(['id']);
        $time = time();

        try {
            DB::beginTransaction();
            // marker 与流量增量同事务提交：重复提交会因主键冲突回滚，保证 exactly-once
            DB::table('v2_traffic_settle_marker')->insert([
                'token' => $token,
                'settled_at' => $time,
            ]);
            foreach ($users as $user) {
                $upload = (int)($uploads[$user->id] ?? 0);
                $download = (int)($downloads[$user->id] ?? 0);
                if ($upload === 0 && $download === 0) {
                    continue;
                }
                // 使用 DB::raw 原子更新，避免并发读写不一致
                User::where('id', $user->id)->update([
                    'u' => DB::raw('u + ' . $upload),
                    'd' => DB::raw('d + ' . $download),
                    't' => $time,
                    'updated_at' => $time,
                ]);
            }
            // 提交前二次检查重置锁：重置窗口内放弃本轮，swap 桶保留由下一轮结算
            if (Redis::exists('traffic_reset_lock')) {
                DB::rollBack();
                return;
            }
            DB::commit();
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // marker 主键冲突 = 该批已结算过（崩溃恢复场景），跳过结算仅清桶
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                Redis::del($uploadSwap, $downloadSwap, $tokenKey);
                return;
            }
            // 落库失败保留 swap 桶，下一轮调度依 marker 机制追回，不丢流量
            \Log::error('流量更新失败: ' . $e->getMessage());
            return;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('流量更新失败: ' . $e->getMessage());
            return;
        }

        // 提交成功后才清理 swap 桶与令牌
        Redis::del($uploadSwap, $downloadSwap, $tokenKey);
    }
}
