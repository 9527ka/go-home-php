<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\Friendship;
use app\common\model\PrivateMessage;
use app\common\model\GroupMember;
use app\common\model\GroupMessage;
use think\Response;
use think\facade\Db;

class Pm extends BaseApi
{
    /**
     * 获取与某好友的私聊历史
     * GET /api/pm/history?friend_id=xxx&before_id=xxx&limit=50
     */
    public function history(): Response
    {
        $userId = $this->getUserId();
        $friendId = (int)$this->request->get('friend_id', 0);
        $beforeId = (int)$this->request->get('before_id', 0);
        $limit = min(100, max(1, (int)$this->request->get('limit', 50)));

        if ($friendId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        // 验证好友关系
        $isFriend = Friendship::where('user_id', $userId)
            ->where('friend_id', $friendId)
            ->find();
        if (!$isFriend) {
            return $this->error(ErrorCode::FRIEND_NOT_FOUND, '非好友关系');
        }

        // 双向查询
        $query = PrivateMessage::with(['fromUser'])
            ->where(function ($q) use ($userId, $friendId) {
                $q->where(function ($sub) use ($userId, $friendId) {
                    $sub->where('from_id', $userId)->where('to_id', $friendId);
                })->whereOr(function ($sub) use ($userId, $friendId) {
                    $sub->where('from_id', $friendId)->where('to_id', $userId);
                });
            })
            ->order('id', 'desc');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->limit($limit)->select()->toArray();
        $messages = array_reverse($messages);

        return $this->success([
            'list'     => $messages,
            'has_more' => count($messages) === $limit,
        ]);
    }

    /**
     * 获取会话列表（私聊 + 群聊合并）
     * GET /api/pm/conversations
     */
    public function conversations(): Response
    {
        $userId = $this->getUserId();
        $conversations = [];

        // 1. 私聊会话 — 找出所有有聊天记录的好友（参数绑定防注入）
        $privateSql = "
            SELECT
                CASE WHEN from_id = ? THEN to_id ELSE from_id END AS friend_id,
                MAX(id) AS last_msg_id
            FROM private_messages
            WHERE from_id = ? OR to_id = ?
            GROUP BY friend_id
            ORDER BY last_msg_id DESC
            LIMIT 50
        ";
        $privateConvs = Db::query($privateSql, [$userId, $userId, $userId]);

        foreach ($privateConvs as $conv) {
            $friendId = (int)$conv['friend_id'];
            $lastMsg = PrivateMessage::find((int)$conv['last_msg_id']);
            if (!$lastMsg) continue;

            // 好友信息
            $friend = \app\common\model\User::field('id,nickname,avatar,user_code,user_type')
                ->find($friendId);
            if (!$friend) continue;

            // 未读数
            $unread = PrivateMessage::where('from_id', $friendId)
                ->where('to_id', $userId)
                ->where('is_read', 0)
                ->count();

            $conversations[] = [
                'target_id'     => $friendId,
                'target_type'   => 'private',
                'name'          => $friend->nickname,
                'avatar'        => $friend->avatar,
                'user_type'     => $friend->user_type ?? 0,
                'last_message'  => $lastMsg->content,
                'last_msg_type' => $lastMsg->msg_type,
                'last_msg_time' => $lastMsg->created_at,
                'unread_count'  => $unread,
            ];
        }

        // 2. 群聊会话
        $myGroups = GroupMember::where('user_id', $userId)->column('group_id');
        if (!empty($myGroups)) {
            foreach ($myGroups as $gid) {
                $group = \app\common\model\Group::where('id', $gid)
                    ->where('status', 1)
                    ->find();
                if (!$group) continue;

                $lastMsg = GroupMessage::where('group_id', $gid)
                    ->order('id', 'desc')
                    ->find();

                // 取前9个成员头像和昵称（用于客户端九宫格头像）
                $members = GroupMember::where('group_id', $gid)
                    ->limit(9)
                    ->with(['user' => function ($q) { $q->field('id,nickname,avatar'); }])
                    ->select();
                $memberAvatars = [];
                $memberNames = [];
                foreach ($members as $m) {
                    $memberAvatars[] = $m->user ? $m->user->avatar : '';
                    $memberNames[] = $m->user ? $m->user->nickname : '';
                }

                $conversations[] = [
                    'target_id'       => $gid,
                    'target_type'     => 'group',
                    'name'            => $group->name,
                    'avatar'          => $group->avatar,
                    'last_message'    => $lastMsg ? $lastMsg->content : '',
                    'last_msg_type'   => $lastMsg ? $lastMsg->msg_type : 'text',
                    'last_msg_time'   => $lastMsg ? $lastMsg->created_at : $group->created_at,
                    'unread_count'    => 0,
                    'member_avatars'  => $memberAvatars,
                    'member_names'    => $memberNames,
                ];
            }
        }

        // 按最新消息时间排序
        usort($conversations, function ($a, $b) {
            return strcmp($b['last_msg_time'], $a['last_msg_time']);
        });

        return $this->success(['list' => $conversations]);
    }

    /**
     * 标记私聊已读
     * POST /api/pm/read
     *
     * @body friend_id int
     */
    public function read(): Response
    {
        $userId = $this->getUserId();
        $friendId = (int)$this->request->post('friend_id', 0);

        if ($friendId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        PrivateMessage::where('from_id', $friendId)
            ->where('to_id', $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return $this->success(null, '已标记已读');
    }

    /**
     * 设置会话免打扰（服务端据此跳过 APNs 推送）
     * POST /api/pm/mute
     *
     * @body target_id   int    对方用户ID或群组ID
     * @body target_type string private / group
     * @body muted       bool
     */
    public function mute(): Response
    {
        $userId     = $this->getUserId();
        $targetId   = (int)$this->request->post('target_id', 0);
        $targetType = $this->request->post('target_type', 'private');
        $muted      = (bool)$this->request->post('muted', false);

        if ($targetId <= 0 || !in_array($targetType, ['private', 'group'], true)) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        if ($muted) {
            Db::table('conversation_mutes')->replace(true)->insert([
                'user_id'     => $userId,
                'target_id'   => $targetId,
                'target_type' => $targetType,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } else {
            Db::table('conversation_mutes')
                ->where('user_id', $userId)
                ->where('target_id', $targetId)
                ->where('target_type', $targetType)
                ->delete();
        }

        return $this->success();
    }
}
