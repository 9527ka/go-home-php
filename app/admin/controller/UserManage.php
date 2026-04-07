<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\model\User;
use app\common\model\Wallet;
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

        $query = User::with(['wallet'])->order('id', 'desc');

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

    /**
     * 更新用户信息（帐号、密码、余额）
     * POST /admin/user/update
     */
    public function update(Request $request): Response
    {
        $id = (int)$request->post('id');
        $user = User::find($id);
        if (!$user) {
            return json(['code' => ErrorCode::AUTH_ACCOUNT_NOT_FOUND, 'msg' => '用户不存在']);
        }

        $account = $request->post('account');
        $password = $request->post('password');
        $balance = $request->post('balance');

        // 更新帐号
        if ($account !== null && $account !== '') {
            // 检查帐号唯一性
            $exists = User::where('account', $account)->where('id', '<>', $id)->find();
            if ($exists) {
                return json(['code' => 400, 'msg' => '该帐号已被其他用户使用']);
            }
            $user->account = $account;
        }

        // 更新密码
        if ($password !== null && $password !== '') {
            if (mb_strlen($password) < 6) {
                return json(['code' => 400, 'msg' => '密码长度不能少于6位']);
            }
            $user->password = $password; // setPasswordAttr 自动 bcrypt
        }

        $user->save();

        // 更新余额
        if ($balance !== null && $balance !== '') {
            $balanceVal = (float)$balance;
            if ($balanceVal < 0) {
                return json(['code' => 400, 'msg' => '余额不能为负数']);
            }

            $wallet = Wallet::where('user_id', $id)->find();
            if ($wallet) {
                $wallet->balance = $balanceVal;
                $wallet->save();
            } else {
                $wallet = new Wallet();
                $wallet->user_id = $id;
                $wallet->balance = $balanceVal;
                $wallet->frozen_balance = 0;
                $wallet->total_recharged = 0;
                $wallet->total_withdrawn = 0;
                $wallet->total_donated = 0;
                $wallet->total_received = 0;
                $wallet->status = 1;
                $wallet->save();
            }
        }

        return json(['code' => 0, 'msg' => '更新成功']);
    }
}
