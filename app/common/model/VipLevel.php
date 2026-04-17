<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class VipLevel extends Model
{
    protected $table = 'vip_levels';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    const KEY_NORMAL   = 'normal';
    const KEY_SILVER   = 'silver';
    const KEY_GOLD     = 'gold';
    const KEY_PLATINUM = 'platinum';
    const KEY_DIAMOND  = 'diamond';
    const KEY_SUPREME  = 'supreme';

    /**
     * 进程内缓存（VIP 等级配置变更频率极低）
     * @var array<string, VipLevel>|null
     */
    private static ?array $levelCache = null;

    /**
     * 获取所有启用等级（按 sort_order 升序）
     * @return VipLevel[]
     */
    public static function listAll(): array
    {
        self::ensureCache();
        return array_values(self::$levelCache);
    }

    /**
     * 根据 level_key 查配置
     */
    public static function findByKey(string $levelKey): ?VipLevel
    {
        self::ensureCache();
        return self::$levelCache[$levelKey] ?? null;
    }

    /**
     * 清空缓存（配置变更后调用）
     */
    public static function flushCache(): void
    {
        self::$levelCache = null;
    }

    /**
     * 是否为最低等级（普通）
     */
    public function isNormal(): bool
    {
        return $this->level_key === self::KEY_NORMAL;
    }

    private static function ensureCache(): void
    {
        if (self::$levelCache !== null) return;
        $rows = self::where('is_enabled', 1)
            ->order('sort_order', 'asc')
            ->select();
        $cache = [];
        foreach ($rows as $row) {
            $cache[$row->level_key] = $row;
        }
        self::$levelCache = $cache;
    }
}
