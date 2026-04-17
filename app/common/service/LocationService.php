<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\User;
use think\facade\Db;
use think\facade\Log;

class LocationService
{
    const SOURCE_GPS = 1;
    const SOURCE_IP  = 2;

    /**
     * 用户上报 GPS 位置
     */
    public static function updateFromGps(int $userId, float $lat, float $lng): void
    {
        if (abs($lat) > 90 || abs($lng) > 180) return;

        Db::table('users')->where('id', $userId)->update([
            'last_latitude'       => $lat,
            'last_longitude'      => $lng,
            'location_source'     => self::SOURCE_GPS,
            'location_updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 登录/请求时按 IP 兜底（仅在用户未授权 GPS 时用）
     * 优先使用已安装的 ip2region / geoip 扩展；否则仅记录 IP，不写经纬度
     */
    public static function updateFromIp(int $userId, string $ip): void
    {
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') return;

        $user = User::field('id,last_latitude,last_longitude,location_source')->find($userId);
        if (!$user) return;
        // GPS 已有则不覆盖
        if ((int)$user->location_source === self::SOURCE_GPS && $user->last_latitude !== null) return;

        $coord = self::resolveIpCoord($ip);
        if ($coord === null) return;

        Db::table('users')->where('id', $userId)->update([
            'last_latitude'       => $coord[0],
            'last_longitude'      => $coord[1],
            'location_source'     => self::SOURCE_IP,
            'location_updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 获取用户位置
     * @return array{lat: float|null, lng: float|null, source: int}
     */
    public static function getUserLocation(int $userId): array
    {
        $user = User::field('last_latitude,last_longitude,location_source')->find($userId);
        return [
            'lat'    => $user?->last_latitude !== null ? (float)$user->last_latitude : null,
            'lng'    => $user?->last_longitude !== null ? (float)$user->last_longitude : null,
            'source' => $user ? (int)$user->location_source : 0,
        ];
    }

    /**
     * 将 IP 解析为经纬度
     * 预留扩展点：检测常见 geoip 库，若未安装则返回 null
     *
     * 部署 ip2region（推荐）：
     *   composer require zoujingli/ip2region
     *   将 xdb 数据库放到 /extend/ip2region.xdb 或 PHP 扩展目录
     * 返回 [lat, lng] 或 null
     */
    protected static function resolveIpCoord(string $ip): ?array
    {
        // 尝试 ip2region（zoujingli 版本，有则用，无则跳过）
        if (class_exists('\Ip2Region')) {
            try {
                $region = (new \Ip2Region())->btreeSearch($ip);
                if (!empty($region['region'])) {
                    // ip2region 只返回行政区，没有经纬度，需要再 geocoder 查一次
                    // MVP 阶段：暂不接入地理编码，直接返回 null（保持 GPS 为主）
                    return null;
                }
            } catch (\Throwable $e) {
                Log::warning("IP2Region error: " . $e->getMessage());
            }
        }
        return null;
    }
}
