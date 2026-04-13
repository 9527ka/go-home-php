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
        $memberRow = null;
        try {
            $memberRow = Db::table('group_members')
                ->where('group_id', $groupId)
                ->where('user_id', $connection->userId)
                ->find();
            if (!$memberRow) {
                MessageValidator::sendError($connection, '您不是该群成员');
                return;
            }

            $group = Db::table('groups')
                ->field('id, name, avatar, status, banned, all_muted')
                ->where('id', $groupId)
                ->find();
            if (!$group || $group['status'] != 1) {
                MessageValidator::sendError($connection, '群组不存在或已解散');
                return;
            }

            // 用户全局禁言/封禁（users.status: 1=正常 2=禁言 3=封禁）
            $userStatus = (int)(Db::table('users')->where('id', $connection->userId)->value('status') ?? 1);
            if ($userStatus === 3) {
                MessageValidator::sendError($connection, '账号已被封禁');
                return;
            }
            if ($userStatus === 2) {
                MessageValidator::sendError($connection, '您已被禁言');
                return;
            }

            // 群整体被管理员封禁
            if ((int)($group['banned'] ?? 0) === 1) {
                MessageValidator::sendError($connection, '群聊已被限制');
                return;
            }

            // 群全员禁言（仅管理员/群主可发）
            $memberRole = (int)($memberRow['role'] ?? 0);
            if ((int)($group['all_muted'] ?? 0) === 1 && $memberRole < 1) {
                MessageValidator::sendError($connection, '群聊已全员禁言');
                return;
            }

            // 单人群内禁言
            $mutedUntil = $memberRow['muted_until'] ?? null;
            if ($mutedUntil && strtotime($mutedUntil) > time()) {
                MessageValidator::sendError($connection, '您已在该群被禁言');
                return;
            }
        } catch (\Exception $e) {
            echo "[DB Error] checkGroupMember: {$e->getMessage()}\n";
            MessageValidator::sendError($connection, '服务器错误');
            return;
        }

        $parsed = MessageValidator::parseAndValidate($connection, $msg);
        if ($parsed === null) return;

        // @提及：仅取群成员中的有效用户
        $mentions = [];
        $rawMentions = $msg['mentions'] ?? [];
        if (is_array($rawMentions) && !empty($rawMentions)) {
            $candidates = array_unique(array_map('intval', $rawMentions));
            try {
                $validIds = Db::table('group_members')
                    ->where('group_id', $groupId)
                    ->whereIn('user_id', $candidates)
                    ->column('user_id');
                $mentions = array_values(array_map('intval', $validIds));
            } catch (\Exception $e) {
                echo "[DB Error] validateMentions: {$e->getMessage()}\n";
            }
        }

        $now   = date('Y-m-d H:i:s');
        $msgId = MessageRepository::saveGroup(
            $connection->userId, $groupId,
            $parsed['content'], $parsed['msgType'],
            $parsed['mediaUrl'], $parsed['thumbUrl'], $parsed['mediaInfo'],
            $mentions,
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
        if (!empty($mentions)) {
            $pushData['mentions'] = $mentions;
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

        // 离线 APNs 推送（排除免打扰；被@的人即使免打扰也推送）
        if (!empty($offlineMembers)) {
            $this->pushOfflineMembers($connection, $group, $groupId, $offlineMembers, $parsed, $mentions);
        }

        echo "[Group] {$connection->userId} => group#{$groupId}: {$parsed['msgType']} (members=" . count($memberIds) . ")\n";
    }

    private function pushOfflineMembers(TcpConnection $connection, array $group, int $groupId, array $offlineMembers, array $parsed, array $mentions = []): void
    {
        try {
            $mutedUserIds = Db::table('conversation_mutes')
                ->whereIn('user_id', $offlineMembers)
                ->where('target_id', $groupId)
                ->where('target_type', 'group')
                ->column('user_id');
            // 被@的成员忽略免打扰
            $pushMembers = array_unique(array_merge(
                array_diff($offlineMembers, $mutedUserIds),
                array_intersect($offlineMembers, $mentions),
            ));

            if (!empty($pushMembers)) {
                $senderName = $connection->userInfo['nickname'] ?? '';
                $groupName  = $group['name'] ?? '';
                $rawContent = html_entity_decode($parsed['content'], ENT_QUOTES, 'UTF-8');
                $preview    = ($parsed['msgType'] === 'text') ? mb_substr($rawContent, 0, 50) : '[' . $parsed['msgType'] . ']';
                $title      = $groupName ?: $senderName;
                $body       = $senderName . ': ' . $preview;

                // 被@的成员单独推送，前缀 [有人@你]
                $mentionedTargets = array_values(array_intersect($pushMembers, $mentions));
                $normalTargets    = array_values(array_diff($pushMembers, $mentionedTargets));

                if (!empty($mentionedTargets)) {
                    \app\common\service\ApnsPushService::sendToUsers($mentionedTargets, $title, '[有人@你] ' . $body, [
                        'type'     => 'group_message',
                        'group_id' => $groupId,
                        'mentioned' => true,
                    ]);
                }
                if (!empty($normalTargets)) {
                    \app\common\service\ApnsPushService::sendToUsers($normalTargets, $title, $body, [
                        'type'     => 'group_message',
                        'group_id' => $groupId,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            echo "[APNs] Group push failed for group#{$groupId}: {$e->getMessage()}\n";
        }
    }
}
