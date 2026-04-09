<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use app\common\model\Friendship;
use app\common\model\PrivateMessage;
use app\common\model\User;
use app\common\model\WalletSetting;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Cache;
use think\facade\Log;

class AuthService
{
    /**
     * 登录失败最大尝试次数
     */
    const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * 登录锁定时间（秒）
     */
    const LOCKOUT_DURATION = 900; // 15分钟

    /**
     * 获取 JWT 密钥（统一从 config/jwt.php 读取）
     */
    protected static function getJwtSecret(): string
    {
        return config('jwt.secret');
    }

    /**
     * 获取 JWT 过期时间（秒）
     */
    protected static function getJwtExpire(): int
    {
        return config('jwt.expire') ?: 86400 * 7;
    }

    /**
     * 注册
     */
    public static function register(string $account, string $password, int $accountType = 1): User
    {
        // 检查账号是否已存在
        $exists = User::where('account', $account)->find();
        if ($exists) {
            throw new BusinessException(ErrorCode::AUTH_ACCOUNT_EXISTS);
        }

        // 校验账号格式
        if ($accountType === 1 && !preg_match('/^1[3-9]\d{9}$/', $account)) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '手机号格式不正确');
        }
        if ($accountType === 2 && !filter_var($account, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '邮箱格式不正确');
        }

        // 密码强度校验
        if (strlen($password) < 6) {
            throw new BusinessException(ErrorCode::PARAM_VALIDATE_FAIL, '密码不能少于6个字符');
        }

        $user = new User();
        $user->account = $account;
        $user->account_type = $accountType;
        $user->password = $password; // 模型修改器自动 bcrypt
        $user->nickname = self::generateNickname();
        $user->status = 1;
        $user->save();

        Log::info("User registered: id={$user->id}, account={$account}");

        // 添加默认客服好友
        self::addDefaultServiceFriend($user->id);

        return $user;
    }

    /**
     * 登录
     */
    public static function login(string $account, string $password): array
    {
        // ⚠️ 安全：登录失败锁定机制
        $lockKey = 'login_lock:' . md5($account);
        $attemptKey = 'login_attempts:' . md5($account);

        if (Cache::get($lockKey)) {
            throw new BusinessException(
                ErrorCode::RATE_LIMIT,
                '登录失败次数过多，请15分钟后再试'
            );
        }

        $user = User::where('account', $account)->find();

        if (!$user) {
            self::recordLoginFailure($attemptKey, $lockKey);
            throw new BusinessException(ErrorCode::AUTH_ACCOUNT_NOT_FOUND);
        }

        if ($user->isBanned()) {
            throw new BusinessException(ErrorCode::USER_BANNED);
        }

        if (!$user->verifyPassword($password)) {
            self::recordLoginFailure($attemptKey, $lockKey);
            throw new BusinessException(ErrorCode::AUTH_PASSWORD_WRONG);
        }

        // 登录成功 — 清除失败计数
        Cache::delete($attemptKey);
        Cache::delete($lockKey);

        // 更新登录信息
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = request()->ip();
        $user->save();

        // 生成 Token
        $token = self::generateToken($user->id);

        Log::info("User login: id={$user->id}, ip=" . request()->ip());

        // 签到任务：每日登录
        try {
            TaskService::incrementTaskProgress($user->id, 'login');
        } catch (\Throwable $e) {
            Log::warning("Task progress (login) failed for user#{$user->id}: " . $e->getMessage());
        }

        return [
            'token'    => $token,
            'expires'  => time() + self::getJwtExpire(),
            'userInfo' => $user->hidden(['password'])->toArray(),
        ];
    }

    /**
     * 记录登录失败
     */
    protected static function recordLoginFailure(string $attemptKey, string $lockKey): void
    {
        $attempts = (int)Cache::get($attemptKey, 0) + 1;
        Cache::set($attemptKey, $attempts, self::LOCKOUT_DURATION);

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            Cache::set($lockKey, true, self::LOCKOUT_DURATION);
            Log::warning("Account locked due to too many login attempts: {$attemptKey}");
        }
    }

    /**
     * 生成 JWT Token
     */
    public static function generateToken(int $userId, string $role = 'user'): string
    {
        $now = time();
        $payload = [
            'iss'     => 'go_home',
            'iat'     => $now,
            'nbf'     => $now,     // 不早于签发时间生效
            'exp'     => $now + self::getJwtExpire(),
            'jti'     => bin2hex(random_bytes(16)), // 唯一标识防重放
            'user_id' => $userId,
            'role'    => $role,
        ];

        return JWT::encode($payload, self::getJwtSecret(), 'HS256');
    }

    /**
     * 解析 JWT Token
     */
    public static function parseToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::getJwtSecret(), 'HS256'));
            return (array)$decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_EXPIRED);
        } catch (\Exception $e) {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_INVALID);
        }
    }

    /**
     * Apple 授权登录
     *
     * @param string      $identityToken Apple 返回的 identityToken (JWT)
     * @param string      $userIdentifier Apple 用户唯一标识
     * @param string|null $fullName      用户名字（仅首次授权时有值）
     * @param string|null $email         用户邮箱（可能为私密邮箱中转地址）
     * @return array
     */
    public static function appleSignIn(
        string $identityToken,
        string $userIdentifier,
        ?string $fullName = null,
        ?string $email = null
    ): array {
        // 1. 验证 Apple identityToken
        $applePayload = self::verifyAppleToken($identityToken);

        // 2. 用 Apple token 中的 sub 字段（更可靠）
        $appleId = $applePayload['sub'] ?? '';
        if (empty($appleId)) {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_INVALID, 'Apple Token 无效');
        }

        // 3. 查找是否已有关联用户
        $user = User::where('apple_id', $appleId)->find();

        if ($user) {
            // 已有用户 → 直接登录
            if ($user->isBanned()) {
                throw new BusinessException(ErrorCode::USER_BANNED);
            }

            $user->last_login_at = date('Y-m-d H:i:s');
            $user->last_login_ip = request()->ip();
            $user->save();

            Log::info("Apple signin (existing): user_id={$user->id}");
        } else {
            // 新用户 → 自动注册
            $user = new User();
            $user->apple_id = $appleId;
            $user->auth_provider = 2; // Apple
            $user->account = $email ?: null;
            $user->account_type = $email ? 2 : 0;
            $user->nickname = !empty($fullName) ? htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') : self::generateNickname();
            $user->status = 1;
            $user->last_login_at = date('Y-m-d H:i:s');
            $user->last_login_ip = request()->ip();
            $user->save();

            Log::info("Apple signin (new): user_id={$user->id}");

            // 添加默认客服好友
            self::addDefaultServiceFriend($user->id);
        }

        $token = self::generateToken($user->id);

        return [
            'token'    => $token,
            'expires'  => time() + self::getJwtExpire(),
            'userInfo' => $user->hidden(['password'])->toArray(),
        ];
    }

    /**
     * 验证 Apple identityToken (JWT)
     *
     * Apple 使用 RS256 签名，公钥从 https://appleid.apple.com/auth/keys 获取
     * 简化验证：解码 payload 并校验 iss、exp、aud
     *
     * ⚠️ 生产环境建议使用完整的 RS256 签名验证
     */
    protected static function verifyAppleToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_INVALID, 'Apple Token 格式错误');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!$payload) {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_INVALID, 'Apple Token 解析失败');
        }

        if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_INVALID, 'Apple Token 签发者无效');
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_EXPIRED, 'Apple Token 已过期');
        }

        $bundleId = env('APPLE_BUNDLE_ID', '');
        if (!empty($bundleId) && ($payload['aud'] ?? '') !== $bundleId) {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_INVALID, 'Apple Token 目标应用不匹配');
        }

        return $payload;
    }

    /**
     * 游客一键快速登录
     * 自动生成临时账号和密码，直接注册并返回登录信息
     *
     * @return array
     */
    public static function quickLogin(): array
    {
        // 生成唯一的游客账号
        $guestId = 'guest_' . date('ymd') . '_' . bin2hex(random_bytes(4));
        // 生成随机密码（8位）
        $password = bin2hex(random_bytes(4));

        $user = new User();
        $user->account       = $guestId;
        $user->account_type  = 3; // 3=游客账号
        $user->password      = $password; // 模型修改器自动 bcrypt
        $user->auth_provider = 3; // 3=游客快速登录
        $user->nickname      = self::generateNickname();
        $user->status        = 1;
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = request()->ip();
        $user->save();

        Log::info("Guest quick login: user_id={$user->id}, account={$guestId}");

        // 添加默认客服好友
        self::addDefaultServiceFriend($user->id);

        $token = self::generateToken($user->id);

        return [
            'token'    => $token,
            'expires'  => time() + self::getJwtExpire(),
            'userInfo' => $user->hidden(['password'])->toArray(),
        ];
    }

    /**
     * 修改账号（手机号/邮箱）
     *
     * @param int    $userId
     * @param string $newAccount   新账号
     * @param int    $accountType  1=手机号 2=邮箱
     * @return User
     */
    public static function changeAccount(int $userId, string $newAccount, int $accountType): User
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::AUTH_ACCOUNT_NOT_FOUND);
        }

        // 校验账号格式
        if ($accountType === 1 && !preg_match('/^1[3-9]\d{9}$/', $newAccount)) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '手机号格式不正确');
        }
        if ($accountType === 2 && !filter_var($newAccount, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException(ErrorCode::PARAM_FORMAT_ERROR, '邮箱格式不正确');
        }

        // 检查新账号是否已被占用
        $exists = User::where('account', $newAccount)
            ->where('id', '<>', $userId)
            ->find();
        if ($exists) {
            throw new BusinessException(ErrorCode::AUTH_ACCOUNT_EXISTS, '该账号已被其他用户使用');
        }

        $user->account      = $newAccount;
        $user->account_type = $accountType;
        $user->save();

        Log::info("User changed account: user_id={$userId}, new_account={$newAccount}");

        return $user;
    }

    /**
     * 修改密码
     *
     * @param int         $userId
     * @param string|null $oldPassword 旧密码（游客用户首次设置密码可为空）
     * @param string      $newPassword 新密码
     * @return User
     */
    public static function changePassword(int $userId, ?string $oldPassword, string $newPassword): User
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::AUTH_ACCOUNT_NOT_FOUND);
        }

        // 如果用户已有密码（非游客首次设密码），需要验证旧密码
        $isGuestOrApple = in_array($user->auth_provider, [2, 3]);
        $hasPassword = !empty($user->password);

        if ($hasPassword && !$isGuestOrApple) {
            // 普通用户必须验证旧密码
            if (empty($oldPassword)) {
                throw new BusinessException(ErrorCode::PARAM_MISSING, '请输入旧密码');
            }
            if (!$user->verifyPassword($oldPassword)) {
                throw new BusinessException(ErrorCode::AUTH_PASSWORD_WRONG, '旧密码不正确');
            }
        }

        // 密码强度校验
        if (strlen($newPassword) < 6) {
            throw new BusinessException(ErrorCode::PARAM_VALIDATE_FAIL, '密码不能少于6个字符');
        }

        $user->password = $newPassword; // 模型修改器自动 bcrypt
        $user->save();

        Log::info("User changed password: user_id={$userId}");

        return $user;
    }

    /**
     * 为新用户分配客服好友 + 发送欢迎消息
     * 按容量自动分配：找第一个未满的客服账号
     */
    public static function addDefaultServiceFriend(int $userId): void
    {
        try {
            $capacity = (int)WalletSetting::getValue('service_users_per_account', '1000');
            if ($capacity <= 0) $capacity = 1000;

            // 查所有活跃客服，按 ID 顺序
            $serviceUsers = User::where('user_type', 1)
                ->where('status', 1)
                ->order('id', 'asc')
                ->select();

            if ($serviceUsers->isEmpty()) {
                return;
            }

            // 找第一个未满的客服
            $targetService = null;
            foreach ($serviceUsers as $su) {
                if ((int)$su->id === $userId) continue;
                $count = Friendship::where('user_id', (int)$su->id)->count();
                if ($count < $capacity) {
                    $targetService = $su;
                    break;
                }
            }

            // 全满则分配给最后一个（软上限）
            if (!$targetService) {
                $targetService = $serviceUsers->last();
                if ((int)$targetService->id === $userId) return;
            }

            $serviceId = (int)$targetService->id;
            $now = date('Y-m-d H:i:s');

            // 创建双向好友关系（防重复）
            if (!Friendship::where('user_id', $userId)->where('friend_id', $serviceId)->find()) {
                Friendship::create(['user_id' => $userId, 'friend_id' => $serviceId, 'created_at' => $now]);
            }
            if (!Friendship::where('user_id', $serviceId)->where('friend_id', $userId)->find()) {
                Friendship::create(['user_id' => $serviceId, 'friend_id' => $userId, 'created_at' => $now]);
            }

            // 客服发送欢迎消息
            PrivateMessage::create([
                'from_id'    => $serviceId,
                'to_id'      => $userId,
                'content'    => '欢迎使用回家了么！有任何问题可以随时联系客服。',
                'msg_type'   => 'text',
                'is_read'    => 0,
                'created_at' => $now,
            ]);

            Log::info("Assigned service account#{$serviceId} to user#{$userId}");
        } catch (\Throwable $e) {
            Log::warning("Add default service friend failed for user#{$userId}: " . $e->getMessage());
        }
    }

    /**
     * 生成随机昵称
     */
    protected static function generateNickname(): string
    {
        $prefix = ['热心', '善良', '温暖', '爱心', '阳光', '希望'];
        $suffix = ['市民', '伙伴', '志愿者', '用户', '朋友'];
        return $prefix[array_rand($prefix)] . $suffix[array_rand($suffix)] . rand(1000, 9999);
    }
}
