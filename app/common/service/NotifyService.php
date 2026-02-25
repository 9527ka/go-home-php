<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\model\Notification;
use think\facade\Log;

class NotifyService
{
    /**
     * 发送通知
     */
    public static function send(int $userId, int $type, string $title, string $content = '', ?int $postId = null): void
    {
        try {
            $notification = new Notification();
            $notification->user_id   = $userId;
            $notification->type      = $type;
            $notification->title     = $title;
            $notification->content   = $content;
            $notification->post_id   = $postId;
            $notification->is_read   = 0;
            $notification->created_at = date('Y-m-d H:i:s');
            $notification->save();
        } catch (\Exception $e) {
            // 通知发送失败不影响主业务
            Log::error("Notification send failed: " . $e->getMessage());
        }
    }

    /**
     * 审核通过通知
     */
    public static function notifyAuditPass(int $userId, int $postId, string $postName): void
    {
        self::send(
            $userId,
            Notification::TYPE_AUDIT_PASS,
            '您的启事已审核通过',
            "您发布的「{$postName}」已通过审核，现已公开展示。祝早日找到！",
            $postId
        );
    }

    /**
     * 审核驳回通知
     */
    public static function notifyAuditReject(int $userId, int $postId, string $postName, string $reason): void
    {
        self::send(
            $userId,
            Notification::TYPE_AUDIT_REJECT,
            '您的启事未通过审核',
            "您发布的「{$postName}」未通过审核。原因：{$reason}。请修改后重新提交。",
            $postId
        );
    }

    /**
     * 新线索通知
     */
    public static function notifyNewClue(int $userId, int $postId, string $postName): void
    {
        self::send(
            $userId,
            Notification::TYPE_CLUE_REPLY,
            '您的启事收到新线索',
            "有人为「{$postName}」提供了新线索，请尽快查看。",
            $postId
        );
    }

    /**
     * 获取未读通知数量
     */
    public static function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', 0)
            ->count();
    }

    /**
     * 标记已读
     */
    public static function markAsRead(int $userId, ?int $notificationId = null): void
    {
        $query = Notification::where('user_id', $userId)->where('is_read', 0);

        if ($notificationId) {
            $query->where('id', $notificationId);
        }

        $query->update(['is_read' => 1]);
    }
}
