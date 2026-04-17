<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\UserVip;
use app\common\model\VipLevel;
use app\common\model\VipOrder;
use app\common\service\VipService;
use think\Response;

class Vip extends BaseApi
{
    /**
     * VIP 等级列表（公开数值 + 特效键）
     * GET /api/vip/levels
     */
    public function levels(): Response
    {
        $list = [];
        foreach (VipLevel::listAll() as $lv) {
            $list[] = self::serializeLevel($lv);
        }
        return $this->success($list);
    }

    /**
     * 我的 VIP 状态
     * GET /api/vip/my
     */
    public function my(): Response
    {
        $userId = $this->getUserId();
        $info = VipService::getUserVipInfo($userId);
        return $this->success([
            'level'      => self::serializeLevel($info['level']),
            'expired_at' => $info['expired_at'],
            'is_active'  => $info['is_active'],
        ]);
    }

    /**
     * 购买 / 续费
     * POST /api/vip/purchase  { level_key }
     */
    public function purchase(): Response
    {
        $levelKey = (string)$this->request->post('level_key', '');
        if ($levelKey === '') {
            return $this->error(ErrorCode::PARAM_MISSING, '缺少等级参数');
        }
        $order = VipService::purchase($this->getUserId(), $levelKey);
        return $this->success([
            'order_id'       => $order->id,
            'level_key'      => $order->level_key,
            'price'          => (float)$order->price,
            'balance_after'  => (float)$order->balance_after,
            'new_expired_at' => $order->new_expired_at,
        ], '购买成功');
    }

    /**
     * 我的 VIP 购买记录
     * GET /api/vip/orders?page=
     */
    public function orders(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));
        $list = VipOrder::where('user_id', $this->getUserId())
            ->order('id', 'desc')
            ->paginate(['list_rows' => 20, 'page' => $page]);
        return $this->successPage([
            'list'      => $list->items(),
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }

    protected static function serializeLevel(VipLevel $lv): array
    {
        return [
            'level_key'             => $lv->level_key,
            'level_name'            => $lv->level_name,
            'level_order'           => (int)$lv->level_order,
            'price'                 => (float)$lv->price,
            'duration_days'         => (int)$lv->duration_days,
            'sign_bonus_rate'       => (float)$lv->sign_bonus_rate,
            'crit_prob_bonus'       => (float)$lv->crit_prob_bonus,
            'crit_max_multiple'     => (int)$lv->crit_max_multiple,
            'withdraw_fee_rate'     => (float)$lv->withdraw_fee_rate,
            'withdraw_daily_limit'  => (float)$lv->withdraw_daily_limit,
            'icon_url'              => (string)$lv->icon_url,
            'badge_effect_key'      => (string)$lv->badge_effect_key,
            'name_effect_key'       => (string)$lv->name_effect_key,
            'red_packet_skin_url'   => (string)$lv->red_packet_skin_url,
            'red_packet_effect_key' => (string)$lv->red_packet_effect_key,
        ];
    }
}
