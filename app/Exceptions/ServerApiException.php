<?php

namespace App\Exceptions;

use Exception;

/**
 * 节点 API 业务错误（token 错误、节点不存在等）
 * 由 Handler 渲染为 HTTP 200 + {"status":"fail","message":"..."}，
 * 与节点端（v2node 等）既有响应结构保持兼容，
 * 同时避免在控制器构造函数中 send()+exit 绕过中间件收尾与日志
 */
class ServerApiException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
