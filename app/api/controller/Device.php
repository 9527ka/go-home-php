<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use think\facade\Db;
use think\Response;

/**
 * 设备管理 - 推送令牌注册/注销
 */
class Device extends BaseApi
{
    /**
     * 注册设备推送令牌
     * POST /api/device/register-token
     *
     * @body device_token string APNs device token (hex)
     * @body platform     string ios/android
     */
    public function registerToken(): Response
    {
        $userId = $this->getUserId();
        $deviceToken = trim($this->request->post('device_token', ''));
        $platform = trim($this->request->post('platform', 'ios'));

        if (empty($deviceToken) || !preg_match('/^[a-f0-9]{64,200}$/i', $deviceToken)) {
            return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '无效的设备令牌');
        }

        if (!in_array($platform, ['ios', 'android'])) {
            $platform = 'ios';
        }

        try {
            // INSERT ON DUPLICATE KEY UPDATE：同一设备换用户登录自动转移归属
            Db::execute(
                'INSERT INTO `device_tokens` (`user_id`, `device_token`, `platform`, `created_at`, `updated_at`)
                 VALUES (?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`), `platform` = VALUES(`platform`), `updated_at` = NOW()',
                [$userId, $deviceToken, $platform]
            );
        } catch (\Throwable $e) {
            \think\facade\Log::error("[Device] registerToken failed: {$e->getMessage()}");
            return $this->error(ErrorCode::SYSTEM_ERROR, '注册失败');
        }

        return $this->success(null, '设备令牌已注册');
    }

    /**
     * 注销设备推送令牌
     * POST /api/device/unregister-token
     *
     * @body device_token string APNs device token
     */
    public function unregisterToken(): Response
    {
        $userId = $this->getUserId();
        $deviceToken = trim($this->request->post('device_token', ''));

        if (!empty($deviceToken)) {
            try {
                Db::table('device_tokens')
                    ->where('device_token', $deviceToken)
                    ->where('user_id', $userId)
                    ->delete();
            } catch (\Throwable $e) {
                // 静默失败
            }
        }

        return $this->success(null, '设备令牌已注销');
    }
}
