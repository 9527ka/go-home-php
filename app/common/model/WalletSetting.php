<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Cache;
use think\facade\Db;

/**
 * 钱包配置（KV 表）
 * 不继承 Model，避免方法名冲突
 */
class WalletSetting
{
    const TABLE        = 'wallet_settings';
    const CACHE_PREFIX = 'wallet_setting:';
    const CACHE_TTL    = 300; // 5分钟缓存

    /**
     * 获取配置值
     */
    public static function getValue(string $key, string $default = ''): string
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        $value = Cache::get($cacheKey);

        if ($value !== null) {
            return (string)$value;
        }

        $row = Db::table(self::TABLE)->where('setting_key', $key)->find();
        $value = $row ? (string)$row['setting_value'] : $default;

        Cache::set($cacheKey, $value, self::CACHE_TTL);
        return $value;
    }

    /**
     * 设置配置值
     */
    public static function setValue(string $key, string $value): void
    {
        $row = Db::table(self::TABLE)->where('setting_key', $key)->find();
        if ($row) {
            Db::table(self::TABLE)->where('setting_key', $key)->update([
                'setting_value' => $value,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        } else {
            Db::table(self::TABLE)->insert([
                'setting_key'   => $key,
                'setting_value' => $value,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        Cache::set(self::CACHE_PREFIX . $key, $value, self::CACHE_TTL);
    }

    /**
     * 获取所有配置(管理后台用)
     */
    public static function getAll(): array
    {
        return Db::table(self::TABLE)->column('setting_value', 'setting_key');
    }

    /**
     * 钱包功能是否开启
     */
    public static function isEnabled(): bool
    {
        return self::getValue('wallet_enabled') === '1';
    }
}
