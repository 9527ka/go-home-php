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
