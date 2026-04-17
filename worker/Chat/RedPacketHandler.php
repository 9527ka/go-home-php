<?php
declare(strict_types=1);

namespace worker\Chat;

use think\facade\Db;
use Workerman\Connection\TcpConnection;

/**
 * 红包消息处理
 */
class RedPacketHandler
{
    private ConnectionManager $cm;

    public function __construct(ConnectionManager $cm)
    {
        $this->cm = $cm;
    }

    /**
     * 处理红包发送消息
     */
    public function handle(TcpConnection $connection, array $msg): void
    {
        if (!MessageValidator::requireAuth($connection)) return;

        $clientMsgId = isset($msg['client_msg_id']) ? (string)$msg['client_msg_id'] : null;

        $redPacketId = (int)($msg['red_packet_id'] ?? 0);
        if ($redPacketId <= 0) {
            MessageValidator::sendError($connection, '红包ID无效', 'INVALID_RED_PACKET', $clientMsgId);
            return;
        }

        try {
            $packet = Db::table('red_packets')
                ->where('id', $redPacketId)
                ->where('user_id', $connection->userId)
                ->find();
            if (!$packet) {
                MessageValidator::sendError($connection, '红包不存在', 'RED_PACKET_NOT_FOUND', $clientMsgId);
                return;
            }
        } catch (\Exception $e) {
            echo "[DB Error] handleRedPacketMessage: {$e->getMessage()}\n";
            MessageValidator::sendError($connection, '服务器错误', 'SERVER_ERROR', $clientMsgId);
            return;
        }

        $greeting   = $packet['greeting'] ?: '恭喜发财，大吉大利';
        $targetType = (int)$packet['target_type'];

        // 发送前校验：用户全局状态 / 好友关系（私聊） / 群禁言（群聊）
        try {
            $userStatus = (int)(Db::table('users')->where('id', $connection->userId)->value('status') ?? 1);
            if ($userStatus === 3) {
                MessageValidator::sendError($connection, '账号已被封禁', 'USER_BANNED', $clientMsgId);
                return;
            }
            if ($userStatus === 2) {
                MessageValidator::sendError($connection, '您已被禁言', 'USER_MUTED', $clientMsgId);
                return;
            }

            if ($targetType === 2) {
                // 私聊红包：必须是好友
                $toId = (int)$packet['target_id'];
                $isFriend = Db::table('friendships')
                    ->where('user_id', $connection->userId)
                    ->where('friend_id', $toId)
                    ->find();
                if (!$isFriend) {
                    MessageValidator::sendError($connection, '对方不是您的好友', 'NOT_FRIEND', $clientMsgId);
                    return;
                }
            } elseif ($targetType === 3) {
                // 群聊红包：必须是成员 + 群未被封禁 + 全员禁言/单人禁言校验
                $groupId = (int)$packet['target_id'];
                $memberRow = Db::table('group_members')
                    ->where('group_id', $groupId)
                    ->where('user_id', $connection->userId)
                    ->find();
                if (!$memberRow) {
                    MessageValidator::sendError($connection, '您不是该群成员', 'NOT_GROUP_MEMBER', $clientMsgId);
                    return;
                }

                $group = Db::table('groups')
                    ->field('id, status, banned, all_muted')
                    ->where('id', $groupId)
                    ->find();
                if (!$group || (int)$group['status'] !== 1) {
                    MessageValidator::sendError($connection, '群组不存在或已解散', 'GROUP_NOT_FOUND', $clientMsgId);
                    return;
                }
                if ((int)($group['banned'] ?? 0) === 1) {
                    MessageValidator::sendError($connection, '群聊已被限制', 'GROUP_BANNED', $clientMsgId);
                    return;
                }

                $memberRole = (int)($memberRow['role'] ?? 0);
                if ((int)($group['all_muted'] ?? 0) === 1 && $memberRole < 1) {
                    MessageValidator::sendError($connection, '群聊已全员禁言', 'GROUP_ALL_MUTED', $clientMsgId);
                    return;
                }

                $mutedUntil = $memberRow['muted_until'] ?? null;
                if ($mutedUntil && strtotime($mutedUntil) > time()) {
                    MessageValidator::sendError($connection, '您已在该群被禁言', 'GROUP_MEMBER_MUTED', $clientMsgId);
                    return;
                }
            }
        } catch (\Exception $e) {
            echo "[DB Error] redPacket precheck: {$e->getMessage()}\n";
            MessageValidator::sendError($connection, '服务器错误', 'SERVER_ERROR', $clientMsgId);
            return;
        }

        $senderVipLevel = (string)($packet['sender_vip_level'] ?? 'normal');

        $broadcastData = [
            'type'             => 'red_packet',
            'red_packet_id'    => $redPacketId,
            'user_id'          => $connection->userId,
            'nickname'         => $connection->userInfo['nickname'] ?? '',
            'avatar'           => $connection->userInfo['avatar'] ?? '',
            'greeting'         => $greeting,
            'total_count'      => (int)$packet['total_count'],
            'target_type'      => $targetType,
            'target_id'        => (int)$packet['target_id'],
            'sender_vip_level' => $senderVipLevel,
            'created_at'       => date('Y-m-d H:i:s'),
        ];
        if ($clientMsgId !== null && $clientMsgId !== '') {
            $broadcastData['client_msg_id'] = $clientMsgId;
        }

        // 内容 JSON 中也嵌入 sender_vip_level，让历史消息也能正确渲染皮肤
        $contentJson = json_encode([
            'red_packet_id'    => $redPacketId,
            'greeting'         => $greeting,
            'sender_vip_level' => $senderVipLevel,
        ], JSON_UNESCAPED_UNICODE);

        if ($targetType === 1) {
            // 公共聊天室
            $broadcastData['msg_type'] = 'red_packet';
            $broadcastData['content']  = $contentJson;
            $msgId = MessageRepository::savePublic($connection->userId, $contentJson, 'red_packet');
            if ($msgId) $broadcastData['id'] = $msgId;
            $this->cm->broadcast($broadcastData);
        } elseif ($targetType === 2) {
            // 私聊
            $toId = (int)$packet['target_id'];
            $msgId = MessageRepository::savePrivate($connection->userId, $toId, $contentJson, 'red_packet');
            if ($msgId) $broadcastData['id'] = $msgId;
            $broadcastData['type']     = 'private_message';
            $broadcastData['msg_type'] = 'red_packet';
            $broadcastData['from_id']  = $connection->userId;
            $broadcastData['to_id']    = $toId;
            $broadcastData['content']  = $contentJson;
            $this->cm->sendToUser($toId, $broadcastData);
            $this->cm->sendToUser($connection->userId, $broadcastData);
        } elseif ($targetType === 3) {
            // 群聊
            $groupId = (int)$packet['target_id'];
            $msgId = MessageRepository::saveGroup($connection->userId, $groupId, $contentJson, 'red_packet');
            if ($msgId) $broadcastData['id'] = $msgId;
            $broadcastData['type']     = 'group_message';
            $broadcastData['msg_type'] = 'red_packet';
            $broadcastData['group_id'] = $groupId;
            $broadcastData['content']  = $contentJson;

            try {
                $memberIds = Db::table('group_members')
                    ->where('group_id', $groupId)
                    ->column('user_id');
            } catch (\Exception $e) {
                $memberIds = [$connection->userId];
            }
            foreach ($memberIds as $memberId) {
                $this->cm->sendToUser((int)$memberId, $broadcastData);
            }
        }

        echo "[RedPacket] userId={$connection->userId} sent red_packet#{$redPacketId} target_type={$targetType}\n";
    }

    /**
     * 广播红包被领取通知
     */
    public function broadcastClaimed(int $redPacketId, int $claimUserId, float $amount, int $targetType, int $targetId): void
    {
        $user = MessageRepository::getUserInfo($claimUserId);
        $nickname = $user['nickname'] ?? '';

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
            $this->cm->broadcast($data);
        } elseif ($targetType === 2) {
            try {
                $senderId = (int)Db::table('red_packets')->where('id', $redPacketId)->value('user_id');
                if ($senderId) $this->cm->sendToUser($senderId, $data);
                $this->cm->sendToUser($claimUserId, $data);
            } catch (\Exception $e) {
                echo "[DB Error] broadcastClaimed private: {$e->getMessage()}\n";
            }
        } elseif ($targetType === 3) {
            try {
                $memberIds = Db::table('group_members')
                    ->where('group_id', $targetId)
                    ->column('user_id');
                foreach ($memberIds as $memberId) {
                    $this->cm->sendToUser((int)$memberId, $data);
                }
            } catch (\Exception $e) {
                echo "[DB Error] broadcastClaimed group: {$e->getMessage()}\n";
            }
        }
    }

    /**
     * 退回过期红包
     */
    public static function refundExpired(): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            $expiredPackets = Db::table('red_packets')
                ->where('status', 1)
                ->where('expire_at', '<', $now)
                ->where('remaining_amount', '>', 0)
                ->select();

            foreach ($expiredPackets as $packet) {
                Db::startTrans();
                try {
                    $remaining = (float)$packet['remaining_amount'];
                    if ($remaining > 0) {
                        $affected = Db::table('wallets')
                            ->where('user_id', $packet['user_id'])
                            ->inc('balance', $remaining)
                            ->update();

                        if ($affected) {
                            $wallet = Db::table('wallets')
                                ->where('user_id', $packet['user_id'])
                                ->find();
                            if ($wallet) {
                                Db::table('wallet_transactions')->insert([
                                    'user_id'        => $packet['user_id'],
                                    'type'           => 8,
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

                    Db::table('red_packets')
                        ->where('id', $packet['id'])
                        ->update(['status' => 3, 'updated_at' => $now]);

                    Db::commit();
                    echo "[RedPacket] Refunded expired packet#{$packet['id']}, amount={$remaining} to userId={$packet['user_id']}\n";
                } catch (\Exception $e) {
                    Db::rollback();
                    echo "[RedPacket] Refund failed for packet#{$packet['id']}: {$e->getMessage()}\n";
                }
            }
        } catch (\Exception $e) {
            echo "[RedPacket] refundExpired error: {$e->getMessage()}\n";
        }
    }
}
