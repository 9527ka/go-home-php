<?php
declare(strict_types=1);

namespace worker\Chat;

use think\facade\Db;

/**
 * 统一消息持久化（消除 3 处重复的 save 函数）
 */
class MessageRepository
{
    /**
     * 保存公共聊天消息
     */
    public static function savePublic(int $userId, string $content, string $msgType = 'text', string $mediaUrl = '', string $thumbUrl = '', ?array $mediaInfo = null): ?int
    {
        return self::insert('chat_messages', [
            'user_id'    => $userId,
            'msg_type'   => $msgType,
            'content'    => $content,
            'media_url'  => $mediaUrl,
            'thumb_url'  => $thumbUrl,
            'media_info' => $mediaInfo ? json_encode($mediaInfo, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ], $userId);
    }

    /**
     * 保存私聊消息
     */
    public static function savePrivate(int $fromId, int $toId, string $content, string $msgType = 'text', string $mediaUrl = '', string $thumbUrl = '', ?array $mediaInfo = null): ?int
    {
        return self::insert('private_messages', [
            'from_id'    => $fromId,
            'to_id'      => $toId,
            'msg_type'   => $msgType,
            'content'    => $content,
            'media_url'  => $mediaUrl,
            'thumb_url'  => $thumbUrl,
            'media_info' => $mediaInfo ? json_encode($mediaInfo, JSON_UNESCAPED_UNICODE) : null,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ], $fromId);
    }

    /**
     * 保存群聊消息
     */
    public static function saveGroup(int $userId, int $groupId, string $content, string $msgType = 'text', string $mediaUrl = '', string $thumbUrl = '', ?array $mediaInfo = null): ?int
    {
        return self::insert('group_messages', [
            'user_id'    => $userId,
            'group_id'   => $groupId,
            'msg_type'   => $msgType,
            'content'    => $content,
            'media_url'  => $mediaUrl,
            'thumb_url'  => $thumbUrl,
            'media_info' => $mediaInfo ? json_encode($mediaInfo, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ], null);
    }

    /**
     * 获取用户信息
     */
    public static function getUserInfo(int $userId): ?array
    {
        try {
            $user = Db::table('users')
                ->field('id, nickname, avatar, user_code')
                ->where('id', $userId)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->find();
            return $user ?: null;
        } catch (\Exception $e) {
            echo "[DB Error] getUserInfo: {$e->getMessage()}\n";
            return null;
        }
    }

    // ---- 内部 ----

    private static function insert(string $table, array $data, ?int $chatTaskUserId): ?int
    {
        try {
            $id = (int)Db::table($table)->insertGetId($data);

            // 签到任务：聊天计数（仅公共/私聊触发）
            if ($chatTaskUserId) {
                try {
                    \app\common\service\TaskService::incrementTaskProgress($chatTaskUserId, 'chat_3');
                } catch (\Throwable $e) {
                    echo "[Task] chat_3 progress failed for user#{$chatTaskUserId}: {$e->getMessage()}\n";
                }
            }

            return $id;
        } catch (\Exception $e) {
            echo "[DB Error] {$table} insert: {$e->getMessage()}\n";
            return null;
        }
    }
}
