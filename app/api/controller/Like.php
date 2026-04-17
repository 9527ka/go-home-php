<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\Like as LikeModel;
use app\common\model\Post as PostModel;
use app\common\model\Comment as CommentModel;
use app\common\model\Notification;
use app\common\service\UserResource;
use think\facade\Db;
use think\facade\Log;
use think\Response;

class Like extends BaseApi
{
    /**
     * 点赞/取消点赞（幂等切换）
     * POST /api/like/toggle
     *
     * @body target_type int 1=帖子 2=评论
     * @body target_id   int 目标ID
     */
    public function toggle(): Response
    {
        $userId     = $this->getUserId();
        $targetType = (int)$this->request->post('target_type', 1);
        $targetId   = (int)$this->request->post('target_id', 0);

        if ($targetId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }
        if (!in_array($targetType, [LikeModel::TARGET_POST, LikeModel::TARGET_COMMENT])) {
            return $this->error(ErrorCode::PARAM_VALIDATE_FAIL, '无效的目标类型');
        }

        // 频率限制：1分钟内最多50次点赞
        $recentCount = LikeModel::where('user_id', $userId)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 minute')))
            ->count();
        if ($recentCount > 50) {
            return $this->error(ErrorCode::RATE_LIMIT);
        }

        // 校验目标是否存在 + 权限检查
        $ownerId = 0;
        if ($targetType === LikeModel::TARGET_POST) {
            $post = PostModel::find($targetId);
            if (!$post || !$post->isActive()) {
                return $this->error(ErrorCode::POST_NOT_FOUND);
            }
            // 私密帖只有发布者可点赞
            if ($post->visibility == 2 && (int)$post->user_id !== $userId) {
                return $this->error(ErrorCode::POST_NOT_FOUND);
            }
            $ownerId = (int)$post->user_id;
        } else {
            $comment = CommentModel::where('id', $targetId)->where('status', CommentModel::STATUS_NORMAL)->find();
            if (!$comment) {
                return $this->error(ErrorCode::COMMENT_NOT_FOUND);
            }
            $ownerId = (int)$comment->user_id;
        }

        // 事务保护，防止并发导致计数不一致
        Db::startTrans();
        try {
            $exists = LikeModel::where('user_id', $userId)
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->find();

            if ($exists) {
                // 取消点赞
                $exists->delete();
                $this->updateLikeCount($targetType, $targetId, -1);
                Db::commit();
                return $this->success([
                    'is_liked'   => false,
                    'like_count' => $this->getLikeCount($targetType, $targetId),
                ], '已取消点赞');
            }

            // 点赞
            $like = new LikeModel();
            $like->user_id     = $userId;
            $like->target_type = $targetType;
            $like->target_id   = $targetId;
            $like->created_at  = date('Y-m-d H:i:s');
            $like->save();

            $this->updateLikeCount($targetType, $targetId, 1);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Like toggle failed: " . $e->getMessage());
            return $this->error(ErrorCode::DB_ERROR);
        }

        // 发送通知（事务外执行，不影响主流程）
        if ($ownerId > 0 && $ownerId !== $userId) {
            $notifyType = $targetType === LikeModel::TARGET_POST
                ? Notification::TYPE_LIKE_POST
                : Notification::TYPE_LIKE_COMMENT;
            $this->sendNotification($userId, $ownerId, $notifyType, $targetId);
        }

        return $this->success([
            'is_liked'   => true,
            'like_count' => $this->getLikeCount($targetType, $targetId),
        ], '已点赞');
    }

    /**
     * 批量查询点赞状态
     * GET /api/like/status
     */
    public function status(): Response
    {
        $userId     = $this->getUserId();
        $targetType = (int)$this->request->get('target_type', 1);
        $targetIds  = $this->request->get('target_ids', '');

        if (empty($targetIds)) {
            return $this->success(['liked_ids' => []]);
        }

        $ids = array_map('intval', explode(',', $targetIds));
        $ids = array_filter($ids, fn($id) => $id > 0);
        // 限制批量查询数量，防止滥用
        $ids = array_slice($ids, 0, 100);

        if (empty($ids)) {
            return $this->success(['liked_ids' => []]);
        }

        $likedIds = LikeModel::where('user_id', $userId)
            ->where('target_type', $targetType)
            ->whereIn('target_id', $ids)
            ->column('target_id');

        return $this->success(['liked_ids' => array_values($likedIds)]);
    }

    /**
     * 点赞用户列表
     * GET /api/like/users
     */
    public function users(): Response
    {
        $targetType = (int)$this->request->get('target_type', 1);
        $targetId   = (int)$this->request->get('target_id', 0);
        $page       = max(1, (int)$this->request->get('page', 1));

        if ($targetId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        // 校验目标可见性
        if ($targetType === LikeModel::TARGET_POST) {
            $post = PostModel::find($targetId);
            if (!$post || !$post->isActive()) {
                return $this->error(ErrorCode::POST_NOT_FOUND);
            }
        }

        $list = LikeModel::where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->with(['user'])
            ->order('created_at', 'desc')
            ->paginate(20, false, ['page' => $page]);

        $items = array_map(fn($x) => $x->toArray(), $list->items());
        UserResource::attachVipInList($items, 'user');

        return $this->successPage([
            'list'      => $items,
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }

    private function updateLikeCount(int $targetType, int $targetId, int $delta): void
    {
        if ($targetType === LikeModel::TARGET_POST) {
            PostModel::where('id', $targetId)->inc('like_count', $delta)->update([]);
        } else {
            CommentModel::where('id', $targetId)->inc('like_count', $delta)->update([]);
        }
    }

    private function getLikeCount(int $targetType, int $targetId): int
    {
        if ($targetType === LikeModel::TARGET_POST) {
            return (int)(PostModel::where('id', $targetId)->value('like_count') ?? 0);
        }
        return (int)(CommentModel::where('id', $targetId)->value('like_count') ?? 0);
    }

    private function sendNotification(int $fromUserId, int $toUserId, int $type, int $targetId): void
    {
        try {
            $fromUser = \app\common\model\User::find($fromUserId);
            $nickname = htmlspecialchars($fromUser?->nickname ?? '有人', ENT_QUOTES, 'UTF-8');

            $titles = [
                Notification::TYPE_LIKE_POST    => '收到新的点赞',
                Notification::TYPE_LIKE_COMMENT => '你的评论被点赞了',
            ];
            $contents = [
                Notification::TYPE_LIKE_POST    => "{$nickname} 赞了你的帖子",
                Notification::TYPE_LIKE_COMMENT => "{$nickname} 赞了你的评论",
            ];

            $notification = new Notification();
            $notification->user_id    = $toUserId;
            $notification->type       = $type;
            $notification->title      = $titles[$type] ?? '新通知';
            $notification->content    = $contents[$type] ?? '';
            $notification->related_id = $targetId;
            $notification->is_read    = 0;
            $notification->created_at = date('Y-m-d H:i:s');
            $notification->save();
        } catch (\Throwable $e) {
            Log::error("Like notification failed: " . $e->getMessage(), [
                'from' => $fromUserId, 'to' => $toUserId, 'type' => $type,
            ]);
        }
    }
}
