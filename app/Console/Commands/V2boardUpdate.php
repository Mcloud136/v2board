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
                // 忽略 "already exists" 类错误，其他记录日志
                if (stripos($e->getMessage(), 'already exists') === false &&
                    stripos($e->getMessage(), 'Duplicate') === false) {
                    \Log::warning('SQL 执行警告: ' . $e->getMessage());
                    $errors++;
                }
            }
        }
        \Artisan::call('horizon:terminate');
        if ($errors > 0) {
            $this->warn("更新完毕，但有 {$errors} 条 SQL 执行出现警告，请检查日志。");
        } else {
            $this->info('更新完毕，队列服务已重启，你无需进行任何操作。');
        }
        return 0;
    }
}
