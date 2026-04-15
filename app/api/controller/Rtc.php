<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use think\Response;

/**
 * 腾讯云 TRTC 相关接口
 *
 * - POST /api/rtc/user-sig : 为当前登录用户生成 TRTC UserSig（有效期 24h）
 *
 * UserSig 算法参考官方文档：
 *   https://cloud.tencent.com/document/product/647/17275
 *
 * 安全要点：
 * 1. SDKSecretKey 仅写入 server/.env 的 TRTC_SDK_SECRET，绝不下发客户端
 * 2. UserSig 必须在服务端生成，客户端按需拉取
 * 3. identifier 强制为当前登录用户的 user_id（防止客户端伪造他人身份）
 */
class Rtc extends BaseApi
{
    /**
     * 为当前登录用户生成 UserSig
     * POST /api/rtc/user-sig
     *
     * @return Response { sdk_app_id, user_id, user_sig, expire }
     */
    public function userSig(): Response
    {
        $userId = $this->getUserId();
        if ($userId <= 0) {
            return $this->error(ErrorCode::AUTH_NOT_LOGIN);
        }

        $sdkAppId = (int)env('TRTC_SDK_APPID', 0);
        $secret   = (string)env('TRTC_SDK_SECRET', '');
        $expire   = (int)env('TRTC_USER_SIG_EXPIRE', 86400);

        if ($sdkAppId <= 0 || $secret === '') {
            return $this->error(ErrorCode::SYSTEM_ERROR, 'TRTC 未配置');
        }

        $identifier = (string)$userId;
        $userSig    = self::genUserSig($sdkAppId, $secret, $identifier, $expire);

        return $this->success([
            'sdk_app_id' => $sdkAppId,
            'user_id'    => $identifier,
            'user_sig'   => $userSig,
            'expire'     => $expire,
        ]);
    }

    /**
     * 调试接口：验证 TRTC 配置和 UserSig 生成
     * GET /api/rtc/debug
     * 生产环境应删除此接口
     */
    public function debug(): Response
    {
        $sdkAppId = (int)env('TRTC_SDK_APPID', 0);
        $secret   = (string)env('TRTC_SDK_SECRET', '');
        $expire   = (int)env('TRTC_USER_SIG_EXPIRE', 86400);

        $testUserId = 'test_user_debug';
        $userSig = ($sdkAppId > 0 && $secret !== '')
            ? self::genUserSig($sdkAppId, $secret, $testUserId, $expire)
            : 'MISSING_CONFIG';

        return $this->success([
            'sdk_app_id'     => $sdkAppId,
            'secret_len'     => strlen($secret),
            'secret_prefix'  => substr($secret, 0, 6) . '...',
            'test_user_id'   => $testUserId,
            'user_sig_len'   => strlen($userSig),
            'user_sig_prefix' => substr($userSig, 0, 40) . '...',
            'expire'         => $expire,
            'hint'           => '请到 TRTC 控制台 > UserSig工具 中用相同 sdkAppId + userId(test_user_debug) 生成 UserSig，对比 user_sig_prefix 是否一致',
        ]);
    }

    // =====================================================================
    //  TRTC UserSig 算法（TLS 票据签名 v2）
    //  移植自腾讯云官方 PHP 示例：TLSSigAPIv2
    // =====================================================================

    private static function genUserSig(int $sdkAppId, string $secret, string $identifier, int $expire): string
    {
        $currentTime = time();
        $sig = self::hmacSha256($secret, $sdkAppId, $identifier, $currentTime, $expire);

        $sigDoc = [
            'TLS.ver'        => '2.0',
            'TLS.identifier' => $identifier,
            'TLS.sdkappid'   => $sdkAppId,
            'TLS.expire'     => $expire,
            'TLS.time'       => $currentTime,
            'TLS.sig'        => $sig,
        ];

        $json       = json_encode($sigDoc, JSON_UNESCAPED_UNICODE);
        $compressed = gzcompress($json);
        if ($compressed === false) {
            throw new \RuntimeException('TRTC UserSig gzcompress failed');
        }

        return self::base64UrlEncode($compressed);
    }

    private static function hmacSha256(string $secret, int $sdkAppId, string $identifier, int $currentTime, int $expire): string
    {
        $content = "TLS.identifier:" . $identifier . "\n"
                 . "TLS.sdkappid:"   . $sdkAppId  . "\n"
                 . "TLS.time:"       . $currentTime . "\n"
                 . "TLS.expire:"     . $expire . "\n";
        $hash = hash_hmac('sha256', $content, $secret, true);
        return base64_encode($hash);
    }

    private static function base64UrlEncode(string $data): string
    {
        $base64 = base64_encode($data);
        return strtr($base64, ['+' => '*', '/' => '-', '=' => '_']);
    }
}
