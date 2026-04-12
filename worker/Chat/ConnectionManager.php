<?php
declare(strict_types=1);

namespace worker\Chat;

use Workerman\Connection\TcpConnection;

/**
 * WebSocket 连接池管理
 */
class ConnectionManager
{
    /** @var TcpConnection[] */
    private array $connections = [];

    /** @var array<string, int> IP 连接计数 */
    private array $ipConnCount = [];

    public function add(TcpConnection $connection): void
    {
        $this->connections[$connection->id] = $connection;
    }

    public function remove(int $connId): void
    {
        unset($this->connections[$connId]);
    }

    public function get(int $connId): ?TcpConnection
    {
        return $this->connections[$connId] ?? null;
    }

    /** @return TcpConnection[] */
    public function all(): array
    {
        return $this->connections;
    }

    public function count(): int
    {
        return count($this->connections);
    }

    // ---- IP 限流 ----

    public function incrementIp(string $ip): int
    {
        $this->ipConnCount[$ip] = ($this->ipConnCount[$ip] ?? 0) + 1;
        return $this->ipConnCount[$ip];
    }

    public function decrementIp(string $ip): void
    {
        if (!isset($this->ipConnCount[$ip])) return;
        $this->ipConnCount[$ip]--;
        if ($this->ipConnCount[$ip] <= 0) {
            unset($this->ipConnCount[$ip]);
        }
    }

    // ---- 在线计数 ----

    public function onlineCount(): int
    {
        $count = 0;
        foreach ($this->connections as $conn) {
            if ($conn->userId ?? null) {
                $count++;
            }
        }
        return $count;
    }

    // ---- 发送 ----

    public function broadcast(array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        foreach ($this->connections as $conn) {
            $conn->send($json);
        }
    }

    public function broadcastOnlineCount(): void
    {
        $this->broadcast([
            'type'         => 'online_count',
            'online_count' => $this->onlineCount(),
        ]);
    }

    /**
     * 向指定用户发送消息
     * @return bool 用户是否在线并已投递
     */
    public function sendToUser(int $userId, array $data): bool
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sent = false;
        foreach ($this->connections as $conn) {
            if (isset($conn->userId) && $conn->userId === $userId) {
                $conn->send($json);
                $sent = true;
            }
        }
        return $sent;
    }

    // ---- 安全关闭 ----

    public function safeClose(TcpConnection $connection, string $reason = ''): void
    {
        $connection->cleanedUp = true;

        if ($reason) {
            try {
                $connection->send(json_encode([
                    'type' => 'error',
                    'msg'  => $reason,
                ], JSON_UNESCAPED_UNICODE));
            } catch (\Exception $e) {
                // 连接可能已断开
            }
        }

        $ip = $connection->connIp ?? '';
        if ($ip) {
            $this->decrementIp($ip);
        }

        $this->remove($connection->id);
        $connection->close();
    }
}
