<?php
declare(strict_types=1);

namespace app\admin\middleware;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use app\common\service\AuthService;
use think\Request;
use think\Response;

/**
 * 管理后台鉴权中间件
 */
class AdminAuth
{
    protected array $except = [
        'auth/login',         // 多应用模式下的路径
        'admin/auth/login',   // 兼容非多应用模式
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        // ⚠️ 修复：使用精确匹配防止路径绕过（如 admin/auth/loginXXX）
        $path = strtolower(trim($request->pathinfo(), '/'));
        if (in_array($path, $this->except, true)) {
            return $next($request);
        }

        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            throw new BusinessException(ErrorCode::AUTH_NOT_LOGIN);
        }

        try {
            $payload = AuthService::parseToken($token);
            if (($payload['role'] ?? '') !== 'admin') {
                throw new BusinessException(ErrorCode::AUTH_ADMIN_DENIED);
            }
            $request->adminId = $payload['user_id'];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(ErrorCode::AUTH_TOKEN_INVALID);
        }

        return $next($request);
    }
}
