<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\RedPacket as RedPacketModel;
use app\common\model\RedPacketClaim;
use app\common\service\WalletService;
use think\Response;

class RedPacket extends BaseApi
{
    /**
     * 发红包
     * POST /api/red-packet/send
     * {target_type, target_id, total_amount, total_count, greeting?}
     */
    public function send(): Response
    {
        $targetType  = (int)$this->request->post('target_type', 0);
        $targetId    = (int)$this->request->post('target_id', 0);
        $totalAmount = (float)$this->request->post('total_amount', 0);
        $totalCount  = (int)$this->request->post('total_count', 0);
        $greeting    = trim((string)$this->request->post('greeting', ''));

        if (!in_array($targetType, [RedPacketModel::TARGET_PUBLIC, RedPacketModel::TARGET_PRIVATE, RedPacketModel::TARGET_GROUP])) {
            return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '无效的目标类型');
        }

        if ($totalAmount <= 0 || $totalCount <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $packet = WalletService::sendRedPacket(
            $this->getUserId(),
            $targetType,
            $targetId,
            $totalAmount,
            $totalCount,
            $greeting
        );

        // 返回红包信息，前端用于发送 WebSocket 消息
        $packet->load(['user']);

        return $this->success($packet, '红包已发送');
    }

    /**
     * 抢红包
     * POST /api/red-packet/claim  {red_packet_id}
     */
    public function claim(): Response
    {
        $redPacketId = (int)$this->request->post('red_packet_id', 0);

        if ($redPacketId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $claim = WalletService::claimRedPacket($this->getUserId(), $redPacketId);

        return $this->success([
            'amount' => (float)$claim->amount,
        ], '领取成功');
    }

    /**
     * 红包详情
     * GET /api/red-packet/detail?id=
     */
    public function detail(): Response
    {
        $id = (int)$this->request->get('id', 0);

        $packet = RedPacketModel::with(['user', 'claims.user'])->find($id);
        if (!$packet) {
            return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '红包不存在');
        }

        // 查看当前用户是否已领取
        $myClaim = RedPacketClaim::where('red_packet_id', $id)
            ->where('user_id', $this->getUserId())
            ->find();

        // 找出手气最佳
        $bestClaim = RedPacketClaim::where('red_packet_id', $id)
            ->order('amount', 'desc')
            ->find();

        $data = $packet->toArray();
        $data['my_claim'] = $myClaim;
        $data['best_user_id'] = $bestClaim ? $bestClaim->user_id : null;

        return $this->success($data);
    }
}
