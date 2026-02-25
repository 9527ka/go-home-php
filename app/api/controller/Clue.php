<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\ClueValidate;
use app\common\enum\ErrorCode;
use app\common\enum\PostStatus;
use app\common\exception\BusinessException;
use app\common\model\Clue as ClueModel;
use app\common\model\Post as PostModel;
use app\common\service\NotifyService;
use think\facade\Db;
use think\facade\Log;
use think\Response;

class Clue extends BaseApi
{
    /**
     * 提交线索
     * POST /api/clue/create
     *
     * @body post_id int    关联启事ID
     * @body content string 线索内容(≥5字)
     * @body images  array  图片URL列表(≤3张)
     * @body contact string 联系方式(可选)
     */
    public function create(): Response
    {
        $params = $this->request->post();

        validate(ClueValidate::class)->check($params);

        $postId = (int)$params['post_id'];
        $userId = $this->getUserId();

        // 校验启事是否存在且为已发布状态
        $post = PostModel::find($postId);
        if (!$post || $post->status !== PostStatus::ACTIVE) {
            throw new BusinessException(ErrorCode::POST_NOT_FOUND);
        }

        Db::startTrans();
        try {
            $clue = new ClueModel();
            $clue->post_id = $postId;
            $clue->user_id = $userId;
            $clue->content = htmlspecialchars(trim($params['content']), ENT_QUOTES, 'UTF-8');

            // ⚠️ 修复：线索图片路径同样需要校验，防止路径遍历
            $rawImages = $params['images'] ?? [];
            if (is_array($rawImages)) {
                $validImages = [];
                foreach ($rawImages as $url) {
                    if (is_string($url) && preg_match('#^/uploads/\d{8}/[a-f0-9]+\.\w+$#', $url)) {
                        $validImages[] = $url;
                    }
                }
                $clue->images = $validImages;
            } else {
                $clue->images = '';
            }

            $clue->contact = htmlspecialchars(trim($params['contact'] ?? ''), ENT_QUOTES, 'UTF-8');
            $clue->status  = 1;
            $clue->save();

            // 更新启事线索计数
            PostModel::where('id', $postId)->inc('clue_count')->update();

            Db::commit();

            // 通知发布者（异步思想，但 MVP 同步写）
            NotifyService::notifyNewClue($post->user_id, $postId, $post->name);

            Log::info("Clue created: id={$clue->id}, post={$postId}, user={$userId}");

            return $this->success(['id' => $clue->id], '线索提交成功');

        } catch (BusinessException $e) {
            Db::rollback();
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("Clue create failed: " . $e->getMessage());
            throw new BusinessException(ErrorCode::DB_ERROR);
        }
    }

    /**
     * 获取启事下的线索列表
     * GET /api/clue/list
     *
     * @query post_id   int 启事ID
     * @query page      int 页码
     * @query page_size int 每页条数
     */
    public function list(): Response
    {
        $postId = (int)$this->request->get('post_id', 0);
        $page = max(1, (int)$this->request->get('page', 1));
        $pageSize = 20;

        if ($postId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING, '缺少启事ID');
        }

        $list = ClueModel::where('post_id', $postId)
            ->where('status', 1)
            ->with(['user'])
            ->order('created_at', 'desc')
            ->paginate($pageSize, false, ['page' => $page]);

        return $this->successPage([
            'list'      => $list->items(),
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }
}
