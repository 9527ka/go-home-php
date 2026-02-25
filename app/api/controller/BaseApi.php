<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use think\App;
use think\Request;
use think\Response;

/**
 * API 控制器基类
 */
class BaseApi
{
    protected App $app;
    protected Request $request;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
    }

    /**
     * 获取当前登录用户ID
     */
    protected function getUserId(): int
    {
        return (int)($this->request->userId ?? 0);
    }

    /**
     * 成功响应
     */
    protected function success($data = null, string $msg = '操作成功'): Response
    {
        return json([
            'code'      => ErrorCode::SUCCESS,
            'msg'       => $msg,
            'data'      => $data,
            'timestamp' => time(),
        ]);
    }

    /**
     * 分页成功响应
     */
    protected function successPage(array $pageData, string $msg = '操作成功'): Response
    {
        return json([
            'code'      => ErrorCode::SUCCESS,
            'msg'       => $msg,
            'data'      => $pageData,
            'timestamp' => time(),
        ]);
    }

    /**
     * 失败响应
     */
    protected function error(int $code, string $msg = ''): Response
    {
        return json([
            'code'      => $code,
            'msg'       => $msg ?: ErrorCode::getMessage($code),
            'data'      => null,
            'timestamp' => time(),
        ]);
    }

    /**
     * 获取客户端语言
     */
    protected function getLang(): string
    {
        return $this->request->header('X-Lang', 'zh-CN');
    }
}
