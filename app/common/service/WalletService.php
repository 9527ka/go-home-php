<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\enum\WalletTransactionType;
use app\common\exception\BusinessException;
use app\common\model\Clue;
use app\common\model\Donation;
use app\common\model\Post;
use app\common\model\PostBoost;
use app\common\model\RechargeOrder;
use app\common\model\RedPacket;
use app\common\model\RedPacketClaim;
use app\common\model\RewardClaim;
use app\common\model\Wallet;
use app\common\model\WalletSetting;
use app\common\model\WalletTransaction;
use app\common\model\WithdrawalOrder;
use think\facade\Db;
use think\facade\Log;

class WalletService
{
    // ========== 钱包基础操作 ==========

    /**
     * 获取或创建钱包（惰性初始化）
     */
    public static function getOrCreateWallet(int $userId): Wallet
    {
        $wallet = Wallet::where('user_id', $userId)->find();
        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $userId,
                'balance' => 0,
                'frozen_balance' => 0,
            ]);
        }
        return $wallet;
    }

    /**
     * 检查钱包功能是否开启
     */
    public static function checkEnabled(): void
    {
        if (!WalletSetting::isEnabled()) {
            throw new BusinessException(ErrorCode::WALLET_DISABLED);
        }
    }

    /**
     * 扣减余额（原子操作，乐观锁防超扣）
     */
    protected static function deduct(int $userId, float $amount, int $type, ?int $relatedId, string $remark): void
    {
        $wallet = self::getOrCreateWallet($userId);

        if (!$wallet->isNormal()) {
            throw new BusinessException(ErrorCode::WALLET_FROZEN);
        }

        $balanceBefore = (float)$wallet->balance;

        // 乐观锁扣减：WHERE balance >= amount
        $affected = Db::table('wallets')
            ->where('user_id', $userId)
            ->where('balance', '>=', $amount)
            ->update([
                'balance'    => Db::raw("balance - {$amount}"),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($affected === 0) {
            throw new BusinessException(ErrorCode::WALLET_INSUFFICIENT);
        }

        // 更新累计统计
        if ($type === WalletTransactionType::DONATION_OUT) {
            Db::table('wallets')->where('user_id', $userId)
                ->update(['total_donated' => Db::raw("total_donated + {$amount}")]);
        }

        // 记录流水
        WalletTransaction::create([
            'user_id'        => $userId,
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => bcsub((string)$balanceBefore, (string)$amount, 2),
            'related_id'     => $relatedId,
            'remark'         => $remark,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 增加余额
     */
    protected static function credit(int $userId, float $amount, int $type, ?int $relatedId, string $remark): void
    {
        $wallet = self::getOrCreateWallet($userId);

        // 合并余额增加与累计统计为单次 update，减少 SQL 开销
        $updateData = [
            'balance'    => Db::raw("balance + {$amount}"),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($type === WalletTransactionType::RECHARGE) {
            $updateData['total_recharged'] = Db::raw("total_recharged + {$amount}");
        } elseif ($type === WalletTransactionType::DONATION_IN) {
            $updateData['total_received'] = Db::raw("total_received + {$amount}");
        }
        Db::table('wallets')
            ->where('user_id', $userId)
            ->update($updateData);

        // 重新读取更新后的余额，保证流水记录精确
        $balanceAfter = (float)Db::table('wallets')->where('user_id', $userId)->value('balance');
        $balanceBefore = bcsub((string)$balanceAfter, (string)$amount, 2);

        WalletTransaction::create([
            'user_id'        => $userId,
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'related_id'     => $relatedId,
            'remark'         => $remark,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 冻结余额（提现申请时）
     */
    protected static function freeze(int $userId, float $amount): void
    {
        $affected = Db::table('wallets')
            ->where('user_id', $userId)
            ->where('balance', '>=', $amount)
            ->update([
                'balance'        => Db::raw("balance - {$amount}"),
                'frozen_balance' => Db::raw("frozen_balance + {$amount}"),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        if ($affected === 0) {
            throw new BusinessException(ErrorCode::WALLET_INSUFFICIENT);
        }
    }

    /**
     * 解冻余额（提现拒绝时）
     */
    protected static function unfreeze(int $userId, float $amount): void
    {
        Db::table('wallets')
            ->where('user_id', $userId)
            ->update([
                'balance'        => Db::raw("balance + {$amount}"),
                'frozen_balance' => Db::raw("frozen_balance - {$amount}"),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 扣减冻结金额（提现通过后）
     */
    protected static function deductFrozen(int $userId, float $amount): void
    {
        Db::table('wallets')
            ->where('user_id', $userId)
            ->update([
                'frozen_balance'  => Db::raw("frozen_balance - {$amount}"),
                'total_withdrawn' => Db::raw("total_withdrawn + {$amount}"),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
    }

    // ========== 充值 ==========

    /**
     * 管理员审核通过充值（自带事务）
     */
    public static function recharge(int $userId, float $amount, int $orderId): void
    {
        Db::transaction(function () use ($userId, $amount, $orderId) {
            self::credit($userId, $amount, WalletTransactionType::RECHARGE, $orderId, '充值');
        });
    }

    /**
     * IAP 充值到账（无事务包裹，供调用方在外层事务中使用）
     */
    public static function iapCredit(int $userId, float $amount, int $orderId): void
    {
        self::credit($userId, $amount, WalletTransactionType::RECHARGE, $orderId, 'Apple IAP充值');
    }

    // ========== 提现 ==========

    /**
     * 用户提交提现申请
     */
    public static function requestWithdrawal(int $userId, float $amount, string $address, string $chain): WithdrawalOrder
    {
        self::checkEnabled();
        $wallet = self::getOrCreateWallet($userId);

        if (!$wallet->isNormal()) {
            throw new BusinessException(ErrorCode::WALLET_FROZEN);
        }

        $minWithdrawal = (float)WalletSetting::getValue('min_withdrawal', '20');
        if ($amount < $minWithdrawal) {
            throw new BusinessException(ErrorCode::WALLET_AMOUNT_TOO_SMALL, "最低提现 {$minWithdrawal} 爱心币");
        }

        // 检查是否有待审核的提现
        $pending = WithdrawalOrder::where('user_id', $userId)
            ->where('status', WithdrawalOrder::STATUS_PENDING)
            ->find();
        if ($pending) {
            throw new BusinessException(ErrorCode::WALLET_WITHDRAWAL_PENDING);
        }

        $feeRate = (float)WalletSetting::getValue('withdrawal_fee_rate', '0.05');
        $fee = round($amount * $feeRate, 2);
        $netAmount = round($amount - $fee, 2);
        $totalDeduct = $amount; // 冻结总额 = 提现金额（含手续费在内）

        return Db::transaction(function () use ($userId, $amount, $fee, $netAmount, $totalDeduct, $address, $chain) {
            self::freeze($userId, $totalDeduct);

            $order = new WithdrawalOrder();
            $order->user_id        = $userId;
            $order->amount         = $amount;
            $order->fee            = $fee;
            $order->net_amount     = $netAmount;
            $order->wallet_address = $address;
            $order->chain_type     = $chain;
            $order->status         = WithdrawalOrder::STATUS_PENDING;
            $order->save();

            // 记录提现流水
            $wallet = Wallet::where('user_id', $userId)->find();
            WalletTransaction::create([
                'user_id'        => $userId,
                'type'           => WalletTransactionType::WITHDRAWAL,
                'amount'         => $amount,
                'balance_before' => bcadd((string)$wallet->balance, (string)$totalDeduct, 2),
                'balance_after'  => (string)$wallet->balance,
                'related_id'     => $order->id,
                'remark'         => '提现申请',
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            return $order;
        });
    }

    /**
     * 管理员通过提现
     */
    public static function approveWithdrawal(int $orderId, int $adminId, string $txHash = ''): void
    {
        $order = WithdrawalOrder::find($orderId);
        if (!$order || $order->status !== WithdrawalOrder::STATUS_PENDING) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '订单不存在或已处理');
        }

        Db::transaction(function () use ($order, $adminId, $txHash) {
            self::deductFrozen($order->user_id, (float)$order->amount);

            $order->status       = WithdrawalOrder::STATUS_COMPLETED;
            $order->admin_id     = $adminId;
            $order->tx_hash      = $txHash;
            $order->processed_at = date('Y-m-d H:i:s');
            $order->save();
        });
    }

    /**
     * 管理员拒绝提现
     */
    public static function rejectWithdrawal(int $orderId, int $adminId, string $remark = ''): void
    {
        $order = WithdrawalOrder::find($orderId);
        if (!$order || $order->status !== WithdrawalOrder::STATUS_PENDING) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '订单不存在或已处理');
        }

        Db::transaction(function () use ($order, $adminId, $remark) {
            self::unfreeze($order->user_id, (float)$order->amount);

            // 记录退回流水
            $wallet = Wallet::where('user_id', $order->user_id)->find();
            WalletTransaction::create([
                'user_id'        => $order->user_id,
                'type'           => WalletTransactionType::WITHDRAWAL_REFUND,
                'amount'         => $order->amount,
                'balance_before' => bcsub((string)$wallet->balance, (string)$order->amount, 2),
                'balance_after'  => (string)$wallet->balance,
                'related_id'     => $order->id,
                'remark'         => '提现被拒绝' . ($remark ? ": {$remark}" : ''),
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            $order->status       = WithdrawalOrder::STATUS_REJECTED;
            $order->admin_id     = $adminId;
            $order->admin_remark = $remark;
            $order->processed_at = date('Y-m-d H:i:s');
            $order->save();
        });
    }

    // ========== 捐赠 ==========

    /**
     * 捐赠启事发布者
     */
    public static function donate(int $fromUserId, int $postId, float $amount, string $message = '', bool $anonymous = false): Donation
    {
        self::checkEnabled();

        $post = Post::find($postId);
        if (!$post || !$post->isActive()) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        if ($post->user_id === $fromUserId) {
            throw new BusinessException(ErrorCode::WALLET_SELF_DONATE);
        }

        $minDonation = (float)WalletSetting::getValue('min_donation', '1');
        if ($amount < $minDonation) {
            throw new BusinessException(ErrorCode::WALLET_AMOUNT_TOO_SMALL, "最低捐赠 {$minDonation} 爱心币");
        }

        return Db::transaction(function () use ($fromUserId, $post, $amount, $message, $anonymous) {
            // 扣捐赠者
            self::deduct($fromUserId, $amount, WalletTransactionType::DONATION_OUT, null, "捐赠启事#{$post->id}");

            // 加给发布者
            self::credit($post->user_id, $amount, WalletTransactionType::DONATION_IN, null, '收到捐赠');

            // 创建捐赠记录
            $donation = Donation::create([
                'from_user_id' => $fromUserId,
                'to_user_id'   => $post->user_id,
                'post_id'      => $post->id,
                'amount'       => $amount,
                'message'      => $message,
                'is_anonymous' => $anonymous ? 1 : 0,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            // 更新 donation 关联ID到流水
            Db::table('wallet_transactions')
                ->where('user_id', $fromUserId)
                ->where('type', WalletTransactionType::DONATION_OUT)
                ->where('related_id', null)
                ->order('id', 'desc')
                ->limit(1)
                ->update(['related_id' => $donation->id]);

            Db::table('wallet_transactions')
                ->where('user_id', $post->user_id)
                ->where('type', WalletTransactionType::DONATION_IN)
                ->where('related_id', null)
                ->order('id', 'desc')
                ->limit(1)
                ->update(['related_id' => $donation->id]);

            // 通知接收者
            NotifyService::send(
                $post->user_id,
                5, // system notification
                '收到捐赠',
                ($anonymous ? '匿名用户' : '') . "向您的启事「{$post->name}」捐赠了 {$amount} 爱心币",
                $post->id
            );

            // 签到任务：完成一笔消费
            try {
                TaskService::incrementTaskProgress($fromUserId, 'purchase');
            } catch (\Throwable $e) {
                // 静默失败
            }

            return $donation;
        });
    }

    // ========== 置顶/曝光 ==========

    /**
     * 购买启事置顶
     */
    public static function boostPost(int $userId, int $postId, int $hours): PostBoost
    {
        self::checkEnabled();

        $post = Post::find($postId);
        if (!$post || !$post->isActive()) {
            throw new BusinessException(ErrorCode::BOOST_POST_INACTIVE);
        }

        $hourlyRate = (float)WalletSetting::getValue('boost_hourly_rate', '10');
        $totalCost = round($hourlyRate * $hours, 2);

        $now = date('Y-m-d H:i:s');

        // 如果已有活跃置顶，在其过期时间基础上续期
        $existingBoost = PostBoost::where('post_id', $postId)->active()->find();
        $startAt = $existingBoost ? $existingBoost->expire_at : $now;
        $expireAt = date('Y-m-d H:i:s', strtotime($startAt) + $hours * 3600);

        return Db::transaction(function () use ($userId, $postId, $hours, $totalCost, $hourlyRate, $startAt, $expireAt) {
            self::deduct($userId, $totalCost, WalletTransactionType::BOOST_PAYMENT, null, "置顶启事#{$postId} {$hours}小时");

            $boost = PostBoost::create([
                'user_id'     => $userId,
                'post_id'     => $postId,
                'hours'       => $hours,
                'total_cost'  => $totalCost,
                'hourly_rate' => $hourlyRate,
                'start_at'    => $startAt,
                'expire_at'   => $expireAt,
                'status'      => PostBoost::STATUS_ACTIVE,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            // 更新流水关联ID
            Db::table('wallet_transactions')
                ->where('user_id', $userId)
                ->where('type', WalletTransactionType::BOOST_PAYMENT)
                ->where('related_id', null)
                ->order('id', 'desc')
                ->limit(1)
                ->update(['related_id' => $boost->id]);

            // 签到任务：完成一笔消费
            try {
                TaskService::incrementTaskProgress($userId, 'purchase');
            } catch (\Throwable $e) {
                // 静默失败
            }

            return $boost;
        });
    }

    // ========== 红包 ==========

    /**
     * 发红包
     */
    public static function sendRedPacket(int $userId, int $targetType, int $targetId, float $totalAmount, int $totalCount, string $greeting = ''): RedPacket
    {
        self::checkEnabled();

        $maxAmount = (float)WalletSetting::getValue('max_red_packet_amount', '500');
        if ($totalAmount > $maxAmount) {
            throw new BusinessException(ErrorCode::WALLET_AMOUNT_TOO_SMALL, "红包金额不能超过 {$maxAmount} 爱心币");
        }

        if ($totalCount < 1 || $totalCount > 100) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '红包个数须在1-100之间');
        }

        // 每个红包最少 0.01
        if ($totalAmount < $totalCount * 0.01) {
            throw new BusinessException(ErrorCode::WALLET_AMOUNT_TOO_SMALL, '红包金额过小');
        }

        $expireHours = (int)WalletSetting::getValue('red_packet_expire_hours', '24');
        $expireAt = date('Y-m-d H:i:s', time() + $expireHours * 3600);

        if (empty($greeting)) {
            $greeting = '恭喜发财，大吉大利';
        }

        // 发红包时快照发送者当前 VIP 等级（E2: 降级后红包外观不变）
        $senderVipLevel = VipService::getCurrentLevel($userId)->level_key;

        return Db::transaction(function () use ($userId, $senderVipLevel, $targetType, $targetId, $totalAmount, $totalCount, $greeting, $expireAt) {
            self::deduct($userId, $totalAmount, WalletTransactionType::RED_PACKET_SEND, null, '发红包');

            $packet = RedPacket::create([
                'user_id'          => $userId,
                'sender_vip_level' => $senderVipLevel,
                'target_type'      => $targetType,
                'target_id'        => $targetId,
                'total_amount'     => $totalAmount,
                'total_count'      => $totalCount,
                'remaining_amount' => $totalAmount,
                'remaining_count'  => $totalCount,
                'greeting'         => $greeting,
                'status'           => RedPacket::STATUS_ACTIVE,
                'expire_at'        => $expireAt,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            // 更新流水关联
            Db::table('wallet_transactions')
                ->where('user_id', $userId)
                ->where('type', WalletTransactionType::RED_PACKET_SEND)
                ->where('related_id', null)
                ->order('id', 'desc')
                ->limit(1)
                ->update(['related_id' => $packet->id]);

            // 签到任务：完成一笔消费
            try {
                TaskService::incrementTaskProgress($userId, 'purchase');
            } catch (\Throwable $e) {
                // 静默失败
            }

            return $packet;
        });
    }

    /**
     * 抢红包（双倍均值随机算法）
     */
    public static function claimRedPacket(int $userId, int $redPacketId): RedPacketClaim
    {
        self::checkEnabled();

        $packet = RedPacket::find($redPacketId);
        if (!$packet) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '红包不存在');
        }

        if (!$packet->isClaimable()) {
            if ($packet->status === RedPacket::STATUS_EXPIRED) {
                throw new BusinessException(ErrorCode::RED_PACKET_EXPIRED);
            }
            throw new BusinessException(ErrorCode::RED_PACKET_EMPTY);
        }

        return Db::transaction(function () use ($userId, $packet, $redPacketId) {
            // 锁行防并发
            $lockedPacket = Db::table('red_packets')
                ->where('id', $packet->id)
                ->lock(true)
                ->find();

            if ($lockedPacket['remaining_count'] <= 0) {
                throw new BusinessException(ErrorCode::RED_PACKET_EMPTY);
            }

            // 检查是否已领取（必须在事务内、行锁之后检查，防止并发双领）
            $existing = RedPacketClaim::where('red_packet_id', $redPacketId)
                ->where('user_id', $userId)
                ->find();
            if ($existing) {
                throw new BusinessException(ErrorCode::RED_PACKET_CLAIMED);
            }

            // 计算领取金额
            $remaining = (float)$lockedPacket['remaining_amount'];
            $remainCount = (int)$lockedPacket['remaining_count'];

            if ($remainCount === 1) {
                $claimAmount = $remaining;
            } else {
                // 双倍均值法: 最多拿 (剩余/剩余人数) * 2
                $avg = $remaining / $remainCount;
                $max = min($avg * 2, $remaining - ($remainCount - 1) * 0.01);
                $claimAmount = round(mt_rand(1, (int)($max * 100)) / 100, 2);
                $claimAmount = max(0.01, $claimAmount);
            }

            // 原子更新红包剩余
            Db::table('red_packets')
                ->where('id', $packet->id)
                ->update([
                    'remaining_amount' => Db::raw("remaining_amount - {$claimAmount}"),
                    'remaining_count'  => Db::raw('remaining_count - 1'),
                    'status'           => $remainCount === 1 ? RedPacket::STATUS_CLAIMED : RedPacket::STATUS_ACTIVE,
                ]);

            // 创建领取记录
            $claim = RedPacketClaim::create([
                'red_packet_id' => $packet->id,
                'user_id'       => $userId,
                'amount'        => $claimAmount,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            // 给领取者加余额
            self::credit($userId, $claimAmount, WalletTransactionType::RED_PACKET_RECV, $packet->id, '领取红包');

            return $claim;
        });
    }

    // ========== 悬赏 ==========

    /**
     * 创建启事时冻结悬赏金额
     * 从发布者余额冻结到 frozen_balance，不直接扣减
     */
    public static function freezeReward(int $userId, int $postId, float $amount): void
    {
        self::checkEnabled();

        $minReward = (float)WalletSetting::getValue('min_reward', '100');
        if ($amount < $minReward) {
            throw new BusinessException(ErrorCode::WALLET_AMOUNT_TOO_SMALL, "悬赏最低 {$minReward} 爱心币");
        }

        $wallet = self::getOrCreateWallet($userId);
        if (!$wallet->isNormal()) {
            throw new BusinessException(ErrorCode::WALLET_FROZEN);
        }

        // 原子冻结
        $affected = Db::table('wallets')
            ->where('user_id', $userId)
            ->where('balance', '>=', $amount)
            ->update([
                'balance'        => Db::raw("balance - {$amount}"),
                'frozen_balance' => Db::raw("frozen_balance + {$amount}"),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        if ($affected === 0) {
            throw new BusinessException(ErrorCode::WALLET_INSUFFICIENT);
        }

        // 记录冻结流水
        $balanceAfter = (float)Db::table('wallets')->where('user_id', $userId)->value('balance');
        $balanceBefore = bcadd((string)$balanceAfter, (string)$amount, 2);

        WalletTransaction::create([
            'user_id'        => $userId,
            'type'           => WalletTransactionType::BOUNTY_FREEZE,
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'related_id'     => $postId,
            'remark'         => "悬赏冻结 启事#{$postId}",
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 发放悬赏给线索提供者（可分次发放）
     */
    public static function payReward(int $fromUserId, int $postId, int $clueId, float $amount, string $message = ''): RewardClaim
    {
        self::checkEnabled();

        $post = Post::find($postId);
        if (!$post) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        if ($post->user_id !== $fromUserId) {
            throw new BusinessException(ErrorCode::POST_NO_PERMISSION, '只有发布者可以发放悬赏');
        }

        $clue = Clue::where('id', $clueId)->where('post_id', $postId)->where('status', 1)->find();
        if (!$clue) {
            throw new BusinessException(ErrorCode::BOUNTY_CLUE_NOT_FOUND);
        }

        if ($clue->user_id === $fromUserId) {
            throw new BusinessException(ErrorCode::BOUNTY_SELF_CLAIM);
        }

        $remaining = bcsub((string)$post->reward_amount, (string)$post->reward_paid, 2);
        if ($amount > (float)$remaining || $amount <= 0) {
            throw new BusinessException(ErrorCode::BOUNTY_EXCEED, "剩余可发放 {$remaining} 爱心币");
        }

        return Db::transaction(function () use ($fromUserId, $post, $clue, $amount, $message) {
            // 从 frozen_balance 扣减
            $affected = Db::table('wallets')
                ->where('user_id', $fromUserId)
                ->where('frozen_balance', '>=', $amount)
                ->update([
                    'frozen_balance' => Db::raw("frozen_balance - {$amount}"),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);

            if ($affected === 0) {
                throw new BusinessException(ErrorCode::WALLET_INSUFFICIENT, '冻结余额不足');
            }

            // 记录发放者支出流水（余额不变，冻结减少）
            $wallet = Wallet::where('user_id', $fromUserId)->find();
            WalletTransaction::create([
                'user_id'        => $fromUserId,
                'type'           => WalletTransactionType::BOUNTY_PAY,
                'amount'         => $amount,
                'balance_before' => (string)$wallet->balance,
                'balance_after'  => (string)$wallet->balance,
                'related_id'     => null,
                'remark'         => "悬赏发放 线索#{$clue->id}",
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            // 给线索提供者加余额
            self::credit($clue->user_id, $amount, WalletTransactionType::BOUNTY_INCOME, null, "收到悬赏 启事#{$post->id}");

            // 创建发放记录
            $claim = RewardClaim::create([
                'post_id'      => $post->id,
                'clue_id'      => $clue->id,
                'from_user_id' => $fromUserId,
                'to_user_id'   => $clue->user_id,
                'amount'       => $amount,
                'message'      => $message,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            // 更新流水关联ID
            Db::table('wallet_transactions')
                ->where('user_id', $fromUserId)
                ->where('type', WalletTransactionType::BOUNTY_PAY)
                ->where('related_id', null)
                ->order('id', 'desc')
                ->limit(1)
                ->update(['related_id' => $claim->id]);

            Db::table('wallet_transactions')
                ->where('user_id', $clue->user_id)
                ->where('type', WalletTransactionType::BOUNTY_INCOME)
                ->where('related_id', null)
                ->order('id', 'desc')
                ->limit(1)
                ->update(['related_id' => $claim->id]);

            // 更新启事已发放金额
            Db::table('posts')
                ->where('id', $post->id)
                ->update([
                    'reward_paid' => Db::raw("reward_paid + {$amount}"),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);

            // 通知线索提供者
            NotifyService::send(
                $clue->user_id,
                5,
                '收到悬赏',
                "您为启事「{$post->name}」提供的线索获得了 {$amount} 爱心币悬赏",
                $post->id
            );

            return $claim;
        });
    }

    /**
     * 退还悬赏（帖子关闭/过期时退还未发放部分）
     */
    public static function refundReward(int $postId): void
    {
        $post = Post::find($postId);
        if (!$post) return;

        $remaining = bcsub((string)$post->reward_amount, (string)$post->reward_paid, 2);
        if ((float)$remaining <= 0) return;

        try {
            Db::transaction(function () use ($post, $remaining) {
                $refundAmount = (float)$remaining;
                $userId = $post->user_id;

                // 从 frozen_balance 退回到 balance
                Db::table('wallets')
                    ->where('user_id', $userId)
                    ->update([
                        'balance'        => Db::raw("balance + {$refundAmount}"),
                        'frozen_balance' => Db::raw("frozen_balance - {$refundAmount}"),
                        'updated_at'     => date('Y-m-d H:i:s'),
                    ]);

                // 记录退还流水
                $wallet = Wallet::where('user_id', $userId)->find();
                WalletTransaction::create([
                    'user_id'        => $userId,
                    'type'           => WalletTransactionType::BOUNTY_REFUND,
                    'amount'         => $refundAmount,
                    'balance_before' => bcsub((string)$wallet->balance, (string)$refundAmount, 2),
                    'balance_after'  => (string)$wallet->balance,
                    'related_id'     => $post->id,
                    'remark'         => "悬赏退还 启事#{$post->id}",
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);

                // 更新启事 reward_paid = reward_amount（标记为已全部处理）
                Db::table('posts')
                    ->where('id', $post->id)
                    ->update([
                        'reward_paid' => $post->reward_amount,
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ]);
            });
        } catch (\Exception $e) {
            Log::error("Reward refund failed post#{$postId}: " . $e->getMessage());
        }
    }

    /**
     * 退回过期红包（定时任务调用）
     */
    public static function refundExpiredRedPackets(): int
    {
        $expiredPackets = RedPacket::where('status', RedPacket::STATUS_ACTIVE)
            ->where('expire_at', '<', date('Y-m-d H:i:s'))
            ->where('remaining_amount', '>', 0)
            ->select();

        $count = 0;
        foreach ($expiredPackets as $packet) {
            try {
                Db::transaction(function () use ($packet) {
                    $refundAmount = (float)$packet->remaining_amount;
                    if ($refundAmount <= 0) return;

                    self::credit(
                        $packet->user_id,
                        $refundAmount,
                        WalletTransactionType::RED_PACKET_REFUND,
                        $packet->id,
                        '红包过期退回'
                    );

                    $packet->status = RedPacket::STATUS_EXPIRED;
                    $packet->remaining_amount = 0;
                    $packet->remaining_count = 0;
                    $packet->save();
                });
                $count++;
            } catch (\Exception $e) {
                Log::error("Red packet refund failed #{$packet->id}: " . $e->getMessage());
            }
        }

        if ($count > 0) {
            Log::info("Refunded {$count} expired red packets");
        }
        return $count;
    }
}
