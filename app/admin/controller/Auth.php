<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use app\common\model\Admin;
use app\common\service\AuthService;
use think\facade\Cache;
use think\facade\Log;
use think\Request;
use think\Response;

/**
 * 管理后台 - 认证控制器
 */
class Auth
{
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_DURATION   = 900; // 15 分钟

    /**
     * 管理员登录
     * POST /admin/auth/login
     *
     * @body username string 用户名
     * @body password string 密码
     */
    public function login(Request $request): Response
    {
        $username = trim($request->post('username', ''));
        $password = $request->post('password', '');

        if (empty($username) || empty($password)) {
            return json(['code' => ErrorCode::PARAM_MISSING, 'msg' => '请输入用户名和密码']);
        }

        // 登录锁定检查
        $lockKey = 'admin_lock:' . md5($username);
        $attemptKey = 'admin_attempts:' . md5($username);
        if (Cache::get($lockKey)) {
            return json(['code' => ErrorCode::RATE_LIMIT, 'msg' => '登录失败次数过多，请15分钟后再试']);
        }

        // 查找管理员
        $admin = Admin::where('username', $username)->find();
        if (!$admin) {
            self::recordFailure($attemptKey, $lockKey);
            return json(['code' => ErrorCode::AUTH_ACCOUNT_NOT_FOUND, 'msg' => '用户名或密码错误']);
        }

        // 检查状态
        if ($admin->status != 1) {
            return json(['code' => ErrorCode::AUTH_ACCOUNT_DISABLED, 'msg' => '该管理员账号已被禁用']);
        }

        // 验证密码
        if (!$admin->verifyPassword($password)) {
            self::recordFailure($attemptKey, $lockKey);
            return json(['code' => ErrorCode::AUTH_PASSWORD_WRONG, 'msg' => '用户名或密码错误']);
        }

        // 登录成功，清除计数
        Cache::delete($attemptKey);
        Cache::delete($lockKey);

        // 更新登录时间
        $admin->last_login_at = date('Y-m-d H:i:s');
        $admin->last_login_ip = $request->ip();
        $admin->save();

        // 生成 Token（role=admin）
        $token = AuthService::generateToken($admin->id, 'admin');

        Log::info("Admin login: id={$admin->id}, username={$username}, ip={$request->ip()}");

        return json([
            'code' => 0,
            'msg'  => '登录成功',
            'data' => [
                'token' => $token,
                'admin' => [
                    'id'       => $admin->id,
                    'username' => $admin->username,
                    'realname' => $admin->realname,
                    'role'     => $admin->role,
                ],
            ],
        ]);
    }

    /**
     * 获取当前管理员信息
     * GET /admin/auth/info
     */
    public function info(Request $request): Response
    {
        $adminId = (int)($request->adminId ?? 0);
        $admin = Admin::find($adminId);
        if (!$admin) {
            throw new BusinessException(ErrorCode::AUTH_ACCOUNT_NOT_FOUND);
        }

        return json([
            'code' => 0,
            'data' => [
                'id'       => $admin->id,
                'username' => $admin->username,
                'realname' => $admin->realname,
                'role'     => $admin->role,
            ],
        ]);
    }

    /**
     * 仪表盘统计
     * GET /admin/dashboard
     */
    public function dashboard(Request $request): Response
    {
        $stats = [
            'pending_posts'   => \app\common\model\Post::where('status', 0)->count(),
            'active_posts'    => \app\common\model\Post::where('status', 1)->count(),
            'total_posts'     => \app\common\model\Post::count(),
            'pending_reports' => \app\common\model\Report::where('status', 0)->count(),
            'total_users'     => \app\common\model\User::count(),
            'today_posts'     => \app\common\model\Post::whereDay('created_at')->count(),
            'today_clues'     => \app\common\model\Clue::whereDay('created_at')->count(),
        ];

        return json(['code' => 0, 'data' => $stats]);
    }

    /**
     * 记录登录失败
     */
    protected static function recordFailure(string $attemptKey, string $lockKey): void
    {
        $attempts = (int)Cache::get($attemptKey, 0) + 1;
        Cache::set($attemptKey, $attempts, self::LOCKOUT_DURATION);
        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            Cache::set($lockKey, true, self::LOCKOUT_DURATION);
        }
    }
}
