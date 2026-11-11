<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class V2boardUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v2board:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'v2board 更新';

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
        \Artisan::call('config:cache');
        DB::connection()->getPdo();
        $file = \File::get(base_path() . '/database/update.sql');
        if (!$file) {
            $this->error('数据库文件不存在');
            return 1;
        }
        $sql = str_replace("\n", "", $file);
        $sql = preg_split("/;(?=(?:[^']*'[^']*')*[^']*$)/", $sql);
        if (!is_array($sql)) {
            $this->error('数据库文件格式有误');
            return 1;
        }
        $this->info('正在导入数据库请稍等...');
        $errors = 0;
        foreach ($sql as $item) {
            $item = trim($item);
            if (empty($item)) continue;
            try {
                DB::statement($item);
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                // 忽略幂等错误（已存在/已删除/不适用的迁移）
                $ignored = [
                    'already exists',    // 表/列已存在
                    'Duplicate',         // 重复索引/列
                    "Can't DROP",        // 列/索引已删除
                    'Unknown column',    // 列已不存在
                    "doesn't exist in table", // 索引引用的列不存在
                    'Duplicate key name',
                ];
                $shouldIgnore = false;
                foreach ($ignored as $keyword) {
                    if (stripos($msg, $keyword) !== false) {
                        $shouldIgnore = true;
                        break;
                    }
                }
                if (!$shouldIgnore) {
                    \Log::warning('SQL 执行警告: ' . $msg);
                    $errors++;
                }
            }
        }

        // 重启队列（容错：Horizon 可能未运行）
        try {
            \Artisan::call('horizon:terminate');
            $this->info('队列服务已重启。');
        } catch (\Exception $e) {
            $this->warn('队列服务重启失败（可能未运行），请手动重启: php artisan horizon');
        }

        if ($errors > 0) {
            $this->warn("更新完毕，但有 {$errors} 条 SQL 执行出现警告，请检查日志: storage/logs/laravel.log");
        } else {
            $this->info('更新完毕！你无需进行任何操作。');
        }
        return 0;
    }
}
