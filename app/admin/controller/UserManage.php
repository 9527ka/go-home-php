<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\model\User;
use think\Request;
use think\Response;

/**
 * 管理后台 - 用户管理
 */
class UserManage
{
    /**
     * 用户列表
     * GET /admin/user/list
     */
    public function list(Request $request): Response
    {
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);
        $keyword = $request->get('keyword', '');
        $status = $request->get('status', '');

        $query = User::order('id', 'desc');

        if ($keyword !== '') {
            $query->where('nickname|account', 'like', "%{$keyword}%");
        }

        if ($status !== '') {
            $query->where('status', (int)$status);
        }

        $total = (clone $query)->count();
        $list = $query->page($page, $limit)->select();

        return \json([
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
     * 更新用户状态
     * POST /admin/user/status
     */
    public function updateStatus(Request $request): Response
    {
        $id = (int)$request->post('id');
        $status = (int)$request->post('status');

        $user = User::find($id);
        if (!$user) {
            return json(['code' => ErrorCode::AUTH_ACCOUNT_NOT_FOUND, 'msg' => '用户不存在']);
        }

        $user->status = $status;
        $user->save();

        return json(['code' => 0, 'msg' => '操作成功']);
    }
}
