<?php
declare(strict_types=1);

namespace app\common\service;

use Firebase\JWT\JWT;
use think\facade\Db;
use think\facade\Log;

/**
 * APNs 推送服务（HTTP/2 直连，JWT 鉴权）
 */
class ApnsPushService
{
    private const APNS_PROD_URL = 'https://api.push.apple.com';
    private const APNS_DEV_URL  = 'https://api.sandbox.push.apple.com';

    /** JWT 缓存（Workerman 长驻进程中持久化） */
    private static ?string $cachedJwt = null;
    private static int $jwtCreatedAt = 0;
    private const JWT_REFRESH_SEC = 3000; // 50 分钟刷新

    /**
     * APNs 是否已配置
     */
    private static function isConfigured(): bool
    {
        $ok = !empty(config('apns.key_id')) && !empty(config('apns.team_id')) && !empty(config('apns.key_path'));
        if (!$ok) {
            // 每进程只提醒一次，避免刷屏
            static $warned = false;
            if (!$warned) {
                Log::warning('[APNs] push disabled: APNS_KEY_ID / APNS_TEAM_ID / APNS_KEY_PATH 未在 .env 中配置，离线用户将收不到推送');
                $warned = true;
            }
        }
        return $ok;
    }

    /**
     * 向单个用户的所有设备推送
     */
    public static function sendToUser(int $userId, string $title, string $body, array $extra = []): void
    {
        if (!self::isConfigured()) return;

        try {
            $tokens = Db::table('device_tokens')
                ->where('user_id', $userId)
                ->where('platform', 'ios')
                ->column('device_token');

            if (empty($tokens)) return;

            $payload = self::buildPayload($title, $body, $extra);
            $headers = self::buildHeaders();
            $url = self::getApnsUrl();

            foreach ($tokens as $token) {
                self::curlSend($url, $token, $payload, $headers);
            }
        } catch (\Throwable $e) {
            Log::warning("[APNs] sendToUser failed userId={$userId}: {$e->getMessage()}");
        }
    }

    /**
     * 向多个用户批量推送（curl_multi 并行）
     */
    public static function sendToUsers(array $userIds, string $title, string $body, array $extra = []): void
    {
        if (empty($userIds) || !self::isConfigured()) return;

        try {
            $tokens = Db::table('device_tokens')
                ->whereIn('user_id', $userIds)
                ->where('platform', 'ios')
                ->column('device_token');

            if (empty($tokens)) return;

            $payload = self::buildPayload($title, $body, $extra);
            $headers = self::buildHeaders();
            $url = self::getApnsUrl();

            $mh = curl_multi_init();
            $handles = [];

            foreach ($tokens as $token) {
                $ch = curl_init("{$url}/3/device/{$token}");
                curl_setopt_array($ch, [
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 5,
                    CURLOPT_HTTPHEADER     => $headers,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$token] = $ch;
            }

            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh, 1);
                }
            } while ($active && $status === CURLM_OK);

            foreach ($handles as $token => $ch) {
                self::handleResponse($token, $ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
        } catch (\Throwable $e) {
            Log::warning("[APNs] sendToUsers failed: {$e->getMessage()}");
        }
    }

    /**
     * 单条 curl 发送
     */
    private static function curlSend(string $url, string $deviceToken, string $payload, array $headers): void
    {
        try {
            $ch = curl_init("{$url}/3/device/{$deviceToken}");
            curl_setopt_array($ch, [
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_HTTPHEADER     => $headers,
            ]);

            curl_exec($ch);
            self::handleResponse($deviceToken, $ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            Log::warning("[APNs] curlSend error token={$deviceToken}: {$e->getMessage()}");
        }
    }

    /**
     * 处理 APNs 响应：清理失效 token、记录错误
     */
    private static function handleResponse(string $token, $ch): void
    {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 410) {
            Db::table('device_tokens')->where('device_token', $token)->delete();
        } elseif ($httpCode !== 200 && $httpCode !== 0) {
            $resp = curl_multi_getcontent($ch) ?: curl_exec($ch);
            Log::warning("[APNs] Push failed token={$token} http={$httpCode} resp={$resp}");
        }
    }

    /**
     * 构建 APNs payload JSON
     */
    private static function buildPayload(string $title, string $body, array $extra): string
    {
        return json_encode([
            'aps' => [
                'alert' => ['title' => $title, 'body' => $body],
                'sound' => 'default',
                'mutable-content' => 1,
            ],
            'extra' => $extra,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 构建 APNs 请求头
     */
    private static function buildHeaders(): array
    {
        $jwt = self::getApnsJwt();
        $bundleId = config('apns.bundle_id');
        return [
            "authorization: bearer {$jwt}",
            "apns-topic: {$bundleId}",
            "apns-push-type: alert",
            "apns-priority: 10",
            'content-type: application/json',
        ];
    }

    /**
     * 获取 APNs JWT（缓存 50 分钟）
     */
    private static function getApnsJwt(): string
    {
        $now = time();
        if (self::$cachedJwt && ($now - self::$jwtCreatedAt) < self::JWT_REFRESH_SEC) {
            return self::$cachedJwt;
        }

        $keyId   = config('apns.key_id');
        $teamId  = config('apns.team_id');
        $keyPath = config('apns.key_path');

        $privateKey = file_get_contents($keyPath);
        if (!$privateKey) {
            throw new \RuntimeException("APNs key file not readable: {$keyPath}");
        }

        self::$cachedJwt = JWT::encode(['iss' => $teamId, 'iat' => $now], $privateKey, 'ES256', $keyId);
        self::$jwtCreatedAt = $now;

        return self::$cachedJwt;
    }

    /**
     * 获取 APNs URL
     */
    private static function getApnsUrl(): string
    {
        return config('apns.sandbox') ? self::APNS_DEV_URL : self::APNS_PROD_URL;
    }
}
