<?php
declare(strict_types=1);

namespace worker\Chat;

use think\facade\Db;
use Workerman\Connection\TcpConnection;

/**
 * 群聊消息处理
 */
class GroupChatHandler
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

        $groupId = (int)($msg['group_id'] ?? 0);
        if ($groupId <= 0) {
            MessageValidator::sendError($connection, '群组 ID 无效');
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
                MessageValidator::sendError($connection, '您不是该群成员');
                return;
            }

            $group = Db::table('groups')
                ->field('id, name, avatar, status')
                ->where('id', $groupId)
                ->find();
            if (!$group || $group['status'] != 1) {
                MessageValidator::sendError($connection, '群组不存在或已解散');
                return;
            }
        } catch (\Exception $e) {
            echo "[DB Error] checkGroupMember: {$e->getMessage()}\n";
            MessageValidator::sendError($connection, '服务器错误');
            return;
        }

        $parsed = MessageValidator::parseAndValidate($connection, $msg);
        if ($parsed === null) return;

        $now   = date('Y-m-d H:i:s');
        $msgId = MessageRepository::saveGroup(
            $connection->userId, $groupId,
            $parsed['content'], $parsed['msgType'],
            $parsed['mediaUrl'], $parsed['thumbUrl'], $parsed['mediaInfo'],
        );

        $memberIds = [];
        try {
            $memberIds = Db::table('group_members')
                ->where('group_id', $groupId)
                ->column('user_id');
        } catch (\Exception $e) {
            echo "[DB Error] getGroupMembers: {$e->getMessage()}\n";
            $memberIds = [$connection->userId];
        }

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
            'msg_type'       => $parsed['msgType'],
            'content'        => $parsed['content'],
            'media_url'      => $parsed['mediaUrl'],
            'thumb_url'      => $parsed['thumbUrl'],
            'created_at'     => $now,
        ];
        if ($parsed['mediaInfo']) {
            $pushData['media_info'] = $parsed['mediaInfo'];
        }

        // 发送给群内在线成员，收集离线成员
        $offlineMembers = [];
        foreach ($memberIds as $memberId) {
            $mid = (int)$memberId;
            if ($mid === $connection->userId) {
                $this->cm->sendToUser($mid, $pushData);
                continue;
            }
            if (!$this->cm->sendToUser($mid, $pushData)) {
                $offlineMembers[] = $mid;
            }
        }

        // 离线 APNs 推送（排除免打扰）
        if (!empty($offlineMembers)) {
            $this->pushOfflineMembers($connection, $group, $groupId, $offlineMembers, $parsed);
        }

        echo "[Group] {$connection->userId} => group#{$groupId}: {$parsed['msgType']} (members=" . count($memberIds) . ")\n";
    }

    private function pushOfflineMembers(TcpConnection $connection, array $group, int $groupId, array $offlineMembers, array $parsed): void
    {
        try {
            $mutedUserIds = Db::table('conversation_mutes')
                ->whereIn('user_id', $offlineMembers)
                ->where('target_id', $groupId)
                ->where('target_type', 'group')
                ->column('user_id');
            $pushMembers = array_diff($offlineMembers, $mutedUserIds);

            if (!empty($pushMembers)) {
                $senderName = $connection->userInfo['nickname'] ?? '';
                $groupName  = $group['name'] ?? '';
                $rawContent = html_entity_decode($parsed['content'], ENT_QUOTES, 'UTF-8');
                $preview    = ($parsed['msgType'] === 'text') ? mb_substr($rawContent, 0, 50) : '[' . $parsed['msgType'] . ']';
                $title      = $groupName ?: $senderName;
                $body       = $senderName . ': ' . $preview;
                \app\common\service\ApnsPushService::sendToUsers($pushMembers, $title, $body, [
                    'type'     => 'group_message',
                    'group_id' => $groupId,
                ]);
            }
        } catch (\Throwable $e) {
            echo "[APNs] Group push failed for group#{$groupId}: {$e->getMessage()}\n";
        }
    }
}
