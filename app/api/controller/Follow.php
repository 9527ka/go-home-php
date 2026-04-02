<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\model\Follow as FollowModel;
use app\common\model\User as UserModel;
use app\common\model\Notification;
use think\facade\Db;
use think\facade\Log;
use think\Response;

class Follow extends BaseApi
{
    /**
     * 关注/取消关注
     * POST /api/follow/toggle
     *
     * @body user_id int 目标用户ID
     */
    public function toggle(): Response
    {
        $userId   = $this->getUserId();
        $targetId = (int)$this->request->post('user_id', 0);

        if ($targetId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }
        if ($targetId === $userId) {
            return $this->error(ErrorCode::FOLLOW_SELF);
        }

        $targetUser = UserModel::find($targetId);
        if (!$targetUser) {
            return $this->error(ErrorCode::AUTH_ACCOUNT_NOT_FOUND);
        }

        // 事务保护
        Db::startTrans();
        try {
            $exists = FollowModel::where('follower_id', $userId)
                ->where('following_id', $targetId)
                ->find();

            if ($exists) {
                $exists->delete();
                UserModel::where('id', $userId)->dec('following_count', 1)->update([]);
                UserModel::where('id', $targetId)->dec('follower_count', 1)->update([]);
                Db::commit();
                return $this->success([
                    'is_following' => false,
                    'is_mutual'    => false,
                ], '已取消关注');
            }

            $follow = new FollowModel();
            $follow->follower_id  = $userId;
            $follow->following_id = $targetId;
            $follow->created_at   = date('Y-m-d H:i:s');
            $follow->save();

            UserModel::where('id', $userId)->inc('following_count', 1)->update([]);
            UserModel::where('id', $targetId)->inc('follower_count', 1)->update([]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Follow toggle failed: " . $e->getMessage());
            return $this->error(ErrorCode::DB_ERROR);
        }

        // 检查是否互关
        $isMutual = FollowModel::where('follower_id', $targetId)
            ->where('following_id', $userId)
            ->count() > 0;

        // 发送通知（事务外执行）
        $this->sendFollowNotification($userId, $targetId);

        return $this->success([
            'is_following' => true,
            'is_mutual'    => $isMutual,
        ], '已关注');
    }

    /**
     * 关注状态查询
     * GET /api/follow/status
     */
    public function status(): Response
    {
        $userId   = $this->getUserId();
        $targetId = (int)$this->request->get('user_id', 0);

        if ($targetId <= 0) {
            return $this->success(['is_following' => false, 'is_mutual' => false]);
        }

        $isFollowing = FollowModel::where('follower_id', $userId)
            ->where('following_id', $targetId)
            ->count() > 0;

        $isFollowedBy = FollowModel::where('follower_id', $targetId)
            ->where('following_id', $userId)
            ->count() > 0;

        return $this->success([
            'is_following' => $isFollowing,
            'is_mutual'    => $isFollowing && $isFollowedBy,
        ]);
    }

    /**
     * 粉丝列表
     * GET /api/follow/followers
     */
    public function followers(): Response
    {
        $userId = (int)$this->request->get('user_id', 0);
        if ($userId <= 0) $userId = $this->getUserId();
        $page = max(1, (int)$this->request->get('page', 1));
        $myId = $this->getUserId();

        $list = FollowModel::where('following_id', $userId)
            ->with(['follower'])
            ->order('created_at', 'desc')
            ->paginate(20, false, ['page' => $page]);

        $items = $list->items();
        $followerIds = array_map(fn($item) => (int)$item->follower_id, $items);
        $myFollowingIds = [];
        if ($myId > 0 && !empty($followerIds)) {
            $myFollowingIds = FollowModel::where('follower_id', $myId)
                ->whereIn('following_id', $followerIds)
                ->column('following_id');
        }

        $result = [];
        foreach ($items as $item) {
            $arr = $item->toArray();
            $arr['is_following'] = in_array((int)$item->follower_id, $myFollowingIds);
            $result[] = $arr;
        }

        return $this->successPage([
            'list'      => $result,
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }

    /**
     * 关注列表
     * GET /api/follow/following
     */
    public function following(): Response
    {
        $userId = (int)$this->request->get('user_id', 0);
        if ($userId <= 0) $userId = $this->getUserId();
        $page = max(1, (int)$this->request->get('page', 1));

        $list = FollowModel::where('follower_id', $userId)
            ->with(['following'])
            ->order('created_at', 'desc')
            ->paginate(20, false, ['page' => $page]);

        $items = $list->items();
        $followingIds = array_map(fn($item) => (int)$item->following_id, $items);

        // 查互关：对方也关注了 userId
        $mutualIds = [];
        if (!empty($followingIds)) {
            $mutualIds = FollowModel::whereIn('follower_id', $followingIds)
                ->where('following_id', $userId)
                ->column('follower_id');
        }

        $result = [];
        foreach ($items as $item) {
            $arr = $item->toArray();
            $arr['is_mutual'] = in_array((int)$item->following_id, $mutualIds);
            $result[] = $arr;
        }

        return $this->successPage([
            'list'      => $result,
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }

    /**
     * 推荐关注
     * GET /api/follow/recommend
     */
    public function recommend(): Response
    {
        $userId = $this->getUserId();

        $followingIds = FollowModel::where('follower_id', $userId)->column('following_id');
        $excludeIds = array_merge($followingIds, [$userId]);

        $users = UserModel::whereNotIn('id', $excludeIds)
            ->where('status', 1)
            ->field('id,nickname,avatar,user_code,follower_count')
            ->order('follower_count', 'desc')
            ->limit(20)
            ->select();

        return $this->success(['list' => $users->toArray()]);
    }

    private function sendFollowNotification(int $fromUserId, int $toUserId): void
    {
        try {
            $fromUser = UserModel::find($fromUserId);
            $nickname = htmlspecialchars($fromUser?->nickname ?? '有人', ENT_QUOTES, 'UTF-8');

            $n = new Notification();
            $n->user_id    = $toUserId;
            $n->type       = Notification::TYPE_FOLLOW;
            $n->title      = '你有新的关注者';
            $n->content    = "{$nickname} 关注了你";
            $n->related_id = $fromUserId;
            $n->is_read    = 0;
            $n->created_at = date('Y-m-d H:i:s');
            $n->save();
        } catch (\Throwable $e) {
            Log::error("Follow notification failed: " . $e->getMessage(), [
                'from' => $fromUserId, 'to' => $toUserId,
            ]);
        }
    }
}
