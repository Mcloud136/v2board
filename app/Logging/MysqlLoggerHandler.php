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
            $context = $record->context;
            if(isset($context['exception']) && is_object($context['exception'])){
                $context['exception'] = (array)$context['exception'];
            }
            $requestData = request()->all() ??[];
            $log = [
                'title' => $record->message,
                'level' => $record->level->getName(),
                'host' => $requestData['request_host'] ?? request()->getSchemeAndHttpHost(),
                'uri' => $requestData['request_uri'] ?? request()->getRequestUri(),
                'method' => $requestData['request_method'] ?? request()->getMethod(),
                'ip' => request()->getClientIp(),
                'data' => json_encode($requestData),
                'context' => !empty($context) ? json_encode($context) : '',
                'created_at' => $record->datetime->getTimestamp(),
                'updated_at' => $record->datetime->getTimestamp(),
            ];

            LogModel::insert(
                $log
            );
        }catch (\Exception $e){
            Log::channel('daily')->error($e->getMessage().$e->getFile().$e->getTraceAsString());
        }
    }
}
