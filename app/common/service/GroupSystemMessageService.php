<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\Group;
use app\common\model\GroupMember;
use app\common\model\GroupMessage;

/**
 * 群聊系统通知（"XXX 加入了群聊" / "XXX 退出了群聊" 等）
 *
 * 写入 group_messages：user_id=0、msg_type='system'，content 为完整提示文案。
 * 推送复用普通群消息通道（type=group_message），前端按 msg_type=system 走居中灰字渲染。
 *
 * 失败静默：DB / WS 任一环节异常都不抛出，避免阻塞主业务流程（建群/邀请/退群）。
 */
class GroupSystemMessageService
{
    /**
     * 发送群系统通知
     *
     * @param int    $groupId
     * @param string $content 提示文案（不做 i18n / XSS：调用方拼接好的中文，且前端仅作为文本渲染）
     */
    public static function send(int $groupId, string $content): void
    {
        if ($groupId <= 0 || $content === '') return;

        try {
            $now = date('Y-m-d H:i:s');

            $message = GroupMessage::create([
                'group_id'   => $groupId,
                'user_id'    => 0,
                'msg_type'   => 'system',
                'content'    => $content,
                'media_url'  => '',
                'thumb_url'  => '',
                'created_at' => $now,
            ]);

            $memberIds = GroupMember::where('group_id', $groupId)->column('user_id');
            if (empty($memberIds)) return;

            // 携带群名/头像，确保会话列表能正确显示（尤其新群首条消息场景）
            $group = Group::field('name,avatar')->find($groupId);

            WsPushService::sendToUsers($memberIds, [
                'type'         => 'group_message',
                'id'           => (int)$message->id,
                'group_id'     => $groupId,
                'group_name'   => $group ? (string)$group->name : '',
                'group_avatar' => $group ? (string)$group->avatar : '',
                'user_id'      => 0,
                'nickname'     => '',
                'avatar'       => '',
                'user_code'    => '',
                'msg_type'     => 'system',
                'content'      => $content,
                'media_url'    => '',
                'thumb_url'    => '',
                'created_at'   => $now,
            ]);
        } catch (\Throwable $e) {
            // 静默失败：业务不应因系统通知失败而中断
        }
    }
}
