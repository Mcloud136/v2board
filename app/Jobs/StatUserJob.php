<?php

namespace App\Jobs;

use App\Models\StatUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StatUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $data;
    protected $server;
    protected $protocol;
    protected $recordType;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [5, 15, 30];

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data, array $server, $protocol, $recordType = 'd')
    {
        $this->onQueue('stat');
        $this->data =$data;
        $this->server = $server;
        $this->protocol = $protocol;
        $this->recordType = $recordType;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 统计口径：报表记录原始流量（不乘节点倍率 rate）；
        // 用户流量余额结算在 TrafficFetchJob/TrafficUpdate 中按 rate 计算，两者口径不同属设计意图
        $recordAt = strtotime(date('Y-m-d'));

        $existingData = StatUser::where('record_at', $recordAt)
            ->where('server_rate', $this->server['rate'])
            ->whereIn('user_id', array_keys($this->data))
            ->select(['user_id', 'id', 'u', 'd'])
            ->get()
            ->keyBy('user_id');

        $insertData = [];
        DB::beginTransaction();
        try {
            foreach ($this->data as $userId => $trafficData) {
                if (isset($existingData[$userId])) {
                    StatUser::where('id', $existingData[$userId]['id'])->update([
                        'u' => DB::raw('u + ' . (int)$trafficData[0]),
                        'd' => DB::raw('d + ' . (int)$trafficData[1])
                    ]);
                } else {
                    $insertData[] = [
                        'user_id' => $userId,
                        'server_rate' => $this->server['rate'],
                        'u' => $trafficData[0],
                        'd' => $trafficData[1],
                        'record_type' => $this->recordType,
                        'record_at' => $recordAt
                    ];
                }
            }
            if (!empty($insertData)) {
                collect($insertData)->chunk(500)->each(function ($chunk) {
                    StatUser::upsert($chunk->toArray(), ['user_id', 'server_rate', 'record_at']);
                });
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception)
    {
        \Log::error('StatUserJob permanently failed: ' . $exception->getMessage(), [
            'server' => $this->server,
            'data_count' => count($this->data)
        ]);
    }
}
