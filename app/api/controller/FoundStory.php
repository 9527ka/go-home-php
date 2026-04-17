<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\service\FoundStoryService;
use think\Response;

class FoundStory extends BaseApi
{
    /**
     * 仅标记启事为已找到（不填故事）
     * POST /api/post/{id}/mark-found   — 路由中以 id 参数传入
     * 本项目路由采用查询参数风格：POST /api/post/markFound { id }
     */
    public function markFound(): Response
    {
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) return $this->error(ErrorCode::PARAM_MISSING);
        FoundStoryService::markFound($id, $this->getUserId());
        return $this->success(null, '已标记为已找到');
    }

    /**
     * 提交找回故事
     * POST /api/post/found-story   { post_id, content, images[], found_at? }
     */
    public function submit(): Response
    {
        $postId = (int)$this->request->post('post_id', 0);
        if ($postId <= 0) return $this->error(ErrorCode::PARAM_MISSING);
        $data = [
            'content'  => $this->request->post('content', ''),
            'images'   => $this->request->post('images', []),
            'found_at' => $this->request->post('found_at'),
        ];
        $story = FoundStoryService::submitStory($postId, $this->getUserId(), $data);
        return $this->success([
            'id'            => $story->id,
            'status'        => $story->status,
            'reward_amount' => (float)$story->reward_amount,
        ], '提交成功，等待审核');
    }

    /**
     * 公开找回故事列表
     * GET /api/post/found-stories?page=
     */
    public function list(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));
        return $this->successPage(FoundStoryService::publicList($page));
    }

    /**
     * 找回故事详情
     * GET /api/post/found-story?post_id=
     */
    public function detail(): Response
    {
        $postId = (int)$this->request->get('post_id', 0);
        if ($postId <= 0) return $this->error(ErrorCode::PARAM_MISSING);
        $data = FoundStoryService::detail($postId);
        if ($data === null) return $this->error(ErrorCode::POST_NOT_FOUND, '故事不存在');
        return $this->success($data);
    }
}
