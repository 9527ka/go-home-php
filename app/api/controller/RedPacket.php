<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\RedPacket as RedPacketModel;
use app\common\model\RedPacketClaim;
use app\common\model\User;
use app\common\model\VipLevel;
use app\common\service\UserResource;
use app\common\service\WalletService;
use think\facade\Db;
use think\Response;

class RedPacket extends BaseApi
{
    /**
     * 发红包
     * POST /api/red-packet/send
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

        // 手动获取红包数据 + 用户信息，避免 with() 兼容问题
        $data = RedPacketModel::find($packet->id);
        if ($data) {
            $data = $data->toArray();
            $user = User::field('id,nickname,avatar')->find($data['user_id']);
            $data['user'] = $user ? $user->toArray() : null;
            if (is_array($data['user'])) {
                UserResource::attachVipSingle($data['user']);
            }
            self::attachSenderVipSkin($data);
        }

        return $this->success($data, '红包已发送');
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

        $packet = RedPacketModel::find($id);
        if (!$packet) {
            return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '红包不存在');
        }

        $data = $packet->toArray();

        // 手动查询发送者信息
        $sender = User::field('id,nickname,avatar')->find($packet->user_id);
        $data['user'] = $sender ? $sender->toArray() : null;

        // 查询领取记录
        $claims = RedPacketClaim::where('red_packet_id', $id)
            ->order('created_at', 'asc')
            ->select()
            ->toArray();

        // 为每条领取记录附加用户信息
        $userIds = array_column($claims, 'user_id');
        $users = [];
        if (!empty($userIds)) {
            $userList = User::field('id,nickname,avatar')
                ->whereIn('id', $userIds)
                ->select();
            foreach ($userList as $u) {
                $users[$u->id] = $u->toArray();
            }
        }

        $myClaim = null;
        $bestClaim = null;
        foreach ($claims as &$claim) {
            $claim['user'] = $users[$claim['user_id']] ?? null;
            if ($claim['user_id'] === $this->getUserId()) {
                $myClaim = $claim;
            }
            if (!$bestClaim || $claim['amount'] > $bestClaim['amount']) {
                $bestClaim = $claim;
            }
        }
        unset($claim);

        // 附加发送者 + 所有领取者 的 VIP
        if (isset($data['user']) && is_array($data['user'])) {
            UserResource::attachVipSingle($data['user']);
        }
        UserResource::attachVipInList($claims, 'user');
        if ($myClaim && isset($myClaim['user']) && is_array($myClaim['user'])) {
            UserResource::attachVipSingle($myClaim['user']);
        }
        self::attachSenderVipSkin($data);

        $data['claims'] = $claims;
        $data['my_claim'] = $myClaim;
        $data['best_user_id'] = $bestClaim ? $bestClaim['user_id'] : null;

        return $this->success($data);
    }

    /**
     * 根据红包 sender_vip_level 快照查询对应皮肤/动效配置
     * 附加到 $data 的 sender_skin_url / sender_effect_key 字段
     */
    protected static function attachSenderVipSkin(array &$data): void
    {
        $levelKey = (string)($data['sender_vip_level'] ?? 'normal');
        $level = VipLevel::findByKey($levelKey);
        $data['sender_skin_url']   = $level ? (string)$level->red_packet_skin_url   : '';
        $data['sender_effect_key'] = $level ? (string)$level->red_packet_effect_key : 'none';
    }
}
