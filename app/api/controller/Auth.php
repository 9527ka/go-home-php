<?php
declare(strict_types=1);

namespace app\api\controller;

use app\api\validate\AuthValidate;
use app\common\enum\ErrorCode;
use app\common\model\User;
use app\common\service\AuthService;
use think\Response;

class Auth extends BaseApi
{
    /**
     * 注册
     * POST /api/auth/register
     *
     * @body account       string 手机号或邮箱
     * @body password      string 密码(6-32位)
     * @body account_type  int    1=手机号 2=邮箱
     */
    public function register(): Response
    {
        $params = $this->request->post();

        // 参数校验
        validate(AuthValidate::class)->scene('register')->check($params);

        $user = AuthService::register(
            $params['account'],
            $params['password'],
            (int)($params['account_type'] ?? 1)
        );

        // 注册后自动登录
        $loginData = AuthService::login($params['account'], $params['password']);

        return $this->success($loginData, '注册成功');
    }

    /**
     * 登录
     * POST /api/auth/login
     *
     * @body account   string 手机号或邮箱
     * @body password  string 密码
     */
    public function login(): Response
    {
        $params = $this->request->post();

        validate(AuthValidate::class)->scene('login')->check($params);

        $data = AuthService::login($params['account'], $params['password']);

        return $this->success($data, '登录成功');
    }

    /**
     * Apple 授权登录
     * POST /api/auth/apple-signin
     *
     * @body identity_token  string Apple 返回的 identityToken
     * @body user_identifier string Apple 用户唯一标识
     * @body full_name       string 用户名字（可选，仅首次授权有值）
     * @body email           string 用户邮箱（可选）
     */
    public function appleSignIn(): Response
    {
        $identityToken = $this->request->post('identity_token', '');
        $userIdentifier = $this->request->post('user_identifier', '');
        $fullName = $this->request->post('full_name');
        $email = $this->request->post('email');

        if (empty($identityToken) || empty($userIdentifier)) {
            return $this->error(ErrorCode::PARAM_MISSING, '缺少 Apple 授权信息');
        }

        $data = AuthService::appleSignIn($identityToken, $userIdentifier, $fullName, $email);

        return $this->success($data, '登录成功');
    }

    /**
     * 游客一键快速登录
     * POST /api/auth/quick-login
     *
     * 无需参数，自动生成临时账号并返回登录信息
     */
    public function quickLogin(): Response
    {
        $data = AuthService::quickLogin();
        return $this->success($data, '登录成功');
    }

    /**
     * 修改账号（手机号/邮箱）
     * POST /api/auth/change-account
     *
     * @body account      string 新账号（手机号或邮箱）
     * @body account_type int    1=手机号 2=邮箱
     */
    public function changeAccount(): Response
    {
        $userId = $this->getUserId();
        $account = $this->request->post('account', '');
        $accountType = (int)$this->request->post('account_type', 1);

        if (empty($account)) {
            return $this->error(ErrorCode::PARAM_MISSING, '请输入新账号');
        }

        $user = AuthService::changeAccount($userId, $account, $accountType);

        return $this->success($user->hidden(['password', 'deleted_at']), '账号修改成功');
    }

    /**
     * 修改密码
     * POST /api/auth/change-password
     *
     * @body old_password string 旧密码（游客/Apple用户首次设置可空）
     * @body new_password string 新密码(6-32位)
     */
    public function changePassword(): Response
    {
        $userId = $this->getUserId();
        $oldPassword = $this->request->post('old_password', '');
        $newPassword = $this->request->post('new_password', '');

        if (empty($newPassword)) {
            return $this->error(ErrorCode::PARAM_MISSING, '请输入新密码');
        }

        $user = AuthService::changePassword($userId, $oldPassword ?: null, $newPassword);

        return $this->success($user->hidden(['password', 'deleted_at']), '密码修改成功');
    }

    /**
     * 获取当前用户信息
     * GET /api/auth/profile
     */
    public function profile(): Response
    {
        $userId = $this->getUserId();
        $user = User::find($userId);

        if (!$user) {
            return $this->error(ErrorCode::AUTH_ACCOUNT_NOT_FOUND);
        }

        return $this->success($user->hidden(['password', 'deleted_at']));
    }

    /**
     * 更新用户信息
     * POST /api/auth/update
     *
     * @body nickname      string 昵称
     * @body avatar        string 头像URL
     * @body contact_phone string 联系电话
     */
    public function update(): Response
    {
        $userId = $this->getUserId();
        $user = User::find($userId);

        if (!$user) {
            return $this->error(ErrorCode::AUTH_ACCOUNT_NOT_FOUND);
        }

        $allow = ['nickname', 'avatar', 'contact_phone'];
        $data = $this->request->only($allow, 'post');

        // ⚠️ 修复：对用户输入做 XSS 净化
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
                }
            }
            // 头像路径校验：允许上传头像和系统预设头像
            if (!empty($data['avatar'])
                && !preg_match('#^/uploads/\d{8}/[a-f0-9]+\.\w+$#', $data['avatar'])
                && !preg_match('#^/system/avatars/avatar_\d+\.svg$#', $data['avatar'])
            ) {
                return $this->error(ErrorCode::PARAM_FORMAT_ERROR, '头像路径无效');
            }
            $user->save($data);

            // 签到任务：完善资料
            try {
                \app\common\service\TaskService::incrementTaskProgress($userId, 'complete_profile');
            } catch (\Throwable $e) {
                // 静默失败，不影响主流程
            }
        }

        return $this->success($user->hidden(['password', 'deleted_at']), '更新成功');
    }

    /**
     * 注销账号（软删除）
     * POST /api/auth/delete-account
     *
     * @body password string 密码确认（非Apple用户必填）
     * @body confirm  bool   二次确认（Apple用户必填，值为true）
     */
    public function deleteAccount(): Response
    {
        $userId = $this->getUserId();
        $user = User::find($userId);

        if (!$user) {
            return $this->error(ErrorCode::AUTH_ACCOUNT_NOT_FOUND);
        }

        // 区分 Apple 用户和普通用户的验证方式
        if (($user->auth_provider ?? 1) == 2) {
            // Apple 用户：双重确认
            $confirm = $this->request->post('confirm');
            if ($confirm !== true && $confirm !== 'true' && $confirm !== '1') {
                return $this->error(ErrorCode::PARAM_VALIDATE_FAIL, '请确认注销操作');
            }
        } else {
            // 普通用户：密码验证
            $password = $this->request->post('password', '');
            if (empty($password)) {
                return $this->error(ErrorCode::PARAM_MISSING, '请输入密码以确认注销');
            }
            if (!password_verify($password, $user->password)) {
                return $this->error(ErrorCode::AUTH_PASSWORD_WRONG, '密码错误，无法注销');
            }
        }

        // 软删除：设置 status=2 和 deleted_at
        $user->status     = 2;
        $user->deleted_at = date('Y-m-d H:i:s');
        $user->save();

        \think\facade\Log::info("User deleted account: id={$userId}");

        return $this->success(null, '账号已注销');
    }
}
