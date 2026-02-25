<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\model\Admin;
use think\Request;
use think\Response;

/**
 * 管理后台 - 管理员管理
 */
class AdminManage
{
    /**
     * 管理员列表
     * GET /admin/manager/list
     */
    public function list(Request $request): Response
    {
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);

        $query = Admin::order('id', 'asc');
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
     * 添加管理员
     * POST /admin/manager/create
     */
    public function create(Request $request): Response
    {
        $username = trim($request->post('username', ''));
        $password = $request->post('password', '');
        $realname = trim($request->post('realname', ''));
        $role = (int)$request->post('role', 1);

        if (!$username || !$password) {
            return json(['code' => ErrorCode::PARAM_MISSING, 'msg' => '用户名和密码不能为空']);
        }

        if (Admin::where('username', $username)->find()) {
            return json(['code' => ErrorCode::AUTH_ACCOUNT_EXISTS, 'msg' => '用户名已存在']);
        }

        $admin = new Admin();
        $admin->username = $username;
        $admin->password = $password; // 模型中有 setPasswordAttr 自动加密
        $admin->realname = $realname;
        $admin->role = $role;
        $admin->status = 1;
        $admin->save();

        return json(['code' => 0, 'msg' => '创建成功']);
    }

    /**
     * 更新管理员信息
     * POST /admin/manager/update
     */
    public function update(Request $request): Response
    {
        $id = (int)$request->post('id');
        $realname = trim($request->post('realname', ''));
        $role = (int)$request->post('role');
        $status = (int)$request->post('status');
        $password = $request->post('password', '');

        $admin = Admin::find($id);
        if (!$admin) {
            return json(['code' => ErrorCode::AUTH_ACCOUNT_NOT_FOUND, 'msg' => '管理员不存在']);
        }

        if ($realname) $admin->realname = $realname;
        if ($role) $admin->role = $role;
        if ($status) $admin->status = $status;
        if ($password) $admin->password = $password;

        $admin->save();

        return json(['code' => 0, 'msg' => '更新成功']);
    }

    /**
     * 删除管理员
     * POST /admin/manager/delete
     */
    public function delete(Request $request): Response
    {
        $id = (int)$request->post('id');
        if ($id === 1) {
            return json(['code' => ErrorCode::PARAM_VALIDATE_FAIL, 'msg' => '超级管理员不能删除']);
        }

        $admin = Admin::find($id);
        if ($admin) {
            $admin->delete();
        }

        return json(['code' => 0, 'msg' => '删除成功']);
    }
}
