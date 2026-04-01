<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\service\SignService;
use think\Response;

class Sign extends BaseApi
{
    /**
     * 执行签到
     * POST /api/sign
     */
    public function sign(): Response
    {
        $ip = $this->request->ip();
        $deviceId = $this->request->header('X-Device-Id');

        $result = SignService::doSign($this->getUserId(), $ip, $deviceId);

        return $this->success($result, '签到成功');
    }

    /**
     * 获取签到状态
     * GET /api/sign/status
     */
    public function status(): Response
    {
        $result = SignService::getStatus($this->getUserId());

        return $this->success($result);
    }
}
