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
        'auth/quick-login',
        'post/list',
        'post/detail',
        'clue/list',
        'chat/history',
        // 兼容全局路由模式
        'api/auth/login',
        'api/auth/register',
        'api/auth/apple-signin',
        'api/auth/quick-login',
        'api/post/list',
        'api/post/detail',
        'api/clue/list',
        'api/chat/history',
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        // ⚠️ 安全修复：精确匹配白名单，防止 api/auth/loginXXX 绕过
        $path = strtolower(trim($request->pathinfo(), '/'));
        $isPublic = in_array($path, $this->except, true);

        // 公开接口：尝试解析token（可选），以便识别登录用户身份
        if ($isPublic) {
            $this->tryParseToken($request);
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

    /**
     * 尝试解析 Token（不强制要求登录）
     * 用于公开接口中识别已登录用户，例如帖子详情接口需要识别帖子所有者
     */
    protected function tryParseToken(Request $request): void
    {
        try {
            $token = $request->header('Authorization', '');
            if (str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
            }

            if (empty($token)) {
                return;
            }

            $payload = AuthService::parseToken($token);
            $userId = (int)($payload['user_id'] ?? 0);
            if ($userId <= 0) {
                return;
            }

            $user = User::find($userId);
            if ($user && $user->isNormal()) {
                $request->userId = $userId;
                $request->userRole = $payload['role'] ?? 'user';
            }
        } catch (\Exception $e) {
            // 公开接口解析失败不影响正常访问
        }
    }
}
