<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\model\AdminAuditLog;
use app\common\model\LotteryLog;
use app\common\model\LotteryPool;
use app\common\model\LotteryPrize;
use think\Request;
use think\Response;

class LotteryManage
{
    /**
     * 奖池列表（含实时期望值）
     * GET /admin/lottery/pools
     */
    public function pools(Request $request): Response
    {
        $pools = LotteryPool::order('id', 'asc')->select()->toArray();
        foreach ($pools as &$p) {
            $p['expected_return'] = self::calcExpectedReturn((int)$p['id'], (float)$p['cost_per_draw']);
        }
        unset($p);
        return json(['code' => 0, 'msg' => 'ok', 'data' => $pools]);
    }

    /**
     * 更新奖池配置
     * POST /admin/lottery/pool/update
     */
    public function updatePool(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        $pool = LotteryPool::find($id);
        if (!$pool) {
            return json(['code' => ErrorCode::LOTTERY_POOL_NOT_FOUND, 'msg' => '奖池不存在']);
        }
        $editable = [
            'name'                            => 'string',
            'cost_per_draw'                   => 'float',
            'daily_draw_limit'                => 'int',
            'rate_limit_seconds'              => 'int',
            'big_prize_threshold'             => 'float',
            'non_recharged_big_prize_weight'  => 'float',
            'is_enabled'                      => 'int',
        ];
        $changes = [];
        foreach ($editable as $f => $type) {
            $v = $request->post($f);
            if ($v === null) continue;
            $casted = match ($type) {
                'int'    => (int)$v,
                'float'  => (float)$v,
                default  => (string)$v,
            };
            $pool->$f = $casted;
            $changes[$f] = $casted;
        }
        $pool->save();
        AdminAuditLog::log($request->adminId, 'lottery_pool_update', 'lottery_pool', $pool->id,
            json_encode($changes, JSON_UNESCAPED_UNICODE), $request->ip());
        return json(['code' => 0, 'msg' => '更新成功']);
    }

    /**
     * 某奖池的奖品列表
     * GET /admin/lottery/prizes?pool_id=
     */
    public function prizes(Request $request): Response
    {
        $poolId = (int)$request->get('pool_id', 1);
        $list = LotteryPrize::where('pool_id', $poolId)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();
        $pool = LotteryPool::find($poolId);
        return json([
            'code' => 0,
            'msg'  => 'ok',
            'data' => [
                'list'            => $list,
                'expected_return' => $pool ? self::calcExpectedReturn($poolId, (float)$pool->cost_per_draw) : 0,
            ],
        ]);
    }

    /**
     * 新增奖品
     * POST /admin/lottery/prize/create
     */
    public function createPrize(Request $request): Response
    {
        $poolId = (int)$request->post('pool_id', 0);
        if ($poolId <= 0) return json(['code' => ErrorCode::PARAM_MISSING, 'msg' => '缺少奖池ID']);

        $prize = LotteryPrize::create([
            'pool_id'       => $poolId,
            'name'          => (string)$request->post('name', '新奖品'),
            'reward_amount' => (float)$request->post('reward_amount', 0),
            'weight'        => max(1, (int)$request->post('weight', 100)),
            'rarity'        => (int)$request->post('rarity', 0),
            'icon_url'      => (string)$request->post('icon_url', ''),
            'sort_order'    => (int)$request->post('sort_order', 0),
            'is_enabled'    => (int)$request->post('is_enabled', 1),
        ]);

        AdminAuditLog::log($request->adminId, 'lottery_prize_create', 'lottery_prize', $prize->id,
            json_encode($prize->toArray(), JSON_UNESCAPED_UNICODE), $request->ip());
        return json(['code' => 0, 'msg' => '已创建', 'data' => $prize]);
    }

    /**
     * 更新奖品
     * POST /admin/lottery/prize/update
     */
    public function updatePrize(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        $prize = LotteryPrize::find($id);
        if (!$prize) return json(['code' => ErrorCode::PARAM_FORMAT_ERROR, 'msg' => '奖品不存在']);

        $editable = [
            'name'          => 'string',
            'reward_amount' => 'float',
            'weight'        => 'int',
            'rarity'        => 'int',
            'icon_url'      => 'string',
            'sort_order'    => 'int',
            'is_enabled'    => 'int',
        ];
        foreach ($editable as $f => $type) {
            $v = $request->post($f);
            if ($v === null) continue;
            $prize->$f = match ($type) {
                'int'    => (int)$v,
                'float'  => (float)$v,
                default  => (string)$v,
            };
        }
        $prize->save();
        AdminAuditLog::log($request->adminId, 'lottery_prize_update', 'lottery_prize', $prize->id,
            json_encode($prize->toArray(), JSON_UNESCAPED_UNICODE), $request->ip());
        return json(['code' => 0, 'msg' => '已更新']);
    }

    /**
     * 删除奖品
     * POST /admin/lottery/prize/delete  {id}
     */
    public function deletePrize(Request $request): Response
    {
        $id = (int)$request->post('id', 0);
        LotteryPrize::destroy($id);
        AdminAuditLog::log($request->adminId, 'lottery_prize_delete', 'lottery_prize', $id, null, $request->ip());
        return json(['code' => 0, 'msg' => '已删除']);
    }

    /**
     * 抽奖流水
     * GET /admin/lottery/logs?page=&user_id=&is_big_prize=
     */
    public function logs(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));
        $userId = $request->get('user_id');
        $isBig = $request->get('is_big_prize');

        $query = LotteryLog::with(['user'])->order('id', 'desc');
        if (!empty($userId)) $query->where('user_id', (int)$userId);
        if ($isBig !== null && $isBig !== '') $query->where('is_big_prize', (int)$isBig);

        $list = $query->paginate(['list_rows' => 20, 'page' => $page]);
        return json(['code' => 0, 'msg' => 'ok', 'data' => $list->toArray()]);
    }

    /**
     * 统计面板（日/周/月中奖分布、平台净收入）
     * GET /admin/lottery/stats
     */
    public function stats(Request $request): Response
    {
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('-6 days'));
        $monthStart = date('Y-m-01');

        $dayCount   = LotteryLog::whereDay('created_at')->count();
        $dayCost    = (float)LotteryLog::whereDay('created_at')->sum('cost');
        $dayReward  = (float)LotteryLog::whereDay('created_at')->sum('reward_amount');

        $weekCount  = LotteryLog::whereTime('created_at', '>=', $weekStart)->count();
        $weekCost   = (float)LotteryLog::whereTime('created_at', '>=', $weekStart)->sum('cost');
        $weekReward = (float)LotteryLog::whereTime('created_at', '>=', $weekStart)->sum('reward_amount');

        $monthCount  = LotteryLog::whereTime('created_at', '>=', $monthStart)->count();
        $monthCost   = (float)LotteryLog::whereTime('created_at', '>=', $monthStart)->sum('cost');
        $monthReward = (float)LotteryLog::whereTime('created_at', '>=', $monthStart)->sum('reward_amount');

        return json([
            'code' => 0, 'msg' => 'ok',
            'data' => [
                'day'   => ['count' => $dayCount,   'cost' => $dayCost,   'reward' => $dayReward,   'net' => round($dayCost - $dayReward, 2)],
                'week'  => ['count' => $weekCount,  'cost' => $weekCost,  'reward' => $weekReward,  'net' => round($weekCost - $weekReward, 2)],
                'month' => ['count' => $monthCount, 'cost' => $monthCost, 'reward' => $monthReward, 'net' => round($monthCost - $monthReward, 2)],
            ],
        ]);
    }

    /**
     * 期望回报 = Σ(reward × weight) / Σ(weight) / cost
     */
    protected static function calcExpectedReturn(int $poolId, float $cost): float
    {
        if ($cost <= 0) return 0;
        $prizes = LotteryPrize::where('pool_id', $poolId)
            ->where('is_enabled', 1)
            ->select()
            ->toArray();
        $totalWeight = 0;
        $expectedReward = 0;
        foreach ($prizes as $p) {
            $w = (int)$p['weight'];
            $totalWeight += $w;
            $expectedReward += (float)$p['reward_amount'] * $w;
        }
        if ($totalWeight === 0) return 0;
        return round(($expectedReward / $totalWeight) / $cost, 4);
    }
}
