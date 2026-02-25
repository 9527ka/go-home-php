<?php
declare(strict_types=1);

namespace app\common\enum;

class PostCategory
{
    const PET   = 1; // 宠物
    const ELDER = 2; // 成年人
    const CHILD = 3; // 儿童
    const OTHER = 4; // 其它物品

    const MAP = [
        self::PET   => '宠物',
        self::ELDER => '成年人',
        self::CHILD => '儿童',
        self::OTHER => '其它物品',
    ];

    /**
     * 首页分组："其它" 包含 宠物 + 其它物品
     */
    const GROUP_OTHER = [self::PET, self::OTHER];

    public static function isValid(int $value): bool
    {
        return isset(self::MAP[$value]);
    }

    public static function getName(int $value): string
    {
        return self::MAP[$value] ?? '未知';
    }

    /**
     * 是否为未成人类别（需要特殊保护）
     */
    public static function isMinor(int $value): bool
    {
        return $value === self::CHILD;
    }

    /**
     * 校验多个分类值（支持逗号分隔）
     */
    public static function parseMultiple(string $categoryStr): array
    {
        $categories = array_map('intval', explode(',', $categoryStr));
        return array_filter($categories, function ($v) {
            return self::isValid($v);
        });
    }
}
