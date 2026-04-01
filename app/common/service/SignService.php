<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\enum\WalletTransactionType;
use app\common\exception\BusinessException;
use app\common\model\SignLog;
use app\common\model\UserSignStatus;
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

        // 暴击计算
        $bonusRate = self::rollBonus();

        // 最终奖励
        $finalReward = round($baseReward * $bonusRate, 2);

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

        // 今天可获得的奖励
        $todayReward = (float)($rewards[$dayInCycle - 1] ?? $rewards[0]);

        return [
            'signed_today'    => $signedToday,
            'current_streak'  => $currentStreak,
            'day_in_cycle'    => $dayInCycle,
            'today_reward'    => $todayReward,
            'total_sign_days' => $totalSignDays,
            'rewards_config'  => $rewards,
            'week_status'     => $weekStatus,
        ];
    }

    /**
     * 暴击随机：2%概率5倍，10%概率2倍，88%无暴击
     */
    protected static function rollBonus(): int
    {
        $bonus2xRate = (int)WalletSetting::getValue('sign_bonus_2x_rate', '10');
        $bonus5xRate = (int)WalletSetting::getValue('sign_bonus_5x_rate', '2');

        $roll = mt_rand(1, 100);

        if ($roll <= $bonus5xRate) {
            return 5;
        }
        if ($roll <= $bonus5xRate + $bonus2xRate) {
            return 2;
        }
        return 1;
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
