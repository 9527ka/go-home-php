<?php
declare(strict_types=1);

namespace worker\Chat;

use Workerman\Connection\TcpConnection;

/**
 * 统一消息校验（消除 3 处重复验证逻辑）
 */
class MessageValidator
{
    public const MSG_MAX_LENGTH    = 500;
    // 注意：voice_call 不在此白名单中——通话记录消息仅由服务端 CallSignalingHandler 在通话结束时写入，
    // 客户端若通过 private_message 伪造 voice_call 会被当作 text 处理
    public const ALLOWED_MSG_TYPES = ['text', 'image', 'video', 'voice', 'red_packet', 'contact_card'];
    public const RATE_LIMIT_MESSAGES = 10;
    public const RATE_LIMIT_WINDOW   = 10;

    /**
     * 检查连接是否已认证
     */
    public static function requireAuth(TcpConnection $connection): bool
    {
        if (!$connection->userId) {
            self::sendError($connection, '请先登录');
            return false;
        }
        return true;
    }

    /**
     * 检查消息频率限制
     */
    public static function checkRateLimit(TcpConnection $connection): bool
    {
        $now = time();

        if (!isset($connection->rateLimitMessages)) {
            $connection->rateLimitMessages = [];
        }

        $connection->rateLimitMessages = array_filter(
            $connection->rateLimitMessages,
            fn($ts) => ($now - $ts) < self::RATE_LIMIT_WINDOW
        );

        if (count($connection->rateLimitMessages) >= self::RATE_LIMIT_MESSAGES) {
            self::sendError($connection, '发送太快了，请稍后再试');
            return false;
        }

        $connection->rateLimitMessages[] = $now;
        return true;
    }

    /**
     * 解析并校验消息内容，返回净化后的数据，失败返回 null
     *
     * @return array{msgType: string, content: string, mediaUrl: string, thumbUrl: string, mediaInfo: ?array}|null
     */
    public static function parseAndValidate(TcpConnection $connection, array $msg): ?array
    {
        $msgType = $msg['msg_type'] ?? 'text';
        if (!in_array($msgType, self::ALLOWED_MSG_TYPES, true)) {
            $msgType = 'text';
        }

        $content   = trim($msg['content'] ?? '');
        $mediaUrl  = trim($msg['media_url'] ?? '');
        $thumbUrl  = trim($msg['thumb_url'] ?? '');
        $mediaInfo = $msg['media_info'] ?? null;

        // 文本消息校验
        if ($msgType === 'text') {
            if (empty($content)) {
                self::sendError($connection, '消息内容不能为空');
                return null;
            }
            if (mb_strlen($content) > self::MSG_MAX_LENGTH) {
                self::sendError($connection, '消息内容过长，最多' . self::MSG_MAX_LENGTH . '字');
                return null;
            }
        }

        // 多媒体消息校验
        if (in_array($msgType, ['image', 'video', 'voice'], true) && empty($mediaUrl)) {
            self::sendError($connection, '媒体文件不能为空');
            return null;
        }

        // XSS 净化
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // mediaInfo 格式校验
        if ($mediaInfo !== null && !is_array($mediaInfo)) {
            $mediaInfo = null;
        }

        return [
            'msgType'   => $msgType,
            'content'   => $content,
            'mediaUrl'  => $mediaUrl,
            'thumbUrl'  => $thumbUrl,
            'mediaInfo' => $mediaInfo,
        ];
    }

    /**
     * 发送错误到客户端
     * @param string      $msg         人类可读错误文案（兜底用）
     * @param string|null $errorCode   机器可识别的错误码（如 NOT_FRIEND / GROUP_ALL_MUTED / GROUP_MEMBER_MUTED 等）
     * @param string|null $clientMsgId 前端乐观消息的 client_msg_id，用于将失败状态落到具体消息气泡
     */
    public static function sendError(TcpConnection $connection, string $msg, ?string $errorCode = null, ?string $clientMsgId = null): void
    {
        $payload = [
            'type' => 'error',
            'msg'  => $msg,
        ];
        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }
        if ($clientMsgId !== null && $clientMsgId !== '') {
            $payload['client_msg_id'] = $clientMsgId;
        }
        $connection->send(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
