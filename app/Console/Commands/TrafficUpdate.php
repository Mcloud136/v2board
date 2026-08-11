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

        $pairs = [
            ['v2board_upload_traffic', 'v2board_upload_traffic:swap'],
            ['v2board_download_traffic', 'v2board_download_traffic:swap'],
        ];

        // RENAME 换桶：原子切换读写，消除 hgetall->del 竞态丢流量
        foreach ($pairs as [$live, $swap]) {
            // 上轮崩溃残留的 swap 桶优先合并，保证数据不丢不重
            if (Redis::exists($swap)) {
                if (Redis::exists($live)) {
                    foreach (Redis::hgetall($live) ?: [] as $field => $value) {
                        Redis::hincrby($swap, $field, (int)$value);
                    }
                    Redis::del($live);
                }
                continue;
            }
            if (!Redis::exists($live)) {
                continue;
            }
            try {
                Redis::rename($live, $swap);
            } catch (\Throwable $e) {
                // 源键在 exists 后被清空等竞态情形，留待下一轮处理
                continue;
            }
        }

        $uploads = Redis::exists($pairs[0][1]) ? (Redis::hgetall($pairs[0][1]) ?: []) : [];
        $downloads = Redis::exists($pairs[1][1]) ? (Redis::hgetall($pairs[1][1]) ?: []) : [];
        if (empty($uploads) && empty($downloads)) {
            return;
        }

        // 结算用户集取上传/下载并集，修复仅有上行流量的用户不被结算
        $userIds = array_unique(array_merge(array_keys($uploads), array_keys($downloads)));
        $users = User::whereIn('id', $userIds)->get(['id', 'u', 'd']);
        $time = time();

        try {
            DB::beginTransaction();
            foreach ($users as $user) {
                $upload = (int)($uploads[$user->id] ?? 0);
                $download = (int)($downloads[$user->id] ?? 0);
                // 使用 DB::raw 原子更新，避免并发读写不一致
                User::where('id', $user->id)->update([
                    'u' => DB::raw('u + ' . $upload),
                    'd' => DB::raw('d + ' . $download),
                    't' => $time,
                    'updated_at' => $time,
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // 落库失败保留 swap 桶，下一轮调度优先 drain 追回，不丢流量
            \Log::error('流量更新失败: ' . $e->getMessage());
            return;
        }

        // 落库成功后才清理 swap 桶
        foreach ($pairs as [, $swap]) {
            if (Redis::exists($swap)) {
                Redis::del($swap);
            }
        }
    }
}
