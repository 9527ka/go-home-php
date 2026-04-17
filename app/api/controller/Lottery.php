<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\service\LotteryService;
use think\Response;

class Lottery extends BaseApi
{
    /**
     * 抽奖页信息
     * GET /api/lottery/info
     */
    public function info(): Response
    {
        $data = LotteryService::getInfo($this->getUserId());
        return $this->success($data);
    }

    /**
     * 抽一次
     * POST /api/lottery/draw
     */
    public function draw(): Response
    {
        $data = LotteryService::draw($this->getUserId());
        return $this->success($data, '抽奖完成');
    }

    /**
     * 我的抽奖记录
     * GET /api/lottery/logs?page=
     */
    public function logs(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));
        return $this->successPage(LotteryService::myLogs($this->getUserId(), $page));
    }

    /**
     * 全站近期大奖
     * GET /api/lottery/recent
     */
    public function recent(): Response
    {
        $limit = min(50, max(1, (int)$this->request->get('limit', 20)));
        return $this->success(['list' => LotteryService::recentBigPrizes($limit)]);
    }
}
