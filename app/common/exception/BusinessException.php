<?php
declare(strict_types=1);

namespace app\common\exception;

use app\common\enum\ErrorCode;
use think\Exception;

/**
 * 业务异常
 * 在任何地方抛出此异常，会被全局异常处理器捕获并返回统一格式
 */
class BusinessException extends Exception
{
    protected $errorCode;

    public function __construct(int $errorCode, string $message = '', $data = null)
    {
        $this->errorCode = $errorCode;

        if (empty($message)) {
            $message = ErrorCode::getMessage($errorCode);
        }

        parent::__construct($message, $errorCode);

        if ($data !== null) {
            $this->setData($data);
        }
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}
