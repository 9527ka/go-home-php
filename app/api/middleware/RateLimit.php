<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use think\Request;
use think\Response;
use think\facade\Cache;

/**
 * 接口频率限制中间件
 * MVP阶段使用文件缓存，后续可切换 Redis
 */
class RateLimit
{
    // 每分钟最大请求数（默认）
    protected int $maxRequests = 60;
    // 时间窗口（秒）
    protected int $window = 60;

    // ⚠️ 安全加固：敏感接口单独限流（更严格）
    protected array $strictPaths = [
        'api/auth/login'        => 10,  // 登录：每分钟10次
        'api/auth/register'     => 5,   // 注册：每分钟5次
        'api/auth/apple-signin' => 10,  // Apple登录：每分钟10次
        'auth/login'            => 10,  // 多应用模式兼容
        'auth/register'         => 5,
        'auth/apple-signin'     => 10,
        'api/report/create'     => 10,  // 举报：每分钟10次
        'api/upload/image'      => 20,  // 上传：每分钟20次
        'api/upload/images'     => 10,  // 批量上传：每分钟10次
        'api/post/create'       => 5,   // 发布：每分钟5次
        'api/auth/quick-login'  => 10,  // 快速登录：每分钟10次
        'auth/quick-login'      => 10,
        'api/friend/request'    => 20,  // 好友请求：每分钟20次
        'api/group/create'      => 5,   // 创建群组：每分钟5次
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        $ip = $request->ip();
        $path = strtolower(trim($request->pathinfo(), '/'));
        $key = 'rate_limit:' . md5($ip . ':' . $path);

        // 根据路径确定限流阈值
        $limit = $this->strictPaths[$path] ?? $this->maxRequests;

        $current = Cache::get($key, 0);

        if ($current >= $limit) {
            throw new BusinessException(ErrorCode::RATE_LIMIT);
        }

        Cache::set($key, $current + 1, $this->window);

        return $next($request);
    }
}
