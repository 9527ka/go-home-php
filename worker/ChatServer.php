<?php
/**
 * 聊天室 WebSocket 服务
 *
 * 启动命令: php worker/ChatServer.php start [-d]
 * 停止命令: php worker/ChatServer.php stop
 * 重启命令: php worker/ChatServer.php restart
 * 查看状态: php worker/ChatServer.php status
 *
 * 特性:
 * - 使用 ThinkPHP Db 查询构建器（统一数据库操作，自动断线重连）
 * - 消息频率限制（防刷屏）
 * - 认证超时自动断开
 * - 空闲连接清理
 * - 连接数上限保护
 * - IP 连接数限制（防滥用）
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Db;

// ===== 引导 ThinkPHP 框架（加载 .env、配置、数据库层） =====
(new think\App())->initialize();

// ===== 配置常量 =====
const MAX_CONNECTIONS      = 500;   // 最大连接数
const MAX_CONN_PER_IP      = 5;     // 每 IP 最大连接数
const AUTH_TIMEOUT_SEC     = 30;    // 未认证连接超时（秒）
const IDLE_TIMEOUT_SEC     = 600;   // 空闲连接超时（10分钟）
const RATE_LIMIT_MESSAGES  = 10;    // 消息频率限制：N 条
const RATE_LIMIT_WINDOW    = 10;    // 频率限制时间窗口（秒）
const CLEANUP_INTERVAL_SEC = 60;    // 清理检查间隔（秒）
const MSG_MAX_LENGTH       = 500;   // 文本消息最大长度
const ALLOWED_MSG_TYPES    = ['text', 'image', 'video', 'voice'];

// ===== 全局状态 =====
$connections = [];   // 连接池
$ipConnCount = [];   // IP 连接计数

// ===== JWT 密钥 =====
$jwtSecret = env('JWT_SECRET', 'go_home_jwt_secret_change_me_in_production');

// =====================================================================
//  辅助函数
// =====================================================================

/**
 * 验证 JWT Token，返回 user_id 或 null
 */
function verifyToken(string $token): ?int
{
    global $jwtSecret;
    try {
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }
        $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
        $payload = (array)$decoded;
        return (int)($payload['user_id'] ?? 0) ?: null;
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * 获取用户信息（ThinkPHP Db 查询）
 */
function getUserInfo(int $userId): ?array
{
    try {
        $user = Db::table('users')
            ->field('id, nickname, avatar, user_code')
            ->where('id', $userId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->find();
        return $user ?: null;
    } catch (\Exception $e) {
        echo "[DB Error] getUserInfo: {$e->getMessage()}\n";
        return null;
    }
}

/**
 * 保存聊天消息到数据库（ThinkPHP Db 查询）
 */
function saveMessage(int $userId, string $content, string $msgType = 'text', string $mediaUrl = '', string $thumbUrl = '', ?array $mediaInfo = null): ?int
{
    try {
        $data = [
            'user_id'    => $userId,
            'msg_type'   => $msgType,
            'content'    => $content,
            'media_url'  => $mediaUrl,
            'thumb_url'  => $thumbUrl,
            'media_info' => $mediaInfo ? json_encode($mediaInfo, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return (int)Db::table('chat_messages')->insertGetId($data);
    } catch (\Exception $e) {
        echo "[DB Error] saveMessage: {$e->getMessage()}\n";
        return null;
    }
}

/**
 * 获取在线认证用户数量
 */
function getOnlineCount(): int
{
    global $connections;
    $count = 0;
    foreach ($connections as $conn) {
        if ($conn->userId) {
            $count++;
        }
    }
    return $count;
}

/**
 * 广播消息给所有连接
 */
function broadcast(array $data): void
{
    global $connections;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    foreach ($connections as $conn) {
        $conn->send($json);
    }
}

/**
 * 向指定用户发送消息（通过 userId）
 * @param int $userId 目标用户 ID
 * @param array $data 消息内容
 * @return bool 是否发送成功（用户在线）
 */
function sendToUser(int $userId, array $data): bool
{
    global $connections;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $sent = false;
    foreach ($connections as $conn) {
        if (isset($conn->userId) && $conn->userId === $userId) {
            $conn->send($json);
            $sent = true;
        }
    }
    return $sent;
}

/**
 * 广播在线人数
 */
function broadcastOnlineCount(): void
{
    broadcast([
        'type'         => 'online_count',
        'online_count' => getOnlineCount(),
    ]);
}

/**
 * 检查消息频率限制
 * @return bool true=允许发送, false=被限流
 */
function checkRateLimit(TcpConnection $connection): bool
{
    $now = time();

    if (!isset($connection->rateLimitMessages)) {
        $connection->rateLimitMessages = [];
    }

    // 移除过期记录
    $connection->rateLimitMessages = array_filter(
        $connection->rateLimitMessages,
        fn($ts) => ($now - $ts) < RATE_LIMIT_WINDOW
    );

    if (count($connection->rateLimitMessages) >= RATE_LIMIT_MESSAGES) {
        return false;
    }

    $connection->rateLimitMessages[] = $now;
    return true;
}

/**
 * 获取连接的真实 IP 地址（支持 Nginx 反代）
 */
function getConnIp(TcpConnection $connection): string
{
    $ip = $connection->getRemoteIp();
    if (isset($connection->headers['X-Forwarded-For'])) {
        $forwarded = explode(',', $connection->headers['X-Forwarded-For']);
        $ip = trim($forwarded[0]);
    }
    return $ip;
}

/**
 * 发送 JSON 错误消息
 */
function sendError(TcpConnection $connection, string $msg): void
{
    $connection->send(json_encode([
        'type' => 'error',
        'msg'  => $msg,
    ], JSON_UNESCAPED_UNICODE));
}

/**
 * 安全关闭连接（清理计数 + 发送原因）
 */
function safeClose(TcpConnection $connection, string $reason = ''): void
{
    global $connections, $ipConnCount;

    // 标记已清理，防止 onClose 重复处理
    $connection->cleanedUp = true;

    if ($reason) {
        try {
            sendError($connection, $reason);
        } catch (\Exception $e) {
            // 连接可能已断开，忽略
        }
    }

    // 清理 IP 计数
    $ip = $connection->connIp ?? '';
    if ($ip && isset($ipConnCount[$ip])) {
        $ipConnCount[$ip]--;
        if ($ipConnCount[$ip] <= 0) {
            unset($ipConnCount[$ip]);
        }
    }

    unset($connections[$connection->id]);
    $connection->close();
}

// =====================================================================
//  消息处理器
// =====================================================================

/**
 * 处理认证消息
 */
function handleAuth(TcpConnection $connection, array $msg): void
{
    global $connections;

    // 防止重复认证
    if ($connection->userId) {
        sendError($connection, '已认证，无需重复操作');
        return;
    }

    $token  = $msg['token'] ?? '';
    $userId = verifyToken($token);

    if (!$userId) {
        $connection->send(json_encode([
            'type' => 'auth_fail',
            'msg'  => '认证失败，请重新登录',
        ], JSON_UNESCAPED_UNICODE));
        return;
    }

    $userInfo = getUserInfo($userId);
    if (!$userInfo) {
        $connection->send(json_encode([
            'type' => 'auth_fail',
            'msg'  => '用户不存在或已被禁用',
        ], JSON_UNESCAPED_UNICODE));
        return;
    }

    // 踢掉同一用户的旧连接
    $toKick = [];
    foreach ($connections as $id => $existingConn) {
        if ($existingConn->userId === $userId && $id !== $connection->id) {
            $toKick[] = $existingConn;
        }
    }
    foreach ($toKick as $existingConn) {
        echo "[Auth] Kick old connection #{$existingConn->id} for userId={$userId}\n";
        safeClose($existingConn, '您在其他设备上登录了');
    }

    $connection->userId   = $userId;
    $connection->userInfo = $userInfo;

    $connection->send(json_encode([
        'type' => 'auth_success',
        'user' => $userInfo,
    ], JSON_UNESCAPED_UNICODE));

    broadcastOnlineCount();
    echo "[Auth] #{$connection->id} => userId={$userId}\n";
}

/**
 * 处理聊天消息
 */
function handleMessage(TcpConnection $connection, array $msg): void
{
    // 未登录
    if (!$connection->userId) {
        sendError($connection, '请先登录');
        return;
    }

    // 频率限制
    if (!checkRateLimit($connection)) {
        sendError($connection, '发送太快了，请稍后再试');
        return;
    }

    // 消息类型
    $msgType = $msg['msg_type'] ?? 'text';
    if (!in_array($msgType, ALLOWED_MSG_TYPES, true)) {
        $msgType = 'text';
    }

    $content   = trim($msg['content'] ?? '');
    $mediaUrl  = trim($msg['media_url'] ?? '');
    $thumbUrl  = trim($msg['thumb_url'] ?? '');
    $mediaInfo = $msg['media_info'] ?? null;

    // ---- 文本消息校验 ----
    if ($msgType === 'text') {
        if (empty($content)) {
            sendError($connection, '消息内容不能为空');
            return;
        }
        if (mb_strlen($content) > MSG_MAX_LENGTH) {
            sendError($connection, '消息内容过长，最多' . MSG_MAX_LENGTH . '字');
            return;
        }
    }

    // ---- 多媒体消息校验 ----
    if (in_array($msgType, ['image', 'video', 'voice'], true) && empty($mediaUrl)) {
        sendError($connection, '媒体文件不能为空');
        return;
    }

    // XSS 净化
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    // mediaInfo 格式校验
    if ($mediaInfo !== null && !is_array($mediaInfo)) {
        $mediaInfo = null;
    }

    // 保存到数据库
    $msgId = saveMessage($connection->userId, $content, $msgType, $mediaUrl, $thumbUrl, $mediaInfo);

    // 广播给所有人
    $broadcastData = [
        'type'       => 'message',
        'id'         => $msgId,
        'user_id'    => $connection->userId,
        'user_code'  => $connection->userInfo['user_code'] ?? '',
        'nickname'   => $connection->userInfo['nickname'] ?? '',
        'avatar'     => $connection->userInfo['avatar'] ?? '',
        'msg_type'   => $msgType,
        'content'    => $content,
        'media_url'  => $mediaUrl,
        'thumb_url'  => $thumbUrl,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if ($mediaInfo) {
        $broadcastData['media_info'] = $mediaInfo;
    }
    broadcast($broadcastData);
}

/**
 * 处理心跳
 */
function handlePing(TcpConnection $connection): void
{
    $connection->lastActiveTime = time();
    $connection->send(json_encode(['type' => 'pong']));
}

// =====================================================================
//  WebSocket 服务
// =====================================================================

$ws = new Worker('websocket://0.0.0.0:8383');
$ws->count = 1; // 单进程（聊天室共享连接池）
$ws->name  = 'GoHomeChatServer';

// ----- onWorkerStart -----
$ws->onWorkerStart = function () {
    // 定时清理：认证超时 & 空闲连接
    Timer::add(CLEANUP_INTERVAL_SEC, function () {
        global $connections;
        $now = time();

        $toClean = [];
        foreach ($connections as $id => $conn) {
            // 未认证超时
            if (!$conn->userId && ($now - $conn->connectTime) > AUTH_TIMEOUT_SEC) {
                $toClean[] = ['conn' => $conn, 'reason' => '认证超时，连接已断开'];
                continue;
            }
            // 已认证但空闲超时
            if ($conn->userId && ($now - $conn->lastActiveTime) > IDLE_TIMEOUT_SEC) {
                $toClean[] = ['conn' => $conn, 'reason' => '长时间未活动，连接已断开'];
                continue;
            }
        }

        foreach ($toClean as $item) {
            safeClose($item['conn'], $item['reason']);
        }

        if (count($toClean) > 0) {
            broadcastOnlineCount();
            echo "[Cleanup] Removed " . count($toClean) . " connections, active=" . count($connections) . "\n";
        }
    });

    echo "[ChatServer] Started. max_conn=" . MAX_CONNECTIONS
        . " rate_limit=" . RATE_LIMIT_MESSAGES . "/" . RATE_LIMIT_WINDOW . "s\n";
};

// ----- onConnect -----
$ws->onConnect = function (TcpConnection $connection) use (&$connections, &$ipConnCount) {
    $now = time();

    // 总连接数检查
    if (count($connections) >= MAX_CONNECTIONS) {
        echo "[Reject] Max connections reached (" . MAX_CONNECTIONS . ")\n";
        sendError($connection, '服务器繁忙，请稍后再试');
        $connection->close();
        return;
    }

    // IP 连接数检查
    $ip = getConnIp($connection);
    $ipConnCount[$ip] = ($ipConnCount[$ip] ?? 0) + 1;
    if ($ipConnCount[$ip] > MAX_CONN_PER_IP) {
        echo "[Reject] Too many connections from IP {$ip}\n";
        $ipConnCount[$ip]--;
        sendError($connection, '连接过多，请关闭多余窗口');
        $connection->close();
        return;
    }

    // 初始化连接属性
    $connections[$connection->id] = $connection;
    $connection->userId            = null;
    $connection->userInfo          = null;
    $connection->connIp            = $ip;
    $connection->connectTime       = $now;
    $connection->lastActiveTime    = $now;
    $connection->rateLimitMessages = [];
    $connection->cleanedUp         = false;

    echo "[Connect] #{$connection->id} from {$ip} (total=" . count($connections) . ")\n";
};

// ----- onMessage -----
$ws->onMessage = function (TcpConnection $connection, $data) {
    $msg = json_decode($data, true);
    if (!$msg || !isset($msg['type'])) {
        return;
    }

    $connection->lastActiveTime = time();

    switch ($msg['type']) {
        case 'auth':
            handleAuth($connection, $msg);
            break;
        case 'message':
            handleMessage($connection, $msg);
            break;
        case 'ping':
            handlePing($connection);
            break;
    }
};

// ----- onClose -----
$ws->onClose = function (TcpConnection $connection) use (&$connections, &$ipConnCount) {
    $userId = $connection->userId ?? null;

    // 仅在 safeClose 未清理时处理
    if (!($connection->cleanedUp ?? false)) {
        $ip = $connection->connIp ?? '';
        if ($ip && isset($ipConnCount[$ip])) {
            $ipConnCount[$ip]--;
            if ($ipConnCount[$ip] <= 0) {
                unset($ipConnCount[$ip]);
            }
        }
        unset($connections[$connection->id]);
    }

    echo "[Close] #{$connection->id}" . ($userId ? " userId={$userId}" : '')
        . " (total=" . count($connections) . ")\n";
    broadcastOnlineCount();
};

// ----- onError -----
$ws->onError = function (TcpConnection $connection, $code, $msg) {
    echo "[Error] #{$connection->id}: [{$code}] {$msg}\n";
};

// =====================================================================
//  内部通信 Worker（接收 API 的推送指令）
// =====================================================================

$internal = new Worker('text://0.0.0.0:7272');
$internal->name = 'GoHomeInternal';

$internal->onMessage = function (TcpConnection $connection, $data) use (&$connections) {
    $cmd = json_decode($data, true);
    if (!$cmd || !isset($cmd['cmd'])) {
        return;
    }

    // 处理 send_to_user 指令
    if ($cmd['cmd'] === 'send_to_user' && isset($cmd['user_id'], $cmd['data'])) {
        $userId = (int)$cmd['user_id'];
        $pushData = $cmd['data'];
        
        // 遍历所有连接，找到目标用户并发送
        foreach ($connections as $conn) {
            if (isset($conn->userId) && $conn->userId === $userId) {
                $conn->send($pushData);
                echo "[Internal] Pushed to userId={$userId}\n";
            }
        }
    }
};

Worker::runAll();
