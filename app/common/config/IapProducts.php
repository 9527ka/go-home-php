<?php
declare(strict_types=1);

namespace app\common\config;

/**
 * Apple In-App Purchase 产品配置
 *
 * 服务端权威映射：产品 ID → 到账爱心值数量
 * 客户端仅做展示，实际到账以此映射为准
 */
class IapProducts
{
    const PRODUCTS = [
        'com.gohome.coins.100'  => 100,
        'com.gohome.coins.500'  => 500,
        'com.gohome.coins.1000' => 1000,
        'com.gohome.coins.2000' => 2000,
        'com.gohome.coins.5000' => 5000,
    ];

    /**
     * 根据产品 ID 获取对应爱心值数量
     */
    public static function getCoins(string $productId): ?int
    {
        return self::PRODUCTS[$productId] ?? null;
    }

    /**
     * 检查产品 ID 是否合法
     */
    public static function isValid(string $productId): bool
    {
        return isset(self::PRODUCTS[$productId]);
    }
}
