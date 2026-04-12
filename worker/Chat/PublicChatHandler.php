<?php
declare(strict_types=1);

namespace worker\Chat;

use Workerman\Connection\TcpConnection;

/**
 * 公共聊天室消息处理
 */
class PublicChatHandler
{
    private ConnectionManager $cm;

    public function __construct(ConnectionManager $cm)
    {
        $this->cm = $cm;
    }

    public function handle(TcpConnection $connection, array $msg): void
    {
        if (!MessageValidator::requireAuth($connection)) return;
        if (!MessageValidator::checkRateLimit($connection)) return;

        $parsed = MessageValidator::parseAndValidate($connection, $msg);
        if ($parsed === null) return;

        $msgId = MessageRepository::savePublic(
            $connection->userId,
            $parsed['content'],
            $parsed['msgType'],
            $parsed['mediaUrl'],
            $parsed['thumbUrl'],
            $parsed['mediaInfo'],
        );

        $broadcastData = [
            'type'       => 'message',
            'id'         => $msgId,
            'user_id'    => $connection->userId,
            'user_code'  => $connection->userInfo['user_code'] ?? '',
            'nickname'   => $connection->userInfo['nickname'] ?? '',
            'avatar'     => $connection->userInfo['avatar'] ?? '',
            'msg_type'   => $parsed['msgType'],
            'content'    => $parsed['content'],
            'media_url'  => $parsed['mediaUrl'],
            'thumb_url'  => $parsed['thumbUrl'],
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($parsed['mediaInfo']) {
            $broadcastData['media_info'] = $parsed['mediaInfo'];
        }

        $this->cm->broadcast($broadcastData);
    }
}
