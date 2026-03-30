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
const IDLE_TIMEOUT_SEC     = 3600;  // 空闲连接超时（1小时）
const RATE_LIMIT_MESSAGES  = 10;    // 消息频率限制：N 条
const RATE_LIMIT_WINDOW    = 10;    // 频率限制时间窗口（秒）
const CLEANUP_INTERVAL_SEC = 60;    // 清理检查间隔（秒）
const MSG_MAX_LENGTH       = 500;   // 文本消息最大长度
const ALLOWED_MSG_TYPES    = ['text', 'image', 'video', 'voice', 'red_packet'];
const RED_PACKET_REFUND_INTERVAL = 60; // 红包过期退回检查间隔（秒）

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
 * 保存私聊消息到数据库
 */
function savePrivateMessage(int $fromId, int $toId, string $content, string $msgType = 'text', string $mediaUrl = '', string $thumbUrl = '', ?array $mediaInfo = null): ?int
{
    try {
        $data = [
            'from_id'    => $fromId,
            'to_id'      => $toId,
            'msg_type'   => $msgType,
            'content'    => $content,
            'media_url'  => $mediaUrl,
            'thumb_url'  => $thumbUrl,
            'media_info' => $mediaInfo ? json_encode($mediaInfo, JSON_UNESCAPED_UNICODE) : null,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return (int)Db::table('private_messages')->insertGetId($data);
    } catch (\Exception $e) {
        echo "[DB Error] savePrivateMessage: {$e->getMessage()}\n";
        return null;
    }
}

/**
 * 保存群聊消息到数据库
 */
function saveGroupMessage(int $userId, int $groupId, string $content, string $msgType = 'text', string $mediaUrl = '', string $thumbUrl = '', ?array $mediaInfo = null): ?int
{
    try {
        $data = [
            'user_id'    => $userId,
            'group_id'   => $groupId,
            'msg_type'   => $msgType,
            'content'    => $content,
            'media_url'  => $mediaUrl,
            'thumb_url'  => $thumbUrl,
            'media_info' => $mediaInfo ? json_encode($mediaInfo, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return (int)Db::table('group_messages')->insertGetId($data);
    } catch (\Exception $e) {
        echo "[DB Error] saveGroupMessage: {$e->getMessage()}\n";
        return null;
    }
}

/**
 * 处理私聊消息
 */
function handlePrivateMessage(TcpConnection $connection, array $msg): void
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

    $toId = (int)($msg['to_id'] ?? 0);
    if ($toId <= 0) {
        sendError($connection, '接收者 ID 无效');
        return;
    }

    // 验证好友关系
    try {
        $isFriend = Db::table('friendships')
            ->where('user_id', $connection->userId)
            ->where('friend_id', $toId)
            ->find();
        if (!$isFriend) {
            sendError($connection, '对方不是您的好友');
            return;
        }
    } catch (\Exception $e) {
        echo "[DB Error] checkFriendship: {$e->getMessage()}\n";
        sendError($connection, '服务器错误');
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
    $now = date('Y-m-d H:i:s');
    $msgId = savePrivateMessage($connection->userId, $toId, $content, $msgType, $mediaUrl, $thumbUrl, $mediaInfo);

    // 构建推送数据
    $pushData = [
        'type'           => 'private_message',
        'id'             => $msgId,
        'from_id'        => $connection->userId,
        'to_id'          => $toId,
        'user_id'        => $connection->userId,
        'nickname'       => $connection->userInfo['nickname'] ?? '',
        'avatar'         => $connection->userInfo['avatar'] ?? '',
        'user_code'      => $connection->userInfo['user_code'] ?? '',
        'from_nickname'  => $connection->userInfo['nickname'] ?? '',
        'from_avatar'    => $connection->userInfo['avatar'] ?? '',
        'msg_type'       => $msgType,
        'content'        => $content,
        'media_url'      => $mediaUrl,
        'thumb_url'      => $thumbUrl,
        'created_at'     => $now,
    ];
    if ($mediaInfo) {
        $pushData['media_info'] = $mediaInfo;
    }

    // 发送给接收者
    sendToUser($toId, $pushData);
    // 也发回给发送者（确认消息已发送，并用于会话列表更新）
    sendToUser($connection->userId, $pushData);

    echo "[PM] {$connection->userId} => {$toId}: {$msgType}\n";
}

/**
 * 处理群聊消息
 */
function handleGroupMessage(TcpConnection $connection, array $msg): void
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

    $groupId = (int)($msg['group_id'] ?? 0);
    if ($groupId <= 0) {
        sendError($connection, '群组 ID 无效');
        return;
    }

    // 验证群成员身份 & 群组状态
    $group = null;
    try {
        $isMember = Db::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $connection->userId)
            ->find();
        if (!$isMember) {
            sendError($connection, '您不是该群成员');
            return;
        }

        $group = Db::table('groups')
            ->field('id, name, avatar, status')
            ->where('id', $groupId)
            ->find();
        if (!$group || $group['status'] != 1) {
            sendError($connection, '群组不存在或已解散');
            return;
        }
    } catch (\Exception $e) {
        echo "[DB Error] checkGroupMember: {$e->getMessage()}\n";
        sendError($connection, '服务器错误');
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
    $now = date('Y-m-d H:i:s');
    $msgId = saveGroupMessage($connection->userId, $groupId, $content, $msgType, $mediaUrl, $thumbUrl, $mediaInfo);

    // 获取群所有成员 ID
    $memberIds = [];
    try {
        $memberIds = Db::table('group_members')
            ->where('group_id', $groupId)
            ->column('user_id');
    } catch (\Exception $e) {
        echo "[DB Error] getGroupMembers: {$e->getMessage()}\n";
        $memberIds = [$connection->userId]; // 至少发回给自己
    }

    // 构建推送数据
    $pushData = [
        'type'           => 'group_message',
        'id'             => $msgId,
        'group_id'       => $groupId,
        'group_name'     => $group['name'] ?? '',
        'group_avatar'   => $group['avatar'] ?? '',
        'user_id'        => $connection->userId,
        'nickname'       => $connection->userInfo['nickname'] ?? '',
        'avatar'         => $connection->userInfo['avatar'] ?? '',
        'user_code'      => $connection->userInfo['user_code'] ?? '',
        'msg_type'       => $msgType,
        'content'        => $content,
        'media_url'      => $mediaUrl,
        'thumb_url'      => $thumbUrl,
        'created_at'     => $now,
    ];
    if ($mediaInfo) {
        $pushData['media_info'] = $mediaInfo;
    }

    // 发送给群内所有在线成员
    foreach ($memberIds as $memberId) {
        sendToUser((int)$memberId, $pushData);
    }

    echo "[Group] {$connection->userId} => group#{$groupId}: {$msgType} (members=" . count($memberIds) . ")\n";
}

/**
 * 处理红包消息（公共聊天室）
 * 前端发红包后，通过 WebSocket 广播红包卡片消息
 */
function handleRedPacketMessage(TcpConnection $connection, array $msg): void
{
    if (!$connection->userId) {
        sendError($connection, '请先登录');
        return;
    }

    $redPacketId = (int)($msg['red_packet_id'] ?? 0);
    if ($redPacketId <= 0) {
        sendError($connection, '红包ID无效');
        return;
    }

    // 查询红包信息
    try {
        $packet = Db::table('red_packets')
            ->where('id', $redPacketId)
            ->where('user_id', $connection->userId)
            ->find();
        if (!$packet) {
            sendError($connection, '红包不存在');
            return;
        }
    } catch (\Exception $e) {
        echo "[DB Error] handleRedPacketMessage: {$e->getMessage()}\n";
        sendError($connection, '服务器错误');
        return;
    }

    $greeting = $packet['greeting'] ?: '恭喜发财，大吉大利';
    $targetType = (int)$packet['target_type'];

    $broadcastData = [
        'type'           => 'red_packet',
        'red_packet_id'  => $redPacketId,
        'user_id'        => $connection->userId,
        'nickname'       => $connection->userInfo['nickname'] ?? '',
        'avatar'         => $connection->userInfo['avatar'] ?? '',
        'greeting'       => $greeting,
        'total_count'    => (int)$packet['total_count'],
        'target_type'    => $targetType,
        'target_id'      => (int)$packet['target_id'],
        'created_at'     => date('Y-m-d H:i:s'),
    ];

    // 根据目标类型广播
    $contentJson = json_encode(['red_packet_id' => $redPacketId, 'greeting' => $greeting], JSON_UNESCAPED_UNICODE);

    if ($targetType === 1) {
        // 公共聊天室红包 — 广播给所有人
        $msgId = saveMessage($connection->userId, $contentJson, 'red_packet');
        if ($msgId) $broadcastData['id'] = $msgId;
        broadcast($broadcastData);
    } elseif ($targetType === 2) {
        // 私聊红包
        $toId = (int)$packet['target_id'];
        $msgId = savePrivateMessage($connection->userId, $toId, $contentJson, 'red_packet');
        if ($msgId) $broadcastData['id'] = $msgId;
        $broadcastData['type'] = 'private_message';
        $broadcastData['msg_type'] = 'red_packet';
        $broadcastData['from_id'] = $connection->userId;
        $broadcastData['to_id'] = $toId;
        $broadcastData['content'] = $contentJson;
        sendToUser($toId, $broadcastData);
        sendToUser($connection->userId, $broadcastData);
    } elseif ($targetType === 3) {
        // 群聊红包
        $groupId = (int)$packet['target_id'];
        $msgId = saveGroupMessage($connection->userId, $groupId, $contentJson, 'red_packet');
        if ($msgId) $broadcastData['id'] = $msgId;
        $broadcastData['type'] = 'group_message';
        $broadcastData['msg_type'] = 'red_packet';
        $broadcastData['group_id'] = $groupId;
        $broadcastData['content'] = $contentJson;

        try {
            $memberIds = Db::table('group_members')
                ->where('group_id', $groupId)
                ->column('user_id');
        } catch (\Exception $e) {
            $memberIds = [$connection->userId];
        }
        foreach ($memberIds as $memberId) {
            sendToUser((int)$memberId, $broadcastData);
        }
    }

    echo "[RedPacket] userId={$connection->userId} sent red_packet#{$redPacketId} target_type={$targetType}\n";
}

/**
 * 广播红包被领取通知
 */
function broadcastRedPacketClaimed(int $redPacketId, int $claimUserId, float $amount, int $targetType, int $targetId): void
{
    try {
        $user = getUserInfo($claimUserId);
        $nickname = $user['nickname'] ?? '';
    } catch (\Exception $e) {
        $nickname = '';
    }

    $data = [
        'type'          => 'red_packet_claimed',
        'red_packet_id' => $redPacketId,
        'user_id'       => $claimUserId,
        'nickname'      => $nickname,
        'amount'        => $amount,
        'target_type'   => $targetType,
        'target_id'     => $targetId,
    ];

    if ($targetType === 1) {
        broadcast($data);
    } elseif ($targetType === 2) {
        // 私聊红包：通知发送者和领取者
        try {
            $senderId = (int)Db::table('red_packets')->where('id', $redPacketId)->value('user_id');
            if ($senderId) {
                sendToUser($senderId, $data);
            }
            sendToUser($claimUserId, $data);
        } catch (\Exception $e) {
            echo "[DB Error] broadcastRedPacketClaimed private: {$e->getMessage()}\n";
        }
    } elseif ($targetType === 3) {
        try {
            $memberIds = Db::table('group_members')
                ->where('group_id', $targetId)
                ->column('user_id');
            foreach ($memberIds as $memberId) {
                sendToUser((int)$memberId, $data);
            }
        } catch (\Exception $e) {
            echo "[DB Error] broadcastRedPacketClaimed: {$e->getMessage()}\n";
        }
    }
}

/**
 * 退回过期红包
 */
function refundExpiredRedPackets(): void
{
    try {
        $now = date('Y-m-d H:i:s');
        $expiredPackets = Db::table('red_packets')
            ->where('status', 1) // ACTIVE
            ->where('expire_at', '<', $now)
            ->where('remaining_amount', '>', 0)
            ->select();

        foreach ($expiredPackets as $packet) {
            Db::startTrans();
            try {
                // 退回剩余金额给发送者
                $remaining = (float)$packet['remaining_amount'];
                if ($remaining > 0) {
                    $affected = Db::table('wallets')
                        ->where('user_id', $packet['user_id'])
                        ->inc('balance', $remaining)
                        ->update();

                    if ($affected) {
                        // 获取退回后余额
                        $wallet = Db::table('wallets')
                            ->where('user_id', $packet['user_id'])
                            ->find();

                        if ($wallet) {
                            // 记录退回流水
                            Db::table('wallet_transactions')->insert([
                                'user_id'        => $packet['user_id'],
                                'type'           => 8, // RED_PACKET_REFUND
                                'amount'         => $remaining,
                                'balance_before' => (float)$wallet['balance'] - $remaining,
                                'balance_after'  => (float)$wallet['balance'],
                                'related_type'   => 'red_packet',
                                'related_id'     => $packet['id'],
                                'remark'         => '红包过期退回',
                                'created_at'     => $now,
                            ]);
                        }
                    }
                }

                // 更新红包状态为已过期
                Db::table('red_packets')
                    ->where('id', $packet['id'])
                    ->update([
                        'status'     => 3, // EXPIRED
                        'updated_at' => $now,
                    ]);

                Db::commit();
                echo "[RedPacket] Refunded expired packet#{$packet['id']}, amount={$remaining} to userId={$packet['user_id']}\n";
            } catch (\Exception $e) {
                Db::rollback();
                echo "[RedPacket] Refund failed for packet#{$packet['id']}: {$e->getMessage()}\n";
            }
        }
    } catch (\Exception $e) {
        echo "[RedPacket] refundExpiredRedPackets error: {$e->getMessage()}\n";
    }
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

    // 定时检查过期红包并退回余额
    Timer::add(RED_PACKET_REFUND_INTERVAL, function () {
        refundExpiredRedPackets();
    });

    // 服务端主动心跳：每30秒向所有已认证连接发送ping，防止中间层断开空闲连接
    Timer::add(30, function () {
        global $connections;
        $ping = json_encode(['type' => 'ping']);
        foreach ($connections as $conn) {
            if ($conn->userId ?? null) {
                $conn->send($ping);
            }
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
        case 'private_message':
            handlePrivateMessage($connection, $msg);
            break;
        case 'group_message':
            handleGroupMessage($connection, $msg);
            break;
        case 'red_packet':
            handleRedPacketMessage($connection, $msg);
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

    // 处理 red_packet_claimed 广播指令
    if ($cmd['cmd'] === 'red_packet_claimed' && isset($cmd['red_packet_id'])) {
        broadcastRedPacketClaimed(
            (int)$cmd['red_packet_id'],
            (int)($cmd['user_id'] ?? 0),
            (float)($cmd['amount'] ?? 0),
            (int)($cmd['target_type'] ?? 0),
            (int)($cmd['target_id'] ?? 0)
        );
    }
};

Worker::runAll();
