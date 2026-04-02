<?php
declare(strict_types=1);

namespace app\common\enum;

/**
 * 统一错误码
 *
 * 1xxx — 认证相关
 * 2xxx — 参数相关
 * 3xxx — 业务相关
 * 4xxx — 系统相关
 */
class ErrorCode
{
    // ========== 成功 ==========
    const SUCCESS = 0;

    // ========== 认证相关 1xxx ==========
    const AUTH_NOT_LOGIN        = 1001;
    const AUTH_TOKEN_EXPIRED    = 1002;
    const AUTH_TOKEN_INVALID    = 1003;
    const AUTH_ACCOUNT_EXISTS   = 1004;
    const AUTH_ACCOUNT_NOT_FOUND = 1005;
    const AUTH_PASSWORD_WRONG   = 1006;
    const AUTH_ACCOUNT_DISABLED = 1007;
    const AUTH_ADMIN_DENIED     = 1008;

    // ========== 参数相关 2xxx ==========
    const PARAM_MISSING         = 2001;
    const PARAM_FORMAT_ERROR    = 2002;
    const PARAM_VALIDATE_FAIL   = 2003;
    const PARAM_IMAGE_TOO_LARGE = 2004;
    const PARAM_IMAGE_TYPE_ERR  = 2005;

    // ========== 业务相关 3xxx ==========
    const POST_NOT_FOUND        = 3001;
    const POST_NO_PERMISSION    = 3002;
    const POST_AUDIT_PENDING    = 3003;
    const POST_ALREADY_CLOSED   = 3004;
    const POST_PUBLISH_LIMIT    = 3005;
    const CLUE_NOT_FOUND        = 3006;
    const REPORT_DUPLICATE      = 3007;
    const FAVORITE_EXISTS       = 3008;
    const USER_BANNED           = 3009;
    const CHILD_ADDRESS_DENIED  = 3010; // 未成人禁止填写精确地址
    const FEEDBACK_TOO_SHORT    = 3011; // 反馈内容过短
    const ACCOUNT_DELETE_FAILED = 3013; // 注销失败

    // ---- 好友相关 ----
    const FRIEND_SELF              = 3020;
    const FRIEND_ALREADY           = 3021;
    const FRIEND_REQUEST_PENDING   = 3022;
    const FRIEND_REQUEST_NOT_FOUND = 3023;
    const FRIEND_NOT_FOUND         = 3024;

    // ---- 群组相关 ----
    const GROUP_NOT_FOUND          = 3030;
    const GROUP_NOT_MEMBER         = 3031;
    const GROUP_NO_PERMISSION      = 3032;
    const GROUP_OWNER_CANNOT_LEAVE = 3033;
    const GROUP_FULL               = 3034;

    // ---- 钱包相关 ----
    const WALLET_DISABLED          = 3500;
    const WALLET_INSUFFICIENT      = 3501;
    const WALLET_RECHARGE_PENDING  = 3502;
    const WALLET_WITHDRAWAL_PENDING = 3503;
    const WALLET_AMOUNT_TOO_SMALL  = 3504;
    const WALLET_FROZEN            = 3505;
    const WALLET_SELF_DONATE       = 3506;
    const RED_PACKET_EXPIRED       = 3510;
    const RED_PACKET_CLAIMED       = 3511;
    const RED_PACKET_EMPTY         = 3512;
    const BOOST_POST_INACTIVE      = 3515;

    // ---- 评论相关 ----
    const COMMENT_NOT_FOUND        = 3040;
    const COMMENT_NO_PERMISSION    = 3041;
    const COMMENT_TOO_FREQUENT     = 3042;

    // ---- 关注相关 ----
    const FOLLOW_SELF              = 3050;

    // ---- 签到/任务相关 ----
    const SIGN_DISABLED            = 3520;
    const SIGN_ALREADY_TODAY       = 3521;
    const TASK_NOT_FOUND           = 3522;
    const TASK_ALREADY_DONE        = 3523;
    const TASK_DISABLED            = 3524;

    // ========== 系统相关 4xxx ==========
    const UPLOAD_FAIL           = 4001;
    const SYSTEM_ERROR          = 4002;
    const DB_ERROR              = 4003;
    const RATE_LIMIT            = 4004;

    /**
     * 错误信息映射
     */
    const MESSAGES = [
        self::SUCCESS               => '操作成功',

        self::AUTH_NOT_LOGIN        => '请先登录',
        self::AUTH_TOKEN_EXPIRED    => '登录已过期，请重新登录',
        self::AUTH_TOKEN_INVALID    => '无效的Token',
        self::AUTH_ACCOUNT_EXISTS   => '账号已存在',
        self::AUTH_ACCOUNT_NOT_FOUND => '账号不存在',
        self::AUTH_PASSWORD_WRONG   => '密码错误',
        self::AUTH_ACCOUNT_DISABLED => '账号已被禁用',
        self::AUTH_ADMIN_DENIED     => '无管理员权限',

        self::PARAM_MISSING         => '缺少必要参数',
        self::PARAM_FORMAT_ERROR    => '参数格式错误',
        self::PARAM_VALIDATE_FAIL   => '参数校验失败',
        self::PARAM_IMAGE_TOO_LARGE => '图片大小超出限制',
        self::PARAM_IMAGE_TYPE_ERR  => '不支持的图片格式',

        self::POST_NOT_FOUND        => '启事不存在',
        self::POST_NO_PERMISSION    => '无权操作该启事',
        self::POST_AUDIT_PENDING    => '启事正在审核中',
        self::POST_ALREADY_CLOSED   => '启事已关闭',
        self::POST_PUBLISH_LIMIT    => '今日发布数量已达上限',
        self::CLUE_NOT_FOUND        => '线索不存在',
        self::REPORT_DUPLICATE      => '您已举报过该内容',
        self::FAVORITE_EXISTS       => '已收藏',
        self::USER_BANNED           => '账号已被封禁',
        self::CHILD_ADDRESS_DENIED  => '为保护未成人，不允许填写精确住址',
        self::FEEDBACK_TOO_SHORT    => '反馈内容至少5个字',
        self::ACCOUNT_DELETE_FAILED => '账号注销失败，请稍后重试',

        self::FRIEND_SELF              => '不能添加自己为好友',
        self::FRIEND_ALREADY           => '已经是好友了',
        self::FRIEND_REQUEST_PENDING   => '已发送过请求，请等待对方处理',
        self::FRIEND_REQUEST_NOT_FOUND => '请求不存在或已处理',
        self::FRIEND_NOT_FOUND         => '好友关系不存在',
        self::GROUP_NOT_FOUND          => '群组不存在',
        self::GROUP_NOT_MEMBER         => '您不是该群成员',
        self::GROUP_NO_PERMISSION      => '无权执行此操作',
        self::GROUP_OWNER_CANNOT_LEAVE => '群主不能退出，请先解散群组',
        self::GROUP_FULL               => '群组人数已满',

        self::COMMENT_NOT_FOUND        => '评论不存在',
        self::COMMENT_NO_PERMISSION    => '无权操作该评论',
        self::COMMENT_TOO_FREQUENT     => '评论过于频繁',
        self::FOLLOW_SELF              => '不能关注自己',

        self::WALLET_DISABLED          => '钱包功能未开启',
        self::WALLET_INSUFFICIENT      => '余额不足',
        self::WALLET_RECHARGE_PENDING  => '您有待审核的充值订单',
        self::WALLET_WITHDRAWAL_PENDING => '您有待审核的提现订单',
        self::WALLET_AMOUNT_TOO_SMALL  => '金额低于最低限额',
        self::WALLET_FROZEN            => '钱包已被冻结',
        self::WALLET_SELF_DONATE       => '不能给自己捐赠',
        self::RED_PACKET_EXPIRED       => '红包已过期',
        self::RED_PACKET_CLAIMED       => '您已领取过该红包',
        self::RED_PACKET_EMPTY         => '红包已被领完',
        self::BOOST_POST_INACTIVE      => '启事状态异常，无法置顶',
        self::SIGN_DISABLED            => '签到功能未开启',
        self::SIGN_ALREADY_TODAY       => '今日已签到',
        self::TASK_NOT_FOUND           => '任务不存在',
        self::TASK_ALREADY_DONE        => '任务已完成',
        self::TASK_DISABLED            => '任务未开启',

        self::UPLOAD_FAIL           => '文件上传失败',
        self::SYSTEM_ERROR          => '系统异常，请稍后重试',
        self::DB_ERROR              => '数据操作失败',
        self::RATE_LIMIT            => '操作过于频繁，请稍后再试',
    ];

    /**
     * 获取错误信息
     */
    public static function getMessage(int $code): string
    {
        return self::MESSAGES[$code] ?? '未知错误';
    }
}
