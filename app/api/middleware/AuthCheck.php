<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use app\common\model\User;
use app\common\service\AuthService;
use think\Request;
use think\Response;

/**
 * API Token 鉴权中间件
 */
class AuthCheck
{
    /**
     * 不需要登录的白名单路由（精确匹配，防绕过）
     * 兼容多应用模式（带/不带 api/ 前缀两种路径格式）
     */
    protected array $except = [
        'auth/login',
        'auth/register',
        'auth/apple-signin',
        'post/list',
        'post/detail',
        'clue/list',
        'chat/history',
        // 兼容全局路由模式
        'api/auth/login',
        'api/auth/register',
        'api/auth/apple-signin',
        'api/post/list',
        'api/post/detail',
        'api/clue/list',
        'api/chat/history',
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        // ⚠️ 安全修复：精确匹配白名单，防止 api/auth/loginXXX 绕过
        $path = strtolower(trim($request->pathinfo(), '/'));
        if (in_array($path, $this->except, true)) {
            return $next($request);
        }

        // 从 Header 获取 Token
        $token = $request->header('Authorization', '');
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            throw new BusinessException(ErrorCode::AUTH_NOT_LOGIN);
        }

        try {
            $payload = AuthService::parseToken($token);

            // ⚠️ 安全修复：校验用户是否仍然有效（防止封禁用户继续操作）
            $userId = (int)($payload['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new BusinessException(ErrorCode::AUTH_TOKEN_INVALID);
            }

            $user = User::find($userId);
            if (!$user || !$user->isNormal()) {
                throw new BusinessException(ErrorCode::AUTH_ACCOUNT_DISABLED);
            }

            // 将用户信息注入 request
            $request->userId = $userId;
            $request->userRole = $payload['role'] ?? 'user';
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_INVALID);
        }

        return $next($request);
    }
}
