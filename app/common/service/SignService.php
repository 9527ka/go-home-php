<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\enum\WalletTransactionType;
use app\common\exception\BusinessException;
use app\common\model\SignLog;
use app\common\model\UserSignStatus;
use app\common\model\VipLevel;
use app\common\model\Wallet;
use app\common\model\WalletSetting;
use app\common\model\WalletTransaction;
use think\facade\Db;

class SignService
{
    /**
     * 检查签到功能是否开启
     */
    public static function checkEnabled(): void
    {
        if (WalletSetting::getValue('sign_enabled', '1') !== '1') {
            throw new BusinessException(ErrorCode::SIGN_DISABLED);
        }
    }

    /**
     * 执行签到
     *
     * @param int $userId 用户ID
     * @param string|null $ip 客户端IP（风控记录）
     * @param string|null $deviceId 设备ID（预留）
     * @return array 签到结果
     */
    public static function doSign(int $userId, ?string $ip = null, ?string $deviceId = null): array
    {
        self::checkEnabled();

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // 获取或创建签到状态
        $status = UserSignStatus::where('user_id', $userId)->find();
        if (!$status) {
            $status = UserSignStatus::create([
                'user_id'        => $userId,
                'current_streak' => 0,
                'last_sign_date' => null,
                'total_sign_days' => 0,
            ]);
        }

        // 防止重复签到
        if ($status->last_sign_date === $today) {
            throw new BusinessException(ErrorCode::SIGN_ALREADY_TODAY);
        }

        // 计算连续天数
        if ($status->last_sign_date === $yesterday && $status->current_streak > 0) {
            // 连续签到：在7天周期内递增，第7天后重置为第1天
            $newStreak = $status->current_streak >= 7 ? 1 : $status->current_streak + 1;
        } else {
            // 中断或首次签到，重置为第1天
            $newStreak = 1;
        }

        // 获取奖励配置
        $rewardsJson = WalletSetting::getValue('sign_rewards', '[0.1,0.2,0.3,0.5,0.8,1,2]');
        $rewards = json_decode($rewardsJson, true);
        if (!is_array($rewards) || count($rewards) < 7) {
            $rewards = [0.1, 0.2, 0.3, 0.5, 0.8, 1, 2];
        }

        // 基础奖励（数组下标从0开始，天数从1开始）
        $baseReward = (float)($rewards[$newStreak - 1] ?? $rewards[0]);

        // 取用户 VIP 等级快照（暴击上限 / 概率加成 / 奖励加成）
        $vip = VipService::getCurrentLevel($userId);

        // 暴击计算（含保底 + VIP 加成 + 上限裁切）
        $bonusRate = self::rollBonus($userId, $vip);

        // 最终奖励 = base × 暴击倍率 × (1 + VIP签到加成)
        $vipSignBonus = (float)$vip->sign_bonus_rate;
        $finalReward = round($baseReward * $bonusRate * (1 + $vipSignBonus), 2);

        // 事务内执行：插入日志、更新状态、发放冻结奖励
        $signLog = Db::transaction(function () use (
            $userId, $today, $newStreak, $baseReward, $bonusRate, $finalReward, $ip, $deviceId, $status
        ) {
            // 插入签到日志（唯一索引防并发重复）
            $signLog = SignLog::create([
                'user_id'      => $userId,
                'sign_date'    => $today,
                'day_in_cycle' => $newStreak,
                'base_reward'  => $baseReward,
                'bonus_rate'   => $bonusRate,
                'final_reward' => $finalReward,
                'ip'           => $ip,
                'device_id'    => $deviceId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            // 更新签到状态
            $status->current_streak  = $newStreak;
            $status->last_sign_date  = $today;
            $status->total_sign_days = $status->total_sign_days + 1;
            $status->save();

            // 发放奖励到冻结余额
            self::creditRewardFrozen(
                $userId,
                $finalReward,
                WalletTransactionType::SIGN_REWARD,
                $signLog->id,
                "签到奖励(第{$newStreak}天" . ($bonusRate > 1 ? ",{$bonusRate}倍暴击" : '') . ')'
            );

            return $signLog;
        });

        // 获取更新后的冻结余额
        $wallet = WalletService::getOrCreateWallet($userId);

        return [
            'reward'                => (float)$finalReward,
            'base_reward'           => (float)$baseReward,
            'bonus_rate'            => $bonusRate,
            'is_bonus'              => $bonusRate > 1,
            'vip_level_key'         => (string)$vip->level_key,
            'vip_sign_bonus_rate'   => (float)$vipSignBonus,
            'current_streak'        => $newStreak,
            'day_in_cycle'          => $newStreak,
            'total_sign_days'       => $status->total_sign_days,
            'reward_frozen_balance' => (float)$wallet->reward_frozen_balance,
        ];
    }

    /**
     * 获取签到状态
     */
    public static function getStatus(int $userId): array
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $status = UserSignStatus::where('user_id', $userId)->find();

        $signedToday = false;
        $currentStreak = 0;
        $dayInCycle = 0;
        $totalSignDays = 0;

        if ($status) {
            $signedToday = $status->last_sign_date === $today;
            $totalSignDays = (int)$status->total_sign_days;

            if ($signedToday) {
                // 今日已签到，显示当前连续天数
                $currentStreak = (int)$status->current_streak;
                $dayInCycle = $currentStreak;
            } elseif ($status->last_sign_date === $yesterday) {
                // 昨天签到了，连续中，今天待签到
                $currentStreak = (int)$status->current_streak;
                $dayInCycle = $currentStreak >= 7 ? 1 : $currentStreak + 1;
            } else {
                // 已中断，今天签到将从第1天开始
                $currentStreak = 0;
                $dayInCycle = 1;
            }
        } else {
            $dayInCycle = 1;
        }

        // 奖励配置
        $rewardsJson = WalletSetting::getValue('sign_rewards', '[0.1,0.2,0.3,0.5,0.8,1,2]');
        $rewards = json_decode($rewardsJson, true);
        if (!is_array($rewards) || count($rewards) < 7) {
            $rewards = [0.1, 0.2, 0.3, 0.5, 0.8, 1, 2];
        }

        // 本周期7天签到状态
        $weekStatus = [];
        if ($status && $status->last_sign_date) {
            // 查询本周期的签到记录
            $cycleStartDay = $signedToday ? $currentStreak : ($currentStreak >= 7 ? 0 : $currentStreak);
            if ($cycleStartDay > 0) {
                $startDate = date('Y-m-d', strtotime("-" . ($cycleStartDay - 1) . " days", strtotime($signedToday ? $today : $yesterday)));
                $logs = SignLog::where('user_id', $userId)
                    ->where('sign_date', '>=', $startDate)
                    ->where('sign_date', '<=', $today)
                    ->column('sign_date');
                for ($i = 0; $i < 7; $i++) {
                    $date = date('Y-m-d', strtotime($startDate . " +{$i} days"));
                    $weekStatus[] = in_array($date, $logs);
                }
            } else {
                $weekStatus = array_fill(0, 7, false);
            }
        } else {
            $weekStatus = array_fill(0, 7, false);
        }

        // 今天可获得的奖励（含 VIP 签到加成，不含暴击）
        $baseTodayReward = (float)($rewards[$dayInCycle - 1] ?? $rewards[0]);
        $vip = VipService::getCurrentLevel($userId);
        $vipSignBonus = (float)$vip->sign_bonus_rate;
        $todayReward = round($baseTodayReward * (1 + $vipSignBonus), 2);

        return [
            'signed_today'        => $signedToday,
            'current_streak'      => $currentStreak,
            'day_in_cycle'        => $dayInCycle,
            'today_reward'        => $todayReward,
            'base_today_reward'   => $baseTodayReward,
            'total_sign_days'     => $totalSignDays,
            'rewards_config'      => $rewards,
            'week_status'         => $weekStatus,
            'vip_level_key'       => (string)$vip->level_key,
            'vip_sign_bonus_rate' => $vipSignBonus,
            'vip_crit_max'        => (int)$vip->crit_max_multiple,
        ];
    }

    /**
     * 暴击随机：倍率与概率均可配置（wallet_settings），叠加 VIP 概率加成
     *
     * 默认基础概率：1% → 20x（仅至尊）、1% → 10x、3% → 5x、12% → 2x、其余 1x
     * VIP 概率加成 (vip.crit_prob_bonus) 按绝对百分比叠加到每档，
     * 超出 vip.crit_max_multiple 的档位不参与抽取
     * 保底：在 guaranteeDays 天内若从未出过 ≥ guaranteeMinRate，
     *       则本次强制从合格档位按权重归一化随机
     *
     * @param int      $userId 用户ID，用于查询保底历史
     * @param VipLevel $vip    当前等级配置
     * @return int 倍率（1 / 2 / 5 / 10 / 20）
     */
    protected static function rollBonus(int $userId, VipLevel $vip): int
    {
        $capMultiple = (int)$vip->crit_max_multiple;
        $vipBonus    = (float)$vip->crit_prob_bonus;       // 0~0.15 decimal

        // 倍率配置（支持自定义倍数）
        $bonus2x  = (int)WalletSetting::getValue('sign_bonus_2x_multiplier',  '2');
        $bonus5x  = (int)WalletSetting::getValue('sign_bonus_5x_multiplier',  '5');
        $bonus10x = (int)WalletSetting::getValue('sign_bonus_10x_multiplier', '10');
        $bonus20x = (int)WalletSetting::getValue('sign_bonus_20x_multiplier', '20');

        // 基础概率（百分比整数）
        $rate2x  = (int)WalletSetting::getValue('sign_bonus_2x_rate',  '12');
        $rate5x  = (int)WalletSetting::getValue('sign_bonus_5x_rate',  '3');
        $rate10x = (int)WalletSetting::getValue('sign_bonus_10x_rate', '1');
        $rate20x = (int)WalletSetting::getValue('sign_bonus_20x_rate', '1');

        // 候选档位池：[倍数, 有效概率(decimal)]，仅保留 ≤ capMultiple 的档位
        $tiers = [];
        foreach ([[$bonus20x, $rate20x], [$bonus10x, $rate10x], [$bonus5x, $rate5x], [$bonus2x, $rate2x]] as [$mult, $rate]) {
            if ($mult > $capMultiple) continue;
            $effective = ($rate / 100.0) + $vipBonus;
            if ($effective <= 0) continue;
            $tiers[] = ['multiple' => (int)$mult, 'rate' => $effective];
        }

        // 保底配置
        $guaranteeDays    = (int)WalletSetting::getValue('sign_bonus_guarantee_days',     '7');
        $guaranteeMinRate = (int)WalletSetting::getValue('sign_bonus_guarantee_min_rate', '5');

        if ($guaranteeDays > 0 && $guaranteeMinRate > 1) {
            $startDate = date('Y-m-d', strtotime("-" . ($guaranteeDays - 1) . " days"));
            $hasBigBonus = SignLog::where('user_id', $userId)
                ->where('sign_date', '>=', $startDate)
                ->where('bonus_rate', '>=', $guaranteeMinRate)
                ->count();

            if ($hasBigBonus === 0) {
                $eligible = array_values(array_filter($tiers, fn($t) => $t['multiple'] >= $guaranteeMinRate));
                if (!empty($eligible)) {
                    return self::pickByWeight($eligible);
                }
                return min($guaranteeMinRate, $capMultiple);
            }
        }

        // 正常掷骰（精度 1/1_000_000）
        $roll = mt_rand(1, 1000000);
        $cumu = 0;
        foreach ($tiers as $t) {
            $cumu += (int)round($t['rate'] * 1000000);
            if ($roll <= $cumu) {
                return $t['multiple'];
            }
        }
        return 1;
    }

    /**
     * 按权重从候选池中随机（权重取 rate）
     * @param array $pool [['multiple'=>int, 'rate'=>float], ...]
     */
    protected static function pickByWeight(array $pool): int
    {
        $totalWeight = 0;
        foreach ($pool as $t) {
            $totalWeight += (int)round($t['rate'] * 1000000);
        }
        if ($totalWeight <= 0) return $pool[0]['multiple'];

        $roll = mt_rand(1, $totalWeight);
        $acc = 0;
        foreach ($pool as $t) {
            $acc += (int)round($t['rate'] * 1000000);
            if ($roll <= $acc) {
                return $t['multiple'];
            }
        }
        return $pool[0]['multiple'];
    }

    /**
     * 发放奖励到冻结余额（原子操作）
     *
     * @param int $userId 用户ID
     * @param float $amount 奖励金额
     * @param int $type 流水类型（SIGN_REWARD 或 TASK_REWARD）
     * @param int|null $relatedId 关联ID
     * @param string $remark 备注
     */
    public static function creditRewardFrozen(int $userId, float $amount, int $type, ?int $relatedId, string $remark): void
    {
        $wallet = WalletService::getOrCreateWallet($userId);

        $frozenBefore = (float)$wallet->reward_frozen_balance;

        // 原子递增 reward_frozen_balance 和 total_reward_earned
        Db::table('wallets')
            ->where('user_id', $userId)
            ->update([
                'reward_frozen_balance' => Db::raw("reward_frozen_balance + {$amount}"),
                'total_reward_earned'   => Db::raw("total_reward_earned + {$amount}"),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

        $frozenAfter = bcadd((string)$frozenBefore, (string)$amount, 2);

        // 记录流水
        WalletTransaction::create([
            'user_id'        => $userId,
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $frozenBefore,
            'balance_after'  => $frozenAfter,
            'related_id'     => $relatedId,
            'remark'         => $remark . '(冻结)',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
