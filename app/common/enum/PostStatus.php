<?php
declare(strict_types=1);

namespace app\common\enum;

class PostStatus
{
    const PENDING  = 0; // 待审核
    const ACTIVE   = 1; // 已发布
    const FOUND    = 2; // 已找到
    const CLOSED   = 3; // 已关闭
    const REJECTED = 4; // 审核驳回

    const MAP = [
        self::PENDING  => '待审核',
        self::ACTIVE   => '已发布',
        self::FOUND    => '已找到',
        self::CLOSED   => '已关闭',
        self::REJECTED => '审核驳回',
    ];

    public static function isValid(int $value): bool
    {
        return isset(self::MAP[$value]);
    }

    public static function getName(int $value): string
    {
        return self::MAP[$value] ?? '未知';
    }
}
