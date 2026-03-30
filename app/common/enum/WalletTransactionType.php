<?php
declare(strict_types=1);

namespace app\common\enum;

/**
 * 钱包流水类型
 */
class WalletTransactionType
{
    const RECHARGE          = 1; // 充值
    const WITHDRAWAL        = 2; // 提现
    const DONATION_OUT      = 3; // 捐赠(支出)
    const DONATION_IN       = 4; // 收到捐赠
    const BOOST_PAYMENT     = 5; // 购买曝光
    const RED_PACKET_SEND   = 6; // 发红包
    const RED_PACKET_RECV   = 7; // 收红包
    const RED_PACKET_REFUND = 8; // 红包退回
    const WITHDRAWAL_REFUND = 9; // 提现退回

    const NAMES = [
        self::RECHARGE          => '充值',
        self::WITHDRAWAL        => '提现',
        self::DONATION_OUT      => '捐赠',
        self::DONATION_IN       => '收到捐赠',
        self::BOOST_PAYMENT     => '购买曝光',
        self::RED_PACKET_SEND   => '发红包',
        self::RED_PACKET_RECV   => '收红包',
        self::RED_PACKET_REFUND => '红包退回',
        self::WITHDRAWAL_REFUND => '提现退回',
    ];

    /**
     * 收入类型(余额增加)
     */
    const INCOME_TYPES = [
        self::RECHARGE,
        self::DONATION_IN,
        self::RED_PACKET_RECV,
        self::RED_PACKET_REFUND,
        self::WITHDRAWAL_REFUND,
    ];

    public static function getName(int $type): string
    {
        return self::NAMES[$type] ?? '未知';
    }

    public static function isIncome(int $type): bool
    {
        return in_array($type, self::INCOME_TYPES, true);
    }
}
