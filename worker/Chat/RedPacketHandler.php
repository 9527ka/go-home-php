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

        $redPacketId = (int)($msg['red_packet_id'] ?? 0);
        if ($redPacketId <= 0) {
            MessageValidator::sendError($connection, '红包ID无效');
            return;
        }

        try {
            $packet = Db::table('red_packets')
                ->where('id', $redPacketId)
                ->where('user_id', $connection->userId)
                ->find();
            if (!$packet) {
                MessageValidator::sendError($connection, '红包不存在');
                return;
            }
        } catch (\Exception $e) {
            echo "[DB Error] handleRedPacketMessage: {$e->getMessage()}\n";
            MessageValidator::sendError($connection, '服务器错误');
            return;
        }

        $greeting   = $packet['greeting'] ?: '恭喜发财，大吉大利';
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

        $contentJson = json_encode(['red_packet_id' => $redPacketId, 'greeting' => $greeting], JSON_UNESCAPED_UNICODE);

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
