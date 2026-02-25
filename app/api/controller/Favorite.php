<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use app\common\model\Favorite as FavoriteModel;
use app\common\model\Post as PostModel;
use think\Response;

class Favorite extends BaseApi
{
    /**
     * 收藏/取消收藏
     * POST /api/favorite/toggle
     *
     * @body post_id int 启事ID
     */
    public function toggle(): Response
    {
        $userId = $this->getUserId();
        $postId = (int)$this->request->post('post_id', 0);

        if ($postId <= 0) {
            return $this->error(ErrorCode::PARAM_MISSING);
        }

        // ⚠️ 修复：校验帖子是否存在
        $post = PostModel::find($postId);
        if (!$post) {
            return $this->error(ErrorCode::POST_NOT_FOUND);
        }

        $exists = FavoriteModel::where('user_id', $userId)
            ->where('post_id', $postId)
            ->find();

        if ($exists) {
            $exists->delete();
            return $this->success(['is_favorited' => false], '已取消收藏');
        }

        $fav = new FavoriteModel();
        $fav->user_id    = $userId;
        $fav->post_id    = $postId;
        $fav->created_at = date('Y-m-d H:i:s');
        $fav->save();

        return $this->success(['is_favorited' => true], '已收藏');
    }

    /**
     * 我的收藏列表
     * GET /api/favorite/list
     */
    public function list(): Response
    {
        $userId = $this->getUserId();
        $page = max(1, (int)$this->request->get('page', 1));

        $list = FavoriteModel::where('favorites.user_id', $userId)
            ->with(['post' => function ($q) {
                $q->with(['images' => function ($iq) {
                    $iq->where('sort_order', 0)->field('post_id,image_url,thumb_url');
                }]);
            }])
            ->order('favorites.created_at', 'desc')
            ->paginate(20, false, ['page' => $page]);

        return $this->successPage([
            'list'      => $list->items(),
            'page'      => $list->currentPage(),
            'page_size' => $list->listRows(),
            'total'     => $list->total(),
            'last_page' => $list->lastPage(),
        ]);
    }
}
