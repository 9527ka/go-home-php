<?php
declare(strict_types=1);

namespace worker\Chat;

use think\facade\Db;
use Workerman\Connection\TcpConnection;

/**
 * 私聊消息处理
 */
class PrivateChatHandler
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

        $clientMsgId = isset($msg['client_msg_id']) ? (string)$msg['client_msg_id'] : null;

        $toId = (int)($msg['to_id'] ?? 0);
        if ($toId <= 0) {
            MessageValidator::sendError($connection, '接收者 ID 无效', 'INVALID_RECEIVER', $clientMsgId);
            return;
        }

        // 验证好友关系 & 全局禁言/封禁状态
        try {
            $isFriend = Db::table('friendships')
                ->where('user_id', $connection->userId)
                ->where('friend_id', $toId)
                ->find();
            if (!$isFriend) {
                MessageValidator::sendError($connection, '对方不是您的好友', 'NOT_FRIEND', $clientMsgId);
                return;
            }

            // users.status: 1=正常 2=禁言 3=封禁
            $userStatus = (int)(Db::table('users')->where('id', $connection->userId)->value('status') ?? 1);
            if ($userStatus === 3) {
                MessageValidator::sendError($connection, '账号已被封禁', 'USER_BANNED', $clientMsgId);
                return;
            }
            if ($userStatus === 2) {
                MessageValidator::sendError($connection, '您已被禁言', 'USER_MUTED', $clientMsgId);
                return;
            }
        } catch (\Exception $e) {
            echo "[DB Error] checkFriendship: {$e->getMessage()}\n";
            MessageValidator::sendError($connection, '服务器错误', 'SERVER_ERROR', $clientMsgId);
            return;
        }

        $parsed = MessageValidator::parseAndValidate($connection, $msg);
        if ($parsed === null) return;

        $now   = date('Y-m-d H:i:s');
        $msgId = MessageRepository::savePrivate(
            $connection->userId, $toId,
            $parsed['content'], $parsed['msgType'],
            $parsed['mediaUrl'], $parsed['thumbUrl'], $parsed['mediaInfo'],
        );

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
            'msg_type'       => $parsed['msgType'],
            'content'        => $parsed['content'],
            'media_url'      => $parsed['mediaUrl'],
            'thumb_url'      => $parsed['thumbUrl'],
            'created_at'     => $now,
        ];
        if ($parsed['mediaInfo']) {
            $pushData['media_info'] = $parsed['mediaInfo'];
        }
        if ($clientMsgId !== null && $clientMsgId !== '') {
            $pushData['client_msg_id'] = $clientMsgId;
        }

        $delivered = $this->cm->sendToUser($toId, $pushData);
        $this->cm->sendToUser($connection->userId, $pushData);

        // 离线 APNs 推送
        if (!$delivered) {
            $this->pushOffline($connection, $toId, $parsed);
        }

        echo "[PM] {$connection->userId} => {$toId}: {$parsed['msgType']}\n";
    }

    private function pushOffline(TcpConnection $connection, int $toId, array $parsed): void
    {
        try {
            $isMuted = Db::table('conversation_mutes')
                ->where('user_id', $toId)
                ->where('target_id', $connection->userId)
                ->where('target_type', 'private')
                ->find();
            if (!$isMuted) {
                $senderName = $connection->userInfo['nickname'] ?? '';
                $rawContent = html_entity_decode($parsed['content'], ENT_QUOTES, 'UTF-8');
                $preview    = ($parsed['msgType'] === 'text') ? mb_substr($rawContent, 0, 50) : '[' . $parsed['msgType'] . ']';
                \app\common\service\ApnsPushService::sendToUser($toId, $senderName, $preview, [
                    'type'    => 'private_message',
                    'from_id' => $connection->userId,
                ]);
            }
        } catch (\Throwable $e) {
            echo "[APNs] PM push failed for userId={$toId}: {$e->getMessage()}\n";
        }
    }
}
