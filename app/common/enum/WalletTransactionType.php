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
    const SIGN_REWARD       = 10; // 签到奖励
    const TASK_REWARD       = 11; // 任务奖励
    const REWARD_RELEASE    = 12; // 奖励释放(冻结→可用)
    const BOUNTY_FREEZE     = 13; // 悬赏冻结
    const BOUNTY_PAY        = 14; // 悬赏发放(支出)
    const BOUNTY_INCOME     = 15; // 悬赏收入
    const BOUNTY_REFUND     = 16; // 悬赏退还
    const VIP_PURCHASE      = 17; // VIP 购买(支出)
    const LOTTERY_COST      = 18; // 抽奖消耗(支出)
    const LOTTERY_REWARD    = 19; // 抽奖中奖(收入)
    const FOUND_STORY_REWARD = 20; // 找回故事奖励(收入)

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
        self::SIGN_REWARD       => '签到奖励',
        self::TASK_REWARD       => '任务奖励',
        self::REWARD_RELEASE    => '奖励释放',
        self::BOUNTY_FREEZE     => '悬赏冻结',
        self::BOUNTY_PAY        => '悬赏发放',
        self::BOUNTY_INCOME     => '悬赏收入',
        self::BOUNTY_REFUND     => '悬赏退还',
        self::VIP_PURCHASE      => 'VIP购买',
        self::LOTTERY_COST      => '抽奖消耗',
        self::LOTTERY_REWARD    => '抽奖中奖',
        self::FOUND_STORY_REWARD => '找回故事奖励',
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
        self::REWARD_RELEASE,
        self::BOUNTY_INCOME,
        self::BOUNTY_REFUND,
        self::LOTTERY_REWARD,
        self::FOUND_STORY_REWARD,
    ];

    /**
     * 奖励冻结类型(reward_frozen_balance增加)
     */
    const REWARD_FROZEN_TYPES = [
        self::SIGN_REWARD,
        self::TASK_REWARD,
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
