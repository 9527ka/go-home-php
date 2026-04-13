<?php
declare(strict_types=1);

namespace app\common\service;

/**
 * 业务侧（API 进程）向 Workerman ChatServer 推送实时消息
 *
 * 通过 ChatServer 监听的内部 TCP 端口（127.0.0.1:7272）转发，
 * ChatServer 端 onMessage 解析 cmd 后再分发到对应 WS 连接。
 *
 * 静默失败：内部端口不通时不抛异常，避免影响主业务流程。
 */
class WsPushService
{
    private const INTERNAL_HOST = '127.0.0.1';
    private const INTERNAL_PORT = 7272;
    private const CONNECT_TIMEOUT_SEC = 1;

    /**
     * 推送给单个用户（在线时立即送达，离线静默丢弃）
     *
     * @param int   $userId 目标用户 ID
     * @param array $data   要发给前端 WS 的 JSON payload，必须含 type 字段
     * @return bool         是否成功投递到 ChatServer 进程（不代表用户在线）
     */
    public static function sendToUser(int $userId, array $data): bool
    {
        return self::sendCmd([
            'cmd'     => 'send_to_user',
            'user_id' => $userId,
            'data'    => $data,
        ]);
    }

    /**
     * 推送给一组用户（如群成员广播）
     *
     * @param int[] $userIds
     * @param array $data
     */
    public static function sendToUsers(array $userIds, array $data): bool
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) return true;

        // ChatServer 内部 cmd 仅支持 send_to_user 单条；这里多次发送
        // （成员数通常 <100，单次握手成本可忽略；如成员激增可在 ChatServer
        // 加 send_to_users 批量 cmd 一次性传 user_ids）
        $allOk = true;
        foreach ($userIds as $uid) {
            if (!self::sendToUser($uid, $data)) {
                $allOk = false;
            }
        }
        return $allOk;
    }

    /**
     * 实际发送：fsockopen + 单行 JSON
     */
    private static function sendCmd(array $cmd): bool
    {
        try {
            $sock = @fsockopen(self::INTERNAL_HOST, self::INTERNAL_PORT, $errno, $errstr, self::CONNECT_TIMEOUT_SEC);
            if (!$sock) return false;
            fwrite($sock, json_encode($cmd, JSON_UNESCAPED_UNICODE) . "\n");
            fclose($sock);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
