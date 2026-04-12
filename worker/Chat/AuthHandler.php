<?php
declare(strict_types=1);

namespace worker\Chat;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Workerman\Connection\TcpConnection;

/**
 * 认证处理
 */
class AuthHandler
{
    private ConnectionManager $cm;
    private string $jwtSecret;

    public function __construct(ConnectionManager $cm, string $jwtSecret)
    {
        $this->cm = $cm;
        $this->jwtSecret = $jwtSecret;
    }

    public function handle(TcpConnection $connection, array $msg): void
    {
        if ($connection->userId) {
            MessageValidator::sendError($connection, '已认证，无需重复操作');
            return;
        }

        $token  = $msg['token'] ?? '';
        $userId = $this->verifyToken($token);

        if (!$userId) {
            $connection->send(json_encode([
                'type' => 'auth_fail',
                'msg'  => '认证失败，请重新登录',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        $userInfo = MessageRepository::getUserInfo($userId);
        if (!$userInfo) {
            $connection->send(json_encode([
                'type' => 'auth_fail',
                'msg'  => '用户不存在或已被禁用',
            ], JSON_UNESCAPED_UNICODE));
            return;
        }

        // 踢掉同一用户的旧连接
        $toKick = [];
        foreach ($this->cm->all() as $id => $existingConn) {
            if (($existingConn->userId ?? null) === $userId && $id !== $connection->id) {
                $toKick[] = $existingConn;
            }
        }
        foreach ($toKick as $existingConn) {
            echo "[Auth] Kick old connection #{$existingConn->id} for userId={$userId}\n";
            $this->cm->safeClose($existingConn, '您在其他设备上登录了');
        }

        $connection->userId   = $userId;
        $connection->userInfo = $userInfo;

        $connection->send(json_encode([
            'type' => 'auth_success',
            'user' => $userInfo,
        ], JSON_UNESCAPED_UNICODE));

        $this->cm->broadcastOnlineCount();
        echo "[Auth] #{$connection->id} => userId={$userId}\n";
    }

    private function verifyToken(string $token): ?int
    {
        try {
            if (str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
            }
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $payload = (array)$decoded;
            return (int)($payload['user_id'] ?? 0) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
