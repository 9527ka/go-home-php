<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\enum\PostStatus;
use app\common\enum\WalletTransactionType;
use app\common\exception\BusinessException;
use app\common\model\Post;
use app\common\model\PostFoundStory;
use app\common\model\Wallet;
use app\common\model\WalletSetting;
use app\common\model\WalletTransaction;
use think\facade\Db;

class FoundStoryService
{
    /**
     * 标记启事为已找到（不填故事）
     * 与 PostService::updateStatus 的 ACTIVE→FOUND 等价，这里作为入口方便 App 调
     */
    public static function markFound(int $postId, int $userId): void
    {
        PostService::updateStatus($postId, $userId, PostStatus::FOUND);
    }

    /**
     * 提交找回故事（自动把启事标为已找到）
     */
    public static function submitStory(int $postId, int $userId, array $data): PostFoundStory
    {
        $post = Post::find($postId);
        if (!$post) throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        if ((int)$post->user_id !== $userId) throw new BusinessException(ErrorCode::POST_NO_PERMISSION);

        // 仅允许已发布/已找到的启事提交故事，防止 pending/rejected/closed 状态下误提交
        if (!in_array((int)$post->status, [PostStatus::ACTIVE, PostStatus::FOUND], true)) {
            throw new BusinessException(ErrorCode::POST_ALREADY_CLOSED, '当前启事状态不允许提交找回故事');
        }

        // 内容校验
        $content = trim((string)($data['content'] ?? ''));
        if (mb_strlen($content) < 10) {
            throw new BusinessException(ErrorCode::PARAM_VALIDATE_FAIL, '找回经过至少 10 字');
        }
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $images = (array)($data['images'] ?? []);
        $imagesStr = implode(',', array_filter(array_map('strval', $images)));

        $foundAt = (string)($data['found_at'] ?? date('Y-m-d H:i:s'));
        $rewardEnabled = WalletSetting::getValue('found_story_reward_enabled', '1') === '1';
        $rewardAmount = $rewardEnabled ? (float)WalletSetting::getValue('found_story_reward', '10') : 0;

        return Db::transaction(function () use ($postId, $userId, $post, $content, $imagesStr, $foundAt, $rewardAmount) {
            // 启事状态改为 FOUND（若尚未标记）
            if ((int)$post->status === PostStatus::ACTIVE) {
                PostService::updateStatus($postId, $userId, PostStatus::FOUND);
            }

            $exists = PostFoundStory::where('post_id', $postId)->find();
            if ($exists) {
                // 二次提交覆盖并重新进入待审核
                $exists->content = $content;
                $exists->images  = $imagesStr;
                $exists->found_at = $foundAt;
                $exists->status  = PostFoundStory::STATUS_PENDING;
                $exists->audit_remark = '';
                $exists->audited_by   = null;
                $exists->audited_at   = null;
                $exists->is_rewarded  = 0; // 重新提交后奖励按新一轮发放
                $exists->reward_amount = $rewardAmount;
                $exists->save();
                return $exists;
            }

            return PostFoundStory::create([
                'post_id'       => $postId,
                'user_id'       => $userId,
                'content'       => $content,
                'images'        => $imagesStr,
                'found_at'      => $foundAt,
                'reward_amount' => $rewardAmount,
                'is_rewarded'   => 0,
                'status'        => PostFoundStory::STATUS_PENDING,
            ]);
        });
    }

    /**
     * 公开列表（仅 status=1）
     */
    public static function publicList(int $page = 1, int $pageSize = 20): array
    {
        $query = PostFoundStory::with(['user', 'post' => function ($q) {
            $q->field('id,user_id,category,name,appearance,lost_at,lost_city,created_at');
        }])
            ->where('status', PostFoundStory::STATUS_APPROVED)
            ->order('id', 'desc');
        $list = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        $items = array_map(fn($x) => $x->toArray(), $list->items());
        UserResource::attachVipInList($items, 'user');
        return [
            'list'      => $items,
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ];
    }

    /**
     * 详情
     */
    public static function detail(int $postId): ?array
    {
        $story = PostFoundStory::with(['user', 'post'])
            ->where('post_id', $postId)
            ->where('status', PostFoundStory::STATUS_APPROVED)
            ->find();
        if (!$story) return null;
        $data = $story->toArray();
        if (isset($data['user']) && is_array($data['user'])) {
            UserResource::attachVipSingle($data['user']);
        }
        return $data;
    }

    /**
     * 管理员审核通过 → 发放奖励
     */
    public static function approve(int $id, int $adminId, string $remark = ''): void
    {
        $story = PostFoundStory::find($id);
        if (!$story || (int)$story->status !== PostFoundStory::STATUS_PENDING) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '故事不存在或已处理');
        }

        Db::transaction(function () use ($story, $adminId, $remark) {
            $story->status       = PostFoundStory::STATUS_APPROVED;
            $story->audit_remark = $remark;
            $story->audited_by   = $adminId;
            $story->audited_at   = date('Y-m-d H:i:s');
            $story->save();

            $reward = (float)$story->reward_amount;
            if ($reward > 0 && (int)$story->is_rewarded === 0) {
                WalletService::getOrCreateWallet((int)$story->user_id);
                Db::table('wallets')
                    ->where('user_id', $story->user_id)
                    ->update([
                        'balance'    => Db::raw("balance + {$reward}"),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                $wallet = Wallet::where('user_id', $story->user_id)->find();
                $balanceAfter = (float)$wallet->balance;

                WalletTransaction::create([
                    'user_id'        => $story->user_id,
                    'type'           => WalletTransactionType::FOUND_STORY_REWARD,
                    'amount'         => $reward,
                    'balance_before' => bcsub((string)$balanceAfter, (string)$reward, 2),
                    'balance_after'  => $balanceAfter,
                    'related_id'     => $story->id,
                    'remark'         => '找回故事奖励',
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);

                $story->is_rewarded = 1;
                $story->save();
            }
        });
    }

    /**
     * 管理员驳回
     */
    public static function reject(int $id, int $adminId, string $remark): void
    {
        $story = PostFoundStory::find($id);
        if (!$story || (int)$story->status !== PostFoundStory::STATUS_PENDING) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '故事不存在或已处理');
        }
        $story->status       = PostFoundStory::STATUS_REJECTED;
        $story->audit_remark = $remark;
        $story->audited_by   = $adminId;
        $story->audited_at   = date('Y-m-d H:i:s');
        $story->save();
    }

    /**
     * 管理后台列表（可按 status 筛选）
     */
    public static function adminList(?int $status, int $page = 1, int $pageSize = 20): array
    {
        $query = PostFoundStory::with(['user', 'post' => function ($q) {
            $q->field('id,user_id,name,category,lost_city');
        }])->order('id', 'desc');
        if ($status !== null) $query->where('status', $status);
        $list = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        return $list->toArray();
    }
}
