<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\enum\WalletTransactionType;
use app\common\exception\BusinessException;
use app\common\model\UserVip;
use app\common\model\VipLevel;
use app\common\model\VipOrder;
use app\common\model\Wallet;
use app\common\model\WalletTransaction;
use think\facade\Db;

class VipService
{
    /**
     * 获取用户当前有效的 VIP 等级配置
     * 到期自动视为普通；DB 未初始化时返回兜底普通等级，避免级联崩溃
     */
    public static function getCurrentLevel(int $userId): VipLevel
    {
        $userVip = UserVip::find($userId);
        if ($userVip && $userVip->isActive()) {
            $level = VipLevel::findByKey($userVip->level_key);
            if ($level && $level->is_enabled) {
                return $level;
            }
        }
        $normal = VipLevel::findByKey(VipLevel::KEY_NORMAL);
        if ($normal) {
            return $normal;
        }
        // 兜底：DB 未跑 migration 029 时，返回内存默认普通等级（数值与种子一致）
        return self::buildFallbackNormal();
    }

    /**
     * 内存构造一个"普通"等级实例（仅当 DB 缺种子时使用）
     */
    private static function buildFallbackNormal(): VipLevel
    {
        $lv = new VipLevel();
        $lv->data([
            'id'                   => 0,
            'level_key'            => VipLevel::KEY_NORMAL,
            'level_name'           => '普通',
            'level_order'          => 1,
            'price'                => 0,
            'duration_days'        => 30,
            'sign_bonus_rate'      => 0,
            'crit_prob_bonus'      => 0,
            'crit_max_multiple'    => 5,
            'withdraw_fee_rate'    => 0.3,
            'withdraw_daily_limit' => 1000,
            'icon_url'             => '',
            'badge_effect_key'     => 'none',
            'name_effect_key'      => 'none',
            'red_packet_skin_url'  => '',
            'red_packet_effect_key' => 'none',
            'is_enabled'           => 1,
            'sort_order'           => 1,
        ]);
        return $lv;
    }

    /**
     * 获取用户 VIP 详细信息（含到期时间，用于展示）
     * @return array{level: VipLevel, expired_at: ?string, is_active: bool}
     */
    public static function getUserVipInfo(int $userId): array
    {
        $userVip = UserVip::find($userId);
        $level = self::getCurrentLevel($userId);
        return [
            'level'      => $level,
            'expired_at' => $userVip && $userVip->isActive() ? $userVip->expired_at : null,
            'is_active'  => $userVip && $userVip->isActive(),
        ];
    }

    /**
     * 批量获取用户 VIP 快照（用于列表接口，避免 N+1）
     * @param int[] $userIds
     * @return array<int, array{level_key:string, level_name:string, badge_effect_key:string, name_effect_key:string, icon_url:string, expired_at:?string}>
     */
    public static function getVipSnapshots(array $userIds): array
    {
        if (empty($userIds)) return [];
        $now = date('Y-m-d H:i:s');
        $rows = UserVip::whereIn('user_id', $userIds)
            ->where('expired_at', '>', $now)
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $level = VipLevel::findByKey($row['level_key']);
            if (!$level || !$level->is_enabled) continue;
            $result[(int)$row['user_id']] = [
                'level_key'        => $level->level_key,
                'level_name'       => $level->level_name,
                'level_order'      => (int)$level->level_order,
                'badge_effect_key' => $level->badge_effect_key,
                'name_effect_key'  => $level->name_effect_key,
                'icon_url'         => $level->icon_url,
                'expired_at'       => $row['expired_at'],
            ];
        }
        return $result;
    }

    /**
     * 返回单个用户的 VIP 快照（普通等级不返回，避免 API 冗余）
     * @return array|null 与 getVipSnapshots 元素同结构
     */
    public static function getVipSnapshot(int $userId): ?array
    {
        $snaps = self::getVipSnapshots([$userId]);
        return $snaps[$userId] ?? null;
    }

    /**
     * 购买/续费 VIP
     * 续费策略：剩余作废（覆盖 expired_at = now + duration_days）
     */
    public static function purchase(int $userId, string $levelKey): VipOrder
    {
        $level = VipLevel::findByKey($levelKey);
        if (!$level) {
            throw new BusinessException(ErrorCode::VIP_LEVEL_NOT_FOUND);
        }
        if (!$level->is_enabled) {
            throw new BusinessException(ErrorCode::VIP_LEVEL_DISABLED);
        }
        if ($level->isNormal()) {
            throw new BusinessException(ErrorCode::VIP_LEVEL_IS_NORMAL);
        }

        $price = (float)$level->price;
        $durationDays = (int)$level->duration_days;

        return Db::transaction(function () use ($userId, $level, $price, $durationDays) {
            // 扣费（乐观锁）
            $affected = Db::table('wallets')
                ->where('user_id', $userId)
                ->where('balance', '>=', $price)
                ->update([
                    'balance'    => Db::raw("balance - {$price}"),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($affected === 0) {
                // 钱包不存在则初始化后仍判定余额不足
                WalletService::getOrCreateWallet($userId);
                throw new BusinessException(ErrorCode::WALLET_INSUFFICIENT);
            }

            $wallet = Wallet::where('user_id', $userId)->find();
            $balanceAfter = (float)$wallet->balance;
            $balanceBefore = bcadd((string)$balanceAfter, (string)$price, 2);

            // 读取旧 VIP 状态
            $userVip = UserVip::find($userId);
            $prevExpiredAt = ($userVip && $userVip->isActive()) ? $userVip->expired_at : null;

            // 新到期：剩余作废 → now + duration
            $newExpiredAt = date('Y-m-d H:i:s', time() + $durationDays * 86400);

            if ($userVip) {
                $userVip->level_key  = $level->level_key;
                $userVip->expired_at = $newExpiredAt;
                $userVip->save();
            } else {
                UserVip::create([
                    'user_id'    => $userId,
                    'level_key'  => $level->level_key,
                    'expired_at' => $newExpiredAt,
                ]);
            }

            // 钱包流水
            WalletTransaction::create([
                'user_id'        => $userId,
                'type'           => WalletTransactionType::VIP_PURCHASE,
                'amount'         => $price,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'related_id'     => null,
                'remark'         => "购买VIP:{$level->level_name}",
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            // 订单记录
            $order = VipOrder::create([
                'user_id'         => $userId,
                'level_key'       => $level->level_key,
                'price'           => $price,
                'duration_days'   => $durationDays,
                'balance_before'  => $balanceBefore,
                'balance_after'   => $balanceAfter,
                'prev_expired_at' => $prevExpiredAt,
                'new_expired_at'  => $newExpiredAt,
                'status'          => VipOrder::STATUS_SUCCESS,
                'remark'          => "购买{$level->level_name}({$durationDays}天)",
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            // 更新流水关联
            Db::table('wallet_transactions')
                ->where('user_id', $userId)
                ->where('type', WalletTransactionType::VIP_PURCHASE)
                ->where('related_id', null)
                ->order('id', 'desc')
                ->limit(1)
                ->update(['related_id' => $order->id]);

            return $order;
        });
    }

    /**
     * 管理员手动授予/调整 VIP
     */
    public static function adminGrant(int $userId, string $levelKey, ?string $expiredAt = null): void
    {
        $level = VipLevel::findByKey($levelKey);
        if (!$level) {
            throw new BusinessException(ErrorCode::VIP_LEVEL_NOT_FOUND);
        }

        if ($level->isNormal()) {
            UserVip::where('user_id', $userId)->delete();
            return;
        }

        $expiredAt = $expiredAt ?: date('Y-m-d H:i:s', time() + (int)$level->duration_days * 86400);

        $userVip = UserVip::find($userId);
        if ($userVip) {
            $userVip->level_key  = $levelKey;
            $userVip->expired_at = $expiredAt;
            $userVip->save();
        } else {
            UserVip::create([
                'user_id'    => $userId,
                'level_key'  => $levelKey,
                'expired_at' => $expiredAt,
            ]);
        }
    }
}
