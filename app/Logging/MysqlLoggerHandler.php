<?php
namespace App\Logging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use App\Models\Log as LogModel;

class MysqlLoggerHandler extends AbstractProcessingHandler
{
    public function __construct($level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try{
            $isConsole = app()->runningInConsole();
            $context = $record->context;
            if(isset($context['exception']) && is_object($context['exception'])){
                $context['exception'] = (array) $context['exception'];
            }
            $requestData = $isConsole ? [] : (request()->all() ?? []);
            $host = $isConsole ? null : request()->getSchemeAndHttpHost();
            $uri = $isConsole ? 'console' : request()->getRequestUri();
            $method = $isConsole ? 'CLI' : request()->getMethod();
            $ip = $isConsole ? null : request()->getClientIp();
            $log = [
                'title' => $record->message,
                'level' => $record->level->getName(),
                'host' => $host,
                'uri' => $uri,
                'method' => $method,
                'ip' => $ip,
                'data' => json_encode($requestData),
                'context' => !empty($context) ? json_encode($context) : '',
                'created_at' => $record->datetime->getTimestamp(),
                'updated_at' => $record->datetime->getTimestamp(),
            ];

            LogModel::insert(
                $log
            );
        }catch (\Throwable $e){
            Log::channel('daily')->error($e->getMessage().$e->getFile().$e->getTraceAsString());
        }
    }
}
