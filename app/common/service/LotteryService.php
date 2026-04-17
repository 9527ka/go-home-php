<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\enum\WalletTransactionType;
use app\common\exception\BusinessException;
use app\common\model\LotteryLog;
use app\common\model\LotteryPool;
use app\common\model\LotteryPrize;
use app\common\model\RechargeOrder;
use app\common\model\Wallet;
use app\common\model\WalletTransaction;
use think\facade\Cache;
use think\facade\Db;

class LotteryService
{
    const DEFAULT_POOL_KEY = 'main';

    /**
     * 抽奖页信息（奖池配置 + 奖品列表 + 我今日剩余次数）
     */
    public static function getInfo(int $userId, string $poolKey = self::DEFAULT_POOL_KEY): array
    {
        $pool = self::getPool($poolKey);
        $prizes = LotteryPrize::where('pool_id', $pool->id)
            ->where('is_enabled', 1)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();

        $todayCount = $userId > 0
            ? LotteryLog::where('user_id', $userId)
                ->where('pool_id', $pool->id)
                ->whereDay('created_at')
                ->count()
            : 0;

        return [
            'pool'             => self::serializePool($pool),
            'prizes'           => $prizes,
            'today_draw_count' => (int)$todayCount,
            'today_remaining'  => max(0, (int)$pool->daily_draw_limit - (int)$todayCount),
        ];
    }

    /**
     * 抽一次
     * @return array 含 log / prize / balance_after
     */
    public static function draw(int $userId, string $poolKey = self::DEFAULT_POOL_KEY): array
    {
        $pool = self::getPool($poolKey);
        if (!$pool->is_enabled) {
            throw new BusinessException(ErrorCode::LOTTERY_DISABLED);
        }

        // 频率锁（连抽最小间隔）
        // 默认 file 驱动无原子 NX，使用 has + set 近似，偶发误放行可接受
        $rateLockKey = "lottery:rate:{$userId}";
        $rateSec = max(1, (int)$pool->rate_limit_seconds);
        if (Cache::has($rateLockKey)) {
            throw new BusinessException(ErrorCode::LOTTERY_TOO_FREQUENT);
        }
        Cache::set($rateLockKey, 1, $rateSec);

        // 每日上限
        $today = LotteryLog::where('user_id', $userId)
            ->where('pool_id', $pool->id)
            ->whereDay('created_at')
            ->count();
        if ($today >= (int)$pool->daily_draw_limit) {
            throw new BusinessException(ErrorCode::LOTTERY_DAILY_LIMIT);
        }

        $prizes = LotteryPrize::where('pool_id', $pool->id)
            ->where('is_enabled', 1)
            ->select()
            ->toArray();
        if (empty($prizes)) {
            throw new BusinessException(ErrorCode::LOTTERY_NO_PRIZE);
        }

        // 钱包惰性初始化：必须在事务外，否则失败路径回滚会丢失 wallet 记录
        WalletService::getOrCreateWallet($userId);

        // 非充值用户：大奖档位权重降权 (B3: × 0.3)
        $isRecharged = RechargeOrder::where('user_id', $userId)
            ->where('status', RechargeOrder::STATUS_APPROVED)
            ->count() > 0;

        $bigThreshold = (float)$pool->big_prize_threshold;
        $nonRechargedWeight = (float)$pool->non_recharged_big_prize_weight;

        $adjustedPrizes = [];
        foreach ($prizes as $p) {
            $w = (int)$p['weight'];
            if (!$isRecharged && (float)$p['reward_amount'] >= $bigThreshold) {
                $w = max(1, (int)round($w * $nonRechargedWeight));
            }
            if ($w <= 0) continue;
            $adjustedPrizes[] = [
                'id'            => (int)$p['id'],
                'name'          => (string)$p['name'],
                'reward_amount' => (float)$p['reward_amount'],
                'weight'        => $w,
                'rarity'        => (int)($p['rarity'] ?? 0),
            ];
        }
        if (empty($adjustedPrizes)) {
            throw new BusinessException(ErrorCode::LOTTERY_NO_PRIZE);
        }

        // 加权随机
        $totalWeight = 0;
        foreach ($adjustedPrizes as $p) $totalWeight += $p['weight'];
        $roll = mt_rand(1, $totalWeight);
        $acc = 0;
        $picked = null;
        foreach ($adjustedPrizes as $p) {
            $acc += $p['weight'];
            if ($roll <= $acc) {
                $picked = $p;
                break;
            }
        }
        if ($picked === null) $picked = $adjustedPrizes[0];

        $cost = (float)$pool->cost_per_draw;
        $reward = (float)$picked['reward_amount'];
        $isBig = $reward >= $bigThreshold;

        return Db::transaction(function () use ($userId, $pool, $picked, $cost, $reward, $isBig, $isRecharged) {
            // 扣费（乐观锁）
            $affected = Db::table('wallets')
                ->where('user_id', $userId)
                ->where('balance', '>=', $cost)
                ->update([
                    'balance'    => Db::raw("balance - {$cost}"),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if ($affected === 0) {
                throw new BusinessException(ErrorCode::WALLET_INSUFFICIENT);
            }

            $wallet = Wallet::where('user_id', $userId)->find();
            $balanceAfterCost = (float)$wallet->balance;
            $balanceBeforeCost = bcadd((string)$balanceAfterCost, (string)$cost, 2);

            // 扣费流水
            WalletTransaction::create([
                'user_id'        => $userId,
                'type'           => WalletTransactionType::LOTTERY_COST,
                'amount'         => $cost,
                'balance_before' => $balanceBeforeCost,
                'balance_after'  => $balanceAfterCost,
                'related_id'     => null,
                'remark'         => "抽奖消耗({$pool->name})",
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            // 中奖（即使 0 也写入 log，但不加余额 / 不开流水）
            if ($reward > 0) {
                Db::table('wallets')
                    ->where('user_id', $userId)
                    ->update([
                        'balance'    => Db::raw("balance + {$reward}"),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                $finalBalance = (float)Db::table('wallets')->where('user_id', $userId)->value('balance');

                WalletTransaction::create([
                    'user_id'        => $userId,
                    'type'           => WalletTransactionType::LOTTERY_REWARD,
                    'amount'         => $reward,
                    'balance_before' => bcsub((string)$finalBalance, (string)$reward, 2),
                    'balance_after'  => $finalBalance,
                    'related_id'     => null,
                    'remark'         => "抽奖中奖:{$picked['name']}",
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            } else {
                $finalBalance = $balanceAfterCost;
            }

            // 写 log
            $log = LotteryLog::create([
                'user_id'           => $userId,
                'pool_id'           => $pool->id,
                'prize_id'          => $picked['id'],
                'prize_name'        => $picked['name'],
                'cost'              => $cost,
                'reward_amount'     => $reward,
                'is_big_prize'      => $isBig ? 1 : 0,
                'is_recharged_user' => $isRecharged ? 1 : 0,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);

            // 更新本次抽奖流水 related_id
            Db::table('wallet_transactions')
                ->where('user_id', $userId)
                ->whereIn('type', [WalletTransactionType::LOTTERY_COST, WalletTransactionType::LOTTERY_REWARD])
                ->where('related_id', null)
                ->order('id', 'desc')
                ->limit($reward > 0 ? 2 : 1)
                ->update(['related_id' => $log->id]);

            return [
                'log_id'          => $log->id,
                'prize_id'        => $picked['id'],
                'prize_name'      => $picked['name'],
                'reward_amount'   => $reward,
                'rarity'          => $picked['rarity'],
                'is_big_prize'    => $isBig,
                'cost'            => $cost,
                'balance_after'   => $finalBalance,
            ];
        });
    }

    /**
     * 我的抽奖记录
     */
    public static function myLogs(int $userId, int $page = 1): array
    {
        $list = LotteryLog::where('user_id', $userId)
            ->order('id', 'desc')
            ->paginate(['list_rows' => 20, 'page' => $page]);
        return [
            'list'      => $list->items(),
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ];
    }

    /**
     * 全站最近大奖（炫耀墙）
     */
    public static function recentBigPrizes(int $limit = 20): array
    {
        $rows = LotteryLog::with(['user'])
            ->where('is_big_prize', 1)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
        UserResource::attachVipInList($rows, 'user');
        return $rows;
    }

    protected static function getPool(string $poolKey): LotteryPool
    {
        $pool = LotteryPool::where('pool_key', $poolKey)->find();
        if (!$pool) {
            throw new BusinessException(ErrorCode::LOTTERY_POOL_NOT_FOUND);
        }
        return $pool;
    }

    protected static function serializePool(LotteryPool $pool): array
    {
        return [
            'id'                  => $pool->id,
            'pool_key'            => $pool->pool_key,
            'name'                => $pool->name,
            'cost_per_draw'       => (float)$pool->cost_per_draw,
            'daily_draw_limit'    => (int)$pool->daily_draw_limit,
            'rate_limit_seconds'  => (int)$pool->rate_limit_seconds,
            'big_prize_threshold' => (float)$pool->big_prize_threshold,
            'is_enabled'          => (bool)$pool->is_enabled,
        ];
    }
}
