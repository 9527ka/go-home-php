<?php
declare(strict_types=1);

namespace worker\Chat;

use think\facade\Db;
use Workerman\Connection\TcpConnection;

/**
 * 私聊语音通话信令处理（腾讯云 TRTC 纯音频，信令走 WebSocket）
 *
 * 客户端消息格式：
 * {
 *   "type": "call_signal",
 *   "action": "invite" | "accept" | "decline" | "cancel" | "hangup" | "timeout",
 *   "call_id": "string",           // invite 时由主叫生成 UUID；其它 action 带回原 call_id
 *   "to_id": int,                   // 对端用户 ID
 *   "call_type": "voice"           // MVP 仅 voice
 * }
 *
 * 服务端广播给对端的 payload 会附加 from_id / from_nickname / from_avatar。
 *
 * 状态机（call_records.status）：
 *   invited → accepted → completed (正常通话结束)
 *   invited → declined / canceled / missed / busy / timeout（未接通结束）
 *
 * 通话结束时（completed / declined / canceled / missed / timeout）在 private_messages
 * 写一条 voice_call 气泡消息，media_info={call_id, duration, status, caller_id, callee_id}。
 */
class CallSignalingHandler
{
    public const CALL_RING_TIMEOUT_SEC = 60; // 60s 无应答视为 missed

    private ConnectionManager $cm;

    public function __construct(ConnectionManager $cm)
    {
        $this->cm = $cm;
    }

    public function handle(TcpConnection $connection, array $msg): void
    {
        if (!MessageValidator::requireAuth($connection)) return;
        if (!MessageValidator::checkRateLimit($connection)) return;

        $action = (string)($msg['action'] ?? '');
        $callId = trim((string)($msg['call_id'] ?? ''));
        $toId   = (int)($msg['to_id'] ?? 0);

        if ($toId <= 0 || $callId === '' || $action === '') {
            MessageValidator::sendError($connection, '通话参数无效', 'CALL_INVALID');
            return;
        }

        switch ($action) {
            case 'invite':  $this->onInvite($connection, $callId, $toId, (string)($msg['call_type'] ?? 'voice')); break;
            case 'accept':  $this->onAccept($connection, $callId, $toId); break;
            case 'decline': $this->onDecline($connection, $callId, $toId); break;
            case 'cancel':  $this->onCancel($connection, $callId, $toId); break;
            case 'hangup':  $this->onHangup($connection, $callId, $toId); break;
            case 'timeout': $this->onTimeout($connection, $callId, $toId); break;
            default:
                MessageValidator::sendError($connection, '未知通话信令', 'CALL_INVALID');
        }
    }

    // =====================================================================
    //  信令分支
    // =====================================================================

    /** 主叫发起邀请 */
    private function onInvite(TcpConnection $connection, string $callId, int $toId, string $callType): void
    {
        $callerId = (int)$connection->userId;
        if ($callerId === $toId) {
            MessageValidator::sendError($connection, '不能呼叫自己', 'CALL_INVALID');
            return;
        }
        if ($callType !== 'voice') {
            MessageValidator::sendError($connection, 'MVP 仅支持语音通话', 'CALL_INVALID');
            return;
        }

        // 好友关系 + 账号状态校验
        try {
            $isFriend = Db::table('friendships')
                ->where('user_id', $callerId)->where('friend_id', $toId)->find();
            if (!$isFriend) {
                MessageValidator::sendError($connection, '对方不是您的好友', 'NOT_FRIEND');
                return;
            }
            $userStatus = (int)(Db::table('users')->where('id', $callerId)->value('status') ?? 1);
            if ($userStatus === 3) { MessageValidator::sendError($connection, '账号已被封禁', 'USER_BANNED'); return; }
            if ($userStatus === 2) { MessageValidator::sendError($connection, '您已被禁言', 'USER_MUTED'); return; }
        } catch (\Throwable $e) {
            echo "[Call] invite check failed: {$e->getMessage()}\n";
            MessageValidator::sendError($connection, '服务器错误', 'SERVER_ERROR');
            return;
        }

        // 被叫是否在另一通话中（has 未结束的 invited/accepted 记录）
        if ($this->isUserBusy($toId)) {
            $this->replyBusyToCaller($connection, $callId, $toId);
            return;
        }

        // 创建通话记录
        try {
            Db::table('call_records')->insert([
                'call_id'    => $callId,
                'caller_id'  => $callerId,
                'callee_id'  => $toId,
                'type'       => 'voice',
                'status'     => 'invited',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            echo "[Call] insert record failed: {$e->getMessage()}\n";
            MessageValidator::sendError($connection, '服务器错误', 'SERVER_ERROR');
            return;
        }

        // 转发给被叫
        $payload = [
            'type'          => 'call_signal',
            'action'        => 'invite',
            'call_id'       => $callId,
            'call_type'     => 'voice',
            'from_id'       => $callerId,
            'from_nickname' => $connection->userInfo['nickname'] ?? '',
            'from_avatar'   => $connection->userInfo['avatar'] ?? '',
            'to_id'         => $toId,
        ];
        $delivered = $this->cm->sendToUser($toId, $payload);

        // 被叫离线 → 回主叫 callee_offline（MVP 不做推送）
        if (!$delivered) {
            $this->updateStatus($callId, 'missed', false);
            $this->writeCallBubble($callId, $callerId, $toId, 'missed', 0);
            $this->cm->sendToUser($callerId, [
                'type'    => 'call_signal',
                'action'  => 'callee_offline',
                'call_id' => $callId,
                'to_id'   => $toId,
            ]);
            return;
        }

        // 回执主叫 "invite_ok"，便于客户端开始响铃倒计时
        $connection->send(json_encode([
            'type'    => 'call_signal',
            'action'  => 'invite_ok',
            'call_id' => $callId,
            'to_id'   => $toId,
        ], JSON_UNESCAPED_UNICODE));

        echo "[Call] invite {$callerId} => {$toId} call_id={$callId}\n";
    }

    /** 被叫接听 */
    private function onAccept(TcpConnection $connection, string $callId, int $toId): void
    {
        $record = $this->loadRecord($callId, (int)$connection->userId, callerOrCallee: 'callee');
        if (!$record) return;

        $this->updateStatus($callId, 'accepted', true);
        $this->forward($connection, $toId, $callId, 'accept');
        echo "[Call] accept call_id={$callId}\n";
    }

    /** 被叫拒接 */
    private function onDecline(TcpConnection $connection, string $callId, int $toId): void
    {
        $record = $this->loadRecord($callId, (int)$connection->userId, callerOrCallee: 'callee');
        if (!$record) return;

        $this->updateStatus($callId, 'declined', false);
        $this->writeCallBubble($callId, (int)$record['caller_id'], (int)$record['callee_id'], 'declined', 0);
        $this->forward($connection, $toId, $callId, 'decline');
        echo "[Call] decline call_id={$callId}\n";
    }

    /** 主叫在对方接听前取消 */
    private function onCancel(TcpConnection $connection, string $callId, int $toId): void
    {
        $record = $this->loadRecord($callId, (int)$connection->userId, callerOrCallee: 'caller');
        if (!$record) return;
        if ($record['status'] !== 'invited') {
            MessageValidator::sendError($connection, '通话状态错误', 'CALL_STATE');
            return;
        }

        $this->updateStatus($callId, 'canceled', false);
        $this->writeCallBubble($callId, (int)$record['caller_id'], (int)$record['callee_id'], 'canceled', 0);
        $this->forward($connection, $toId, $callId, 'cancel');
        echo "[Call] cancel call_id={$callId}\n";
    }

    /** 任意方挂断（已接通） */
    private function onHangup(TcpConnection $connection, string $callId, int $toId): void
    {
        $record = $this->loadRecord($callId, (int)$connection->userId);
        if (!$record) return;

        // 若还没 accepted，视为取消或拒接（兜底，客户端正常应发 cancel/decline）
        if ($record['status'] === 'invited') {
            $isCaller = ((int)$record['caller_id'] === (int)$connection->userId);
            if ($isCaller) {
                $this->updateStatus($callId, 'canceled', false);
                $this->writeCallBubble($callId, (int)$record['caller_id'], (int)$record['callee_id'], 'canceled', 0);
            } else {
                $this->updateStatus($callId, 'declined', false);
                $this->writeCallBubble($callId, (int)$record['caller_id'], (int)$record['callee_id'], 'declined', 0);
            }
            $this->forward($connection, $toId, $callId, 'hangup');
            return;
        }

        // 正常挂断：计算时长
        $duration = 0;
        if (!empty($record['started_at'])) {
            $duration = max(0, time() - strtotime($record['started_at']));
        }
        Db::table('call_records')
            ->where('call_id', $callId)
            ->update([
                'status'   => 'completed',
                'duration' => $duration,
                'ended_at' => date('Y-m-d H:i:s'),
            ]);
        $this->writeCallBubble($callId, (int)$record['caller_id'], (int)$record['callee_id'], 'completed', $duration);
        $this->forward($connection, $toId, $callId, 'hangup', ['duration' => $duration]);
        echo "[Call] hangup call_id={$callId} duration={$duration}\n";
    }

    /** 主叫响铃超时 */
    private function onTimeout(TcpConnection $connection, string $callId, int $toId): void
    {
        $record = $this->loadRecord($callId, (int)$connection->userId, callerOrCallee: 'caller');
        if (!$record) return;
        if ($record['status'] !== 'invited') return; // 已接通或已结束，忽略

        $this->updateStatus($callId, 'missed', false);
        $this->writeCallBubble($callId, (int)$record['caller_id'], (int)$record['callee_id'], 'missed', 0);
        $this->forward($connection, $toId, $callId, 'timeout');
        echo "[Call] timeout call_id={$callId}\n";
    }

    // =====================================================================
    //  工具
    // =====================================================================

    private function isUserBusy(int $userId): bool
    {
        try {
            $row = Db::table('call_records')
                ->where(function ($q) use ($userId) {
                    $q->where('caller_id', $userId)->whereOr('callee_id', $userId);
                })
                ->whereIn('status', ['invited', 'accepted'])
                ->order('id', 'desc')
                ->find();
            if (!$row) return false;
            // 邀请超过 CALL_RING_TIMEOUT_SEC 的记录视为僵死，不算 busy
            $age = time() - strtotime($row['created_at']);
            if ($row['status'] === 'invited' && $age > self::CALL_RING_TIMEOUT_SEC) return false;
            return true;
        } catch (\Throwable $e) {
            echo "[Call] isUserBusy failed: {$e->getMessage()}\n";
            return false;
        }
    }

    private function replyBusyToCaller(TcpConnection $connection, string $callId, int $toId): void
    {
        // busy 情况下也落库一条 record 并写气泡（主叫侧可见“对方忙”）
        try {
            Db::table('call_records')->insert([
                'call_id'    => $callId,
                'caller_id'  => (int)$connection->userId,
                'callee_id'  => $toId,
                'type'       => 'voice',
                'status'     => 'busy',
                'created_at' => date('Y-m-d H:i:s'),
                'ended_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            echo "[Call] busy insert failed: {$e->getMessage()}\n";
        }
        $this->writeCallBubble($callId, (int)$connection->userId, $toId, 'busy', 0);
        $connection->send(json_encode([
            'type'    => 'call_signal',
            'action'  => 'busy',
            'call_id' => $callId,
            'to_id'   => $toId,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 加载并校验通话记录属于当前用户
     * @param string $callerOrCallee 'caller'|'callee'|'' 校验发送者身份
     */
    private function loadRecord(string $callId, int $userId, string $callerOrCallee = ''): ?array
    {
        try {
            $row = Db::table('call_records')->where('call_id', $callId)->find();
        } catch (\Throwable $e) {
            echo "[Call] loadRecord failed: {$e->getMessage()}\n";
            return null;
        }
        if (!$row) return null;
        if ($callerOrCallee === 'caller' && (int)$row['caller_id'] !== $userId) return null;
        if ($callerOrCallee === 'callee' && (int)$row['callee_id'] !== $userId) return null;
        if ($callerOrCallee === '' && (int)$row['caller_id'] !== $userId && (int)$row['callee_id'] !== $userId) return null;
        return $row;
    }

    /** 更新状态；accepted 时写 started_at，结束态写 ended_at */
    private function updateStatus(string $callId, string $status, bool $isAccept): void
    {
        $data = ['status' => $status];
        if ($isAccept) {
            $data['started_at'] = date('Y-m-d H:i:s');
        } elseif (in_array($status, ['declined', 'canceled', 'missed', 'timeout', 'busy'], true)) {
            $data['ended_at'] = date('Y-m-d H:i:s');
        }
        try {
            Db::table('call_records')->where('call_id', $callId)->update($data);
        } catch (\Throwable $e) {
            echo "[Call] updateStatus failed: {$e->getMessage()}\n";
        }
    }

    /** 转发信令给对端 */
    private function forward(TcpConnection $connection, int $toId, string $callId, string $action, array $extra = []): void
    {
        $payload = array_merge([
            'type'    => 'call_signal',
            'action'  => $action,
            'call_id' => $callId,
            'from_id' => (int)$connection->userId,
            'to_id'   => $toId,
        ], $extra);
        $this->cm->sendToUser($toId, $payload);
    }

    /**
     * 通话结束时在 private_messages 写一条 voice_call 气泡
     *
     * 按"语义归属发起方"原则，from_id 始终为主叫，to_id 始终为被叫，
     * 这样聊天页两端都能在 UI 上正确展示气泡朝向（主叫右侧、被叫左侧）。
     */
    private function writeCallBubble(string $callId, int $callerId, int $calleeId, string $status, int $duration): void
    {
        $mediaInfo = [
            'call_id'   => $callId,
            'duration'  => $duration,
            'status'    => $status, // completed / declined / canceled / missed / busy
            'caller_id' => $callerId,
            'callee_id' => $calleeId,
        ];
        try {
            MessageRepository::savePrivate(
                $callerId,
                $calleeId,
                '', // content 空，由客户端按 media_info.status 渲染文案
                'voice_call',
                '',
                '',
                $mediaInfo,
            );

            // 广播给双方（如果在线）以便聊天页即时插入气泡
            $payload = [
                'type'       => 'private_message',
                'from_id'    => $callerId,
                'to_id'      => $calleeId,
                'user_id'    => $callerId,
                'msg_type'   => 'voice_call',
                'content'    => '',
                'media_url'  => '',
                'thumb_url'  => '',
                'media_info' => $mediaInfo,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $this->cm->sendToUser($callerId, $payload);
            $this->cm->sendToUser($calleeId, $payload);
        } catch (\Throwable $e) {
            echo "[Call] writeCallBubble failed: {$e->getMessage()}\n";
        }
    }
}
