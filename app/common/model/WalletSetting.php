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
     * 配置项元信息（描述、类型）
     */
    const META = [
        'wallet_enabled'         => ['description' => '钱包功能总开关（关闭后捐赠/推广/红包/签到等均不可用）', 'type' => 'toggle'],
        'min_recharge'           => ['description' => '最低充值金额', 'type' => 'number'],
        'min_withdrawal'         => ['description' => '最低提现金额', 'type' => 'number'],
        'withdrawal_fee_rate'    => ['description' => '提现手续费比例（0.05 = 5%）', 'type' => 'number'],
        'boost_hourly_rate'      => ['description' => '推广置顶每小时费用', 'type' => 'number'],
        'min_donation'           => ['description' => '最低捐赠金额', 'type' => 'number'],
        'usdt_address_trc20'     => ['description' => 'USDT 收款地址（TRC20）', 'type' => 'text'],
        'usdt_address_erc20'     => ['description' => 'USDT 收款地址（ERC20）', 'type' => 'text'],
        'red_packet_expire_hours'=> ['description' => '红包过期时间（小时）', 'type' => 'number'],
        'max_red_packet_amount'  => ['description' => '单个红包最大金额', 'type' => 'number'],
        'banner_enabled'         => ['description' => '公告横幅开关（聊天室顶部滚动公告）', 'type' => 'toggle'],
        'banner_text'            => ['description' => '公告横幅内容（滚动显示的文字）', 'type' => 'text'],
        'banner_link'            => ['description' => '公告横幅跳转链接（点击后打开的URL）', 'type' => 'text'],
        'about_version'          => ['description' => '关于我们 - App版本号', 'type' => 'text'],
        'about_telegram'         => ['description' => '关于我们 - Telegram联系方式', 'type' => 'text'],
        'about_website_url'      => ['description' => '关于我们 - 官方网站链接', 'type' => 'text'],
        'about_website_name'     => ['description' => '关于我们 - 官方网站显示名', 'type' => 'text'],
        'about_mission'          => ['description' => '关于我们 - 平台宗旨', 'type' => 'text'],
        'about_safety'           => ['description' => '关于我们 - 安全保障', 'type' => 'text'],
        'about_free_service'     => ['description' => '关于我们 - 公益免费说明', 'type' => 'text'],
        'about_disclaimer'       => ['description' => '关于我们 - 免责声明', 'type' => 'text'],
        'about_privacy'          => ['description' => '关于我们 - 隐私政策', 'type' => 'text'],
    ];

    /**
     * 获取所有配置(管理后台用)，返回数组格式便于前端渲染
     */
    public static function getAll(): array
    {
        $rows = Db::table(self::TABLE)->column('setting_value', 'setting_key');
        $result = [];
        foreach ($rows as $key => $value) {
            $meta = self::META[$key] ?? ['description' => '', 'type' => 'text'];
            $result[] = [
                'key'         => $key,
                'value'       => $value,
                'description' => $meta['description'],
                'type'        => $meta['type'],
            ];
        }
        return $result;
    }

    /**
     * 钱包功能是否开启
     */
    public static function isEnabled(): bool
    {
        return self::getValue('wallet_enabled') === '1';
    }
}
