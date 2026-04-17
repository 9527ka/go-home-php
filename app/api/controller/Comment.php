<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\Comment as CommentModel;
use app\common\model\Post as PostModel;
use app\common\model\Like as LikeModel;
use app\common\model\Notification;
use app\common\service\PostService;
use app\common\service\UserResource;
use think\facade\Db;
use think\facade\Log;
use think\Response;

class Comment extends BaseApi
{
    /**
     * 发表评论/回复
     * POST /api/comment/create
     *
     * @body post_id          int    帖子ID
     * @body content           string 评论内容（2-500字）
     * @body parent_id         int    父评论ID（可选，楼中楼回复）
     * @body reply_to_user_id  int    回复对象用户ID（可选）
     */
    public function create(): Response
    {
        $userId        = $this->getUserId();
        $postId        = (int)$this->request->post('post_id', 0);
        $content       = trim((string)$this->request->post('content', ''));
        $parentId      = (int)$this->request->post('parent_id', 0);
        $replyToUserId = (int)$this->request->post('reply_to_user_id', 0);

        if ($postId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING, '帖子ID不能为空');
        }
        if (mb_strlen($content) < 2 || mb_strlen($content) > 500) {
            return $this->error(ErrorCode::PARAM_VALIDATE_FAIL, '评论内容需要2-500个字');
        }

        // 频率限制：1分钟内最多10条评论
        $recentCount = CommentModel::where('user_id', $userId)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 minute')))
            ->count();
        if ($recentCount >= 10) {
            return $this->error(ErrorCode::COMMENT_TOO_FREQUENT);
        }

        // 敏感词过滤
        try {
            PostService::filterSensitiveContent(['content' => $content]);
        } catch (\Throwable $e) {
            return $this->error(ErrorCode::PARAM_VALIDATE_FAIL, $e->getMessage());
        }

        // 帖子必须存在且为活跃状态
        $post = PostModel::find($postId);
        if (!$post || !$post->isActive()) {
            return $this->error(ErrorCode::POST_NOT_FOUND);
        }

        // 如果是回复，校验父评论
        if ($parentId > 0) {
            $parent = CommentModel::where('id', $parentId)
                ->where('post_id', $postId)
                ->where('status', CommentModel::STATUS_NORMAL)
                ->find();
            if (!$parent) {
                return $this->error(ErrorCode::COMMENT_NOT_FOUND, '被回复的评论不存在');
            }
            // 楼中楼只允许一层：如果 parent 本身有 parent，则归到顶级评论下
            if ($parent->parent_id) {
                $parentId = (int)$parent->parent_id;
            }
        }

        // 事务保护：创建评论 + 更新计数
        Db::startTrans();
        try {
            $comment = new CommentModel();
            $comment->post_id          = $postId;
            $comment->user_id          = $userId;
            $comment->parent_id        = $parentId > 0 ? $parentId : null;
            $comment->reply_to_user_id = $replyToUserId > 0 ? $replyToUserId : null;
            $comment->content          = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            $comment->status           = CommentModel::STATUS_NORMAL;
            $comment->created_at       = date('Y-m-d H:i:s');
            $comment->save();

            // 更新帖子评论数
            PostModel::where('id', $postId)->inc('comment_count', 1)->update([]);

            // 更新父评论回复数
            if ($parentId > 0) {
                CommentModel::where('id', $parentId)->inc('reply_count', 1)->update([]);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Comment create failed: " . $e->getMessage());
            return $this->error(ErrorCode::DB_ERROR);
        }

        // 发送通知（事务外执行）
        $this->sendCommentNotification($userId, $comment, $post);

        // 返回带用户信息的评论
        $comment = CommentModel::with(['user', 'replyToUser'])->find($comment->id);
        $commentArr = $comment->toArray();
        if (isset($commentArr['user']) && is_array($commentArr['user'])) {
            UserResource::attachVipSingle($commentArr['user']);
        }
        if (isset($commentArr['reply_to_user']) && is_array($commentArr['reply_to_user'])) {
            UserResource::attachVipSingle($commentArr['reply_to_user']);
        }

        return $this->success($commentArr, '评论成功');
    }

    /**
     * 帖子评论列表
     * GET /api/comment/list
     */
    public function list(): Response
    {
        $postId = (int)$this->request->get('post_id', 0);
        $sort   = $this->request->get('sort', 'hot');
        $page   = max(1, (int)$this->request->get('page', 1));
        $userId = $this->getUserId();

        if ($postId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        // 查询顶级评论
        $query = CommentModel::where('post_id', $postId)
            ->whereNull('parent_id')
            ->where('status', CommentModel::STATUS_NORMAL)
            ->with(['user', 'replyToUser']);

        if ($sort === 'hot') {
            $query->order('like_count', 'desc')->order('created_at', 'desc');
        } else {
            $query->order('created_at', 'desc');
        }

        $list = $query->paginate(20, false, ['page' => $page]);
        $items = $list->items();
        $commentIds = array_column($items, 'id');

        // 批量加载回复预览（优化 N+1）
        $replyPreviews = [];
        if (!empty($commentIds)) {
            $allReplies = CommentModel::whereIn('parent_id', $commentIds)
                ->where('status', CommentModel::STATUS_NORMAL)
                ->with(['user', 'replyToUser'])
                ->order('like_count', 'desc')
                ->order('created_at', 'asc')
                ->select();

            // 按 parent_id 分组，每组取前2条
            foreach ($allReplies as $reply) {
                $pid = (int)$reply->parent_id;
                if (!isset($replyPreviews[$pid])) $replyPreviews[$pid] = [];
                if (count($replyPreviews[$pid]) < 2) {
                    $replyPreviews[$pid][] = $reply->toArray();
                }
            }
        }

        // 批量查询当前用户的点赞状态
        $likedCommentIds = [];
        if ($userId > 0 && !empty($commentIds)) {
            $allCommentIds = $commentIds;
            foreach ($replyPreviews as $replies) {
                foreach ($replies as $r) {
                    $allCommentIds[] = $r['id'];
                }
            }
            $likedCommentIds = LikeModel::where('user_id', $userId)
                ->where('target_type', LikeModel::TARGET_COMMENT)
                ->whereIn('target_id', $allCommentIds)
                ->column('target_id');
        }

        // 组装结果
        $result = [];
        foreach ($items as $item) {
            $arr = $item->toArray();
            $arr['reply_preview'] = $replyPreviews[$item->id] ?? [];
            $arr['is_liked'] = in_array($item->id, $likedCommentIds);
            foreach ($arr['reply_preview'] as &$reply) {
                $reply['is_liked'] = in_array($reply['id'], $likedCommentIds);
            }
            $result[] = $arr;
        }

        // 附加评论发表者 / 被回复者 的 VIP（含 reply_preview 嵌套）
        UserResource::attachVipInListMulti($result, ['user', 'reply_to_user']);
        foreach ($result as &$r) {
            if (!empty($r['reply_preview'])) {
                UserResource::attachVipInListMulti($r['reply_preview'], ['user', 'reply_to_user']);
            }
        }
        unset($r);

        return $this->successPage([
            'list'      => $result,
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }

    /**
     * 某条评论的全部回复
     * GET /api/comment/replies
     */
    public function replies(): Response
    {
        $commentId = (int)$this->request->get('comment_id', 0);
        $page      = max(1, (int)$this->request->get('page', 1));
        $userId    = $this->getUserId();

        if ($commentId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $list = CommentModel::where('parent_id', $commentId)
            ->where('status', CommentModel::STATUS_NORMAL)
            ->with(['user', 'replyToUser'])
            ->order('created_at', 'asc')
            ->paginate(20, false, ['page' => $page]);

        $items = $list->items();
        $likedIds = [];
        if ($userId > 0 && !empty($items)) {
            $ids = array_column($items, 'id');
            $likedIds = LikeModel::where('user_id', $userId)
                ->where('target_type', LikeModel::TARGET_COMMENT)
                ->whereIn('target_id', $ids)
                ->column('target_id');
        }

        $result = [];
        foreach ($items as $item) {
            $arr = $item->toArray();
            $arr['is_liked'] = in_array($item->id, $likedIds);
            $result[] = $arr;
        }

        // 附加评论者 / 被回复者 VIP
        UserResource::attachVipInListMulti($result, ['user', 'reply_to_user']);

        return $this->successPage([
            'list'      => $result,
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }

    /**
     * 删除评论
     * POST /api/comment/delete
     */
    public function delete(): Response
    {
        $userId    = $this->getUserId();
        $commentId = (int)$this->request->post('comment_id', 0);

        if ($commentId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        $comment = CommentModel::where('id', $commentId)
            ->where('status', CommentModel::STATUS_NORMAL)
            ->find();

        if (!$comment) {
            return $this->error(ErrorCode::COMMENT_NOT_FOUND);
        }
        if ((int)$comment->user_id !== $userId) {
            return $this->error(ErrorCode::COMMENT_NO_PERMISSION);
        }

        // 事务保护
        Db::startTrans();
        try {
            $comment->status = CommentModel::STATUS_DELETED;
            $comment->save();

            PostModel::where('id', $comment->post_id)->dec('comment_count', 1)->update([]);

            if ($comment->parent_id) {
                CommentModel::where('id', $comment->parent_id)->dec('reply_count', 1)->update([]);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Comment delete failed: " . $e->getMessage());
            return $this->error(ErrorCode::DB_ERROR);
        }

        return $this->success(null, '已删除');
    }

    private function sendCommentNotification(int $fromUserId, $comment, $post): void
    {
        try {
            $fromUser = \app\common\model\User::find($fromUserId);
            $nickname = htmlspecialchars($fromUser?->nickname ?? '有人', ENT_QUOTES, 'UTF-8');
            $contentPreview = mb_substr(strip_tags($comment->content), 0, 50);

            // 1. 回复评论 => 通知被回复者
            if ($comment->parent_id && $comment->reply_to_user_id && (int)$comment->reply_to_user_id !== $fromUserId) {
                $n = new Notification();
                $n->user_id    = (int)$comment->reply_to_user_id;
                $n->type       = Notification::TYPE_REPLY;
                $n->title      = '你的评论有新回复';
                $n->content    = "{$nickname} 回复了你：{$contentPreview}";
                $n->related_id = (int)$comment->post_id;
                $n->is_read    = 0;
                $n->created_at = date('Y-m-d H:i:s');
                $n->save();
            }

            // 2. 顶级评论 => 通知帖子作者
            $postOwnerId = (int)$post->user_id;
            if (!$comment->parent_id && $postOwnerId !== $fromUserId) {
                $n = new Notification();
                $n->user_id    = $postOwnerId;
                $n->type       = Notification::TYPE_COMMENT;
                $n->title      = '你的帖子有新评论';
                $n->content    = "{$nickname} 评论了你的帖子：{$contentPreview}";
                $n->related_id = (int)$comment->post_id;
                $n->is_read    = 0;
                $n->created_at = date('Y-m-d H:i:s');
                $n->save();
            }

            // 3. 回复别人的评论 => 通知父评论作者（如果不是被回复者也不是自己）
            if ($comment->parent_id) {
                $parentComment = CommentModel::find($comment->parent_id);
                if ($parentComment) {
                    $parentOwnerId = (int)$parentComment->user_id;
                    if ($parentOwnerId !== $fromUserId && $parentOwnerId !== (int)($comment->reply_to_user_id ?? 0)) {
                        $n = new Notification();
                        $n->user_id    = $parentOwnerId;
                        $n->type       = Notification::TYPE_REPLY;
                        $n->title      = '你的评论有新回复';
                        $n->content    = "{$nickname} 在你的评论下回复：{$contentPreview}";
                        $n->related_id = (int)$comment->post_id;
                        $n->is_read    = 0;
                        $n->created_at = date('Y-m-d H:i:s');
                        $n->save();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("Comment notification failed: " . $e->getMessage(), [
                'from' => $fromUserId, 'post' => $comment->post_id,
            ]);
        }
    }
}
