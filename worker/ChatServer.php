<?php
/**
 * 聊天室 WebSocket 服务
 *
 * 启动命令: php worker/ChatServer.php start [-d]
 * 停止命令: php worker/ChatServer.php stop
 * 重启命令: php worker/ChatServer.php restart
 * 查看状态: php worker/ChatServer.php status
 *
 * 架构: ChatServer（调度） → Handler 类（业务逻辑）
 *   - Chat/ConnectionManager   连接池管理
 *   - Chat/MessageValidator    消息校验（统一）
 *   - Chat/MessageRepository   消息持久化（统一）
 *   - Chat/AuthHandler         认证
 *   - Chat/PublicChatHandler   公共聊天
 *   - Chat/PrivateChatHandler  私聊
 *   - Chat/GroupChatHandler    群聊
 *   - Chat/RedPacketHandler    红包
 */

require_once __DIR__ . '/../vendor/autoload.php';

// 自动加载 worker/Chat/ 命名空间
spl_autoload_register(function (string $class) {
    if (str_starts_with($class, 'worker\\Chat\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;
use worker\Chat\ConnectionManager;
use worker\Chat\MessageValidator;
use worker\Chat\AuthHandler;
use worker\Chat\PublicChatHandler;
use worker\Chat\PrivateChatHandler;
use worker\Chat\GroupChatHandler;
use worker\Chat\RedPacketHandler;

// ===== 引导 ThinkPHP 框架 =====
(new think\App())->initialize();

// ===== 配置常量 =====
const MAX_CONNECTIONS      = 500;
const MAX_CONN_PER_IP      = 5;
const AUTH_TIMEOUT_SEC     = 30;
const IDLE_TIMEOUT_SEC     = 3600;
const CLEANUP_INTERVAL_SEC = 60;
const RED_PACKET_REFUND_INTERVAL = 60;

// ===== 共享组件 =====
$cm        = new ConnectionManager();
$jwtSecret = config('jwt.secret');

$authHandler      = new AuthHandler($cm, $jwtSecret);
$publicHandler    = new PublicChatHandler($cm);
$privateHandler   = new PrivateChatHandler($cm);
$groupHandler     = new GroupChatHandler($cm);
$redPacketHandler = new RedPacketHandler($cm);

// =====================================================================
//  辅助函数
// =====================================================================

function getConnIp(TcpConnection $connection): string
{
    $ip = $connection->getRemoteIp();
    if (isset($connection->headers['X-Forwarded-For'])) {
        $forwarded = explode(',', $connection->headers['X-Forwarded-For']);
        $ip = trim($forwarded[0]);
    }
    return $ip;
}

// =====================================================================
//  WebSocket 服务
// =====================================================================

$ws = new Worker('websocket://0.0.0.0:8383');
$ws->count = 1;
$ws->name  = 'GoHomeChatServer';

// ----- onWorkerStart -----
$ws->onWorkerStart = function () use ($cm) {
    // 定时清理：认证超时 & 空闲连接
    Timer::add(CLEANUP_INTERVAL_SEC, function () use ($cm) {
        $now     = time();
        $toClean = [];

        foreach ($cm->all() as $conn) {
            if (!$conn->userId && ($now - $conn->connectTime) > AUTH_TIMEOUT_SEC) {
                $toClean[] = ['conn' => $conn, 'reason' => '认证超时，连接已断开'];
            } elseif ($conn->userId && ($now - $conn->lastActiveTime) > IDLE_TIMEOUT_SEC) {
                $toClean[] = ['conn' => $conn, 'reason' => '长时间未活动，连接已断开'];
            }
        }

        foreach ($toClean as $item) {
            $cm->safeClose($item['conn'], $item['reason']);
        }

        if (count($toClean) > 0) {
            $cm->broadcastOnlineCount();
            echo "[Cleanup] Removed " . count($toClean) . " connections, active=" . $cm->count() . "\n";
        }
    });

    // 定时检查过期红包
    Timer::add(RED_PACKET_REFUND_INTERVAL, function () {
        RedPacketHandler::refundExpired();
    });

    // 服务端心跳
    Timer::add(30, function () use ($cm) {
        $ping = json_encode(['type' => 'ping']);
        foreach ($cm->all() as $conn) {
            if ($conn->userId ?? null) {
                $conn->send($ping);
            }
        }
    });

    echo "[ChatServer] Started. max_conn=" . MAX_CONNECTIONS . "\n";
};

// ----- onConnect -----
$ws->onConnect = function (TcpConnection $connection) use ($cm) {
    $now = time();

    if ($cm->count() >= MAX_CONNECTIONS) {
        echo "[Reject] Max connections reached (" . MAX_CONNECTIONS . ")\n";
        MessageValidator::sendError($connection, '服务器繁忙，请稍后再试');
        $connection->close();
        return;
    }

    $ip = getConnIp($connection);
    if ($cm->incrementIp($ip) > MAX_CONN_PER_IP) {
        echo "[Reject] Too many connections from IP {$ip}\n";
        $cm->decrementIp($ip);
        MessageValidator::sendError($connection, '连接过多，请关闭多余窗口');
        $connection->close();
        return;
    }

    $cm->add($connection);
    $connection->userId            = null;
    $connection->userInfo          = null;
    $connection->connIp            = $ip;
    $connection->connectTime       = $now;
    $connection->lastActiveTime    = $now;
    $connection->rateLimitMessages = [];
    $connection->cleanedUp         = false;

    echo "[Connect] #{$connection->id} from {$ip} (total=" . $cm->count() . ")\n";
};

// ----- onMessage -----
$ws->onMessage = function (TcpConnection $connection, $data) use (
    $authHandler, $publicHandler, $privateHandler, $groupHandler, $redPacketHandler
) {
    $msg = json_decode($data, true);
    if (!$msg || !isset($msg['type'])) return;

    $connection->lastActiveTime = time();

    switch ($msg['type']) {
        case 'auth':
            $authHandler->handle($connection, $msg);
            break;
        case 'message':
            $publicHandler->handle($connection, $msg);
            break;
        case 'private_message':
            $privateHandler->handle($connection, $msg);
            break;
        case 'group_message':
            $groupHandler->handle($connection, $msg);
            break;
        case 'red_packet':
            $redPacketHandler->handle($connection, $msg);
            break;
        case 'ping':
            $connection->lastActiveTime = time();
            $connection->send(json_encode(['type' => 'pong']));
            break;
    }
};

// ----- onClose -----
$ws->onClose = function (TcpConnection $connection) use ($cm) {
    $userId = $connection->userId ?? null;

    if (!($connection->cleanedUp ?? false)) {
        $ip = $connection->connIp ?? '';
        if ($ip) $cm->decrementIp($ip);
        $cm->remove($connection->id);
    }

    echo "[Close] #{$connection->id}" . ($userId ? " userId={$userId}" : '')
        . " (total=" . $cm->count() . ")\n";
    $cm->broadcastOnlineCount();
};

// ----- onError -----
$ws->onError = function (TcpConnection $connection, $code, $msg) {
    echo "[Error] #{$connection->id}: [{$code}] {$msg}\n";
};

// =====================================================================
//  内部通信 Worker（接收 API 推送指令）
// =====================================================================

$internal = new Worker('text://0.0.0.0:7272');
$internal->name = 'GoHomeInternal';

$internal->onMessage = function (TcpConnection $connection, $data) use ($cm, $redPacketHandler) {
    $cmd = json_decode($data, true);
    if (!$cmd || !isset($cmd['cmd'])) return;

    if ($cmd['cmd'] === 'send_to_user' && isset($cmd['user_id'], $cmd['data'])) {
        $userId = (int)$cmd['user_id'];
        $cm->sendToUser($userId, is_array($cmd['data']) ? $cmd['data'] : json_decode($cmd['data'], true));
        echo "[Internal] Pushed to userId={$userId}\n";
    }

    if ($cmd['cmd'] === 'red_packet_claimed' && isset($cmd['red_packet_id'])) {
        $redPacketHandler->broadcastClaimed(
            (int)$cmd['red_packet_id'],
            (int)($cmd['user_id'] ?? 0),
            (float)($cmd['amount'] ?? 0),
            (int)($cmd['target_type'] ?? 0),
            (int)($cmd['target_id'] ?? 0),
        );
    }
};

Worker::runAll();
