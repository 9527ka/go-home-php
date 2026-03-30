<?php
declare(strict_types=1);

namespace app\common\model;

use think\facade\Cache;
use think\Model;

class WalletSetting extends Model
{
    protected $table = 'wallet_settings';
    protected $autoWriteTimestamp = false;
    protected $updateTime = 'updated_at';

    const CACHE_PREFIX = 'wallet_setting:';
    const CACHE_TTL    = 300; // 5分钟缓存

    /**
     * 获取配置值
     */
    public static function get(string $key, string $default = ''): string
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        $value = Cache::get($cacheKey);

        if ($value !== null) {
            return $value;
        }

        $row = self::where('setting_key', $key)->find();
        $value = $row ? $row->setting_value : $default;

        Cache::set($cacheKey, $value, self::CACHE_TTL);
        return $value;
    }

    /**
     * 设置配置值
     */
    public static function set(string $key, string $value): void
    {
        $row = self::where('setting_key', $key)->find();
        if ($row) {
            $row->setting_value = $value;
            $row->save();
        } else {
            self::create([
                'setting_key'   => $key,
                'setting_value' => $value,
            ]);
        }

        Cache::set(self::CACHE_PREFIX . $key, $value, self::CACHE_TTL);
    }

    /**
     * 获取所有配置(管理后台用)
     */
    public static function all(): array
    {
        return self::column('setting_value', 'setting_key');
    }

    /**
     * 钱包功能是否开启
     */
    public static function isEnabled(): bool
    {
        return self::get('wallet_enabled') === '1';
    }
}
