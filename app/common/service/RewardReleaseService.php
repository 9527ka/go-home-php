<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\WalletTransactionType;
use app\common\model\WalletSetting;
use app\common\model\WalletTransaction;
use think\facade\Db;
use think\facade\Log;

class RewardReleaseService
{
    /**
     * 每日释放奖励冻结余额到可用余额
     *
     * 规则：
     * - 每天释放 reward_frozen_balance 的 N%（默认10%）
     * - 最小释放 0.01 USDT
     * - 剩余不足 0.01 则全部释放
     *
     * @return int 处理的钱包数量
     */
    public static function releaseDaily(): int
    {
        $releaseRate = (int)WalletSetting::getValue('reward_daily_release_rate', '10');
        if ($releaseRate <= 0 || $releaseRate > 100) {
            $releaseRate = 10;
        }

        // 查询所有有冻结奖励的钱包
        $wallets = Db::table('wallets')
            ->where('reward_frozen_balance', '>', 0)
            ->select();

        $count = 0;
        foreach ($wallets as $wallet) {
            try {
                self::releaseForWallet($wallet, $releaseRate);
                $count++;
            } catch (\Exception $e) {
                Log::error("Reward release failed for user#{$wallet['user_id']}: " . $e->getMessage());
            }
        }

        if ($count > 0) {
            Log::info("Daily reward release: processed {$count} wallets, rate={$releaseRate}%");
        }

        return $count;
    }

    /**
     * 释放单个钱包的冻结奖励
     */
    protected static function releaseForWallet(array $wallet, int $releaseRate): void
    {
        $frozen = (float)$wallet['reward_frozen_balance'];
        if ($frozen <= 0) {
            return;
        }

        // 计算释放金额
        $releaseAmount = round($frozen * $releaseRate / 100, 2);

        // 最小释放 0.01，如果冻结不足 0.01 则全部释放
        if ($releaseAmount < 0.01) {
            $releaseAmount = $frozen;
        }

        // 确保不超过冻结余额
        if ($releaseAmount > $frozen) {
            $releaseAmount = $frozen;
        }

        $userId = (int)$wallet['user_id'];
        $balanceBefore = (float)$wallet['balance'];

        Db::transaction(function () use ($userId, $releaseAmount, $balanceBefore, $frozen) {
            // 原子操作：冻结余额减少，可用余额增加
            $affected = Db::table('wallets')
                ->where('user_id', $userId)
                ->where('reward_frozen_balance', '>=', $releaseAmount)
                ->update([
                    'reward_frozen_balance' => Db::raw("reward_frozen_balance - {$releaseAmount}"),
                    'balance'               => Db::raw("balance + {$releaseAmount}"),
                    'updated_at'            => date('Y-m-d H:i:s'),
                ]);

            if ($affected === 0) {
                return; // 并发情况下余额已被处理
            }

            // 记录流水
            WalletTransaction::create([
                'user_id'        => $userId,
                'type'           => WalletTransactionType::REWARD_RELEASE,
                'amount'         => $releaseAmount,
                'balance_before' => $balanceBefore,
                'balance_after'  => bcadd((string)$balanceBefore, (string)$releaseAmount, 2),
                'related_id'     => null,
                'remark'         => '奖励释放(冻结→可用)',
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        });
    }
}
