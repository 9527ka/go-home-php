<?php
declare(strict_types=1);

namespace app\common\exception;

use app\common\enum\ErrorCode;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 全局异常处理器
 * 所有异常统一转换为 JSON 响应
 */
class ExceptionHandler extends Handle
{
    public function render($request, Throwable $e): Response
    {
        // 业务异常 — 正常业务逻辑错误
        if ($e instanceof BusinessException) {
            return $this->jsonResponse(
                $e->getErrorCode(),
                $e->getMessage(),
                $e->getData()
            );
        }

        // 参数校验异常
        if ($e instanceof ValidateException) {
            return $this->jsonResponse(
                ErrorCode::PARAM_VALIDATE_FAIL,
                $e->getError()
            );
        }

        // 数据未找到
        if ($e instanceof DataNotFoundException || $e instanceof ModelNotFoundException) {
            return $this->jsonResponse(
                ErrorCode::POST_NOT_FOUND,
                '数据不存在'
            );
        }

        // HTTP 异常
        if ($e instanceof HttpException) {
            return $this->jsonResponse(
                $e->getStatusCode(),
                $e->getMessage()
            );
        }

        // 未知异常 — 记录日志，对外隐藏细节
        $this->logError($e);

        // 开发环境返回详细信息，生产环境隐藏
        $message = app()->isDebug()
            ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
            : ErrorCode::getMessage(ErrorCode::SYSTEM_ERROR);

        return $this->jsonResponse(ErrorCode::SYSTEM_ERROR, $message);
    }

    /**
     * 统一 JSON 响应
     */
    protected function jsonResponse(int $code, $msg, $data = null): Response
    {
        $result = [
            'code'      => $code,
            'msg'       => is_array($msg) ? implode(', ', $msg) : (string)$msg,
            'data'      => $data,
            'timestamp' => time(),
        ];

        return json($result);
    }

    /**
     * 记录错误日志
     */
    protected function logError(Throwable $e): void
    {
        $log = sprintf(
            "[%s] %s in %s:%d\nTrace: %s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        trace($log, 'error');
    }
}
