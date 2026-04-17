<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\model\AdminAuditLog;
use app\common\model\UserVip;
use app\common\model\VipLevel;
use app\common\model\VipOrder;
use app\common\service\VipService;
use think\Request;
use think\Response;

class VipManage
{
    /**
     * VIP 等级列表（含禁用）
     * GET /admin/vip/levels
     */
    public function levels(Request $request): Response
    {
        $rows = VipLevel::order('sort_order', 'asc')->select()->toArray();
        return json(['code' => 0, 'msg' => 'ok', 'data' => $rows]);
    }

    /**
     * 更新 VIP 等级配置
     * POST /admin/vip/level/update  {id, ...字段}
     */
    public function updateLevel(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        $lv = VipLevel::find($id);
        if (!$lv) {
            return json(['code' => ErrorCode::VIP_LEVEL_NOT_FOUND, 'msg' => 'VIP 等级不存在']);
        }

        // 可编辑字段白名单（level_key / level_order 不允许改）+ 类型
        $editable = [
            'level_name'             => 'string',
            'price'                  => 'float',
            'duration_days'          => 'int',
            'sign_bonus_rate'        => 'float',
            'crit_prob_bonus'        => 'float',
            'crit_max_multiple'      => 'int',
            'withdraw_fee_rate'      => 'float',
            'withdraw_daily_limit'   => 'float',
            'icon_url'               => 'string',
            'badge_effect_key'       => 'string',
            'name_effect_key'        => 'string',
            'red_packet_skin_url'    => 'string',
            'red_packet_effect_key'  => 'string',
            'is_enabled'             => 'int',
            'sort_order'             => 'int',
        ];

        $changes = [];
        foreach ($editable as $field => $type) {
            $val = $request->post($field);
            if ($val === null) continue;
            $casted = match ($type) {
                'int'    => (int)$val,
                'float'  => (float)$val,
                default  => (string)$val,
            };
            $lv->$field = $casted;
            $changes[$field] = $casted;
        }
        $lv->save();
        VipLevel::flushCache();

        AdminAuditLog::log(
            $request->adminId,
            'vip_level_update',
            'vip_level',
            $lv->id,
            json_encode(['level_key' => $lv->level_key, 'changes' => $changes], JSON_UNESCAPED_UNICODE),
            $request->ip()
        );

        return json(['code' => 0, 'msg' => '更新成功']);
    }

    /**
     * 购买订单列表
     * GET /admin/vip/orders?page=&user_id=&level_key=
     */
    public function orders(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));
        $userId = $request->get('user_id');
        $levelKey = $request->get('level_key');

        $query = VipOrder::with(['user'])->order('id', 'desc');
        if (!empty($userId)) $query->where('user_id', (int)$userId);
        if (!empty($levelKey)) $query->where('level_key', $levelKey);

        $list = $query->paginate(['list_rows' => 20, 'page' => $page]);
        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    /**
     * 用户 VIP 状态列表
     * GET /admin/vip/users?page=&user_id=
     */
    public function users(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));
        $userId = $request->get('user_id');

        $query = UserVip::alias('uv')
            ->leftJoin('users u', 'u.id = uv.user_id')
            ->field('uv.*, u.nickname, u.avatar, u.user_code')
            ->order('uv.expired_at', 'desc');
        if (!empty($userId)) $query->where('uv.user_id', (int)$userId);

        $list = $query->paginate(['list_rows' => 20, 'page' => $page]);
        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    /**
     * 手动授予/调整用户 VIP
     * POST /admin/vip/user/grant  {user_id, level_key, expired_at?}
     */
    public function grantUser(Request $request): Response
    {
        $userId = (int)$request->post('user_id', 0);
        $levelKey = (string)$request->post('level_key', '');
        $expiredAt = $request->post('expired_at');

        if ($userId <= 0 || $levelKey === '') {
            return json(['code' => ErrorCode::PARAM_MISSING, 'msg' => '参数缺失']);
        }

        VipService::adminGrant($userId, $levelKey, $expiredAt ?: null);

        AdminAuditLog::log(
            $request->adminId,
            'vip_grant',
            'user',
            $userId,
            json_encode(['level_key' => $levelKey, 'expired_at' => $expiredAt], JSON_UNESCAPED_UNICODE),
            $request->ip()
        );

        return json(['code' => 0, 'msg' => '授予成功']);
    }
}
