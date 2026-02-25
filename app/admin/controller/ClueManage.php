<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\model\Clue;
use think\Request;
use think\Response;

/**
 * 管理后台 - 线索管理
 */
class ClueManage
{
    /**
     * 线索列表
     * GET /admin/clue/list
     */
    public function list(Request $request): Response
    {
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);
        $postId = $request->get('post_id');
        $status = $request->get('status');

        $query = Clue::with(['user', 'post'])->order('id', 'desc');

        if ($postId) {
            $query->where('post_id', (int)$postId);
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }

        $total = (clone $query)->count();
        $list = $query->page($page, $limit)->select();

        return json([
            'code' => 0,
            'data' => [
                'list'  => $list,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
            ]
        ]);
    }

    /**
     * 删除线索（逻辑删除）
     * POST /admin/clue/delete
     */
    public function delete(Request $request): Response
    {
        $id = (int)$request->post('id');
        $clue = Clue::find($id);
        if (!$clue) {
            return json(['code' => ErrorCode::CLUE_NOT_FOUND, 'msg' => '线索不存在']);
        }

        $clue->delete();

        return json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 更新线索状态
     * POST /admin/clue/status
     */
    public function updateStatus(Request $request): Response
    {
        $id = (int)$request->post('id');
        $status = (int)$request->post('status');

        $clue = Clue::find($id);
        if (!$clue) {
            return json(['code' => ErrorCode::CLUE_NOT_FOUND, 'msg' => '线索不存在']);
        }

        $clue->status = $status;
        $clue->save();

        return json(['code' => 0, 'msg' => '操作成功']);
    }
}
