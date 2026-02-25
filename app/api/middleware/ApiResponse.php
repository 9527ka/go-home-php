<?php
declare(strict_types=1);

namespace app\api\middleware;

use think\Request;
use think\Response;

/**
 * 统一 JSON 响应格式中间件
 * 设置跨域头 + 确保返回 JSON
 */
class ApiResponse
{
    public function handle(Request $request, \Closure $next): Response
    {
        $origin = $request->header('Origin', '*');

        // 处理 OPTIONS 预检请求
        if ($request->isOptions()) {
            return Response::create('', 'json', 204)->header([
                'Access-Control-Allow-Origin'  => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-Lang',
                'Access-Control-Max-Age'       => '86400',
            ]);
        }

        /** @var Response $response */
        $response = $next($request);

        // 添加跨域头和安全头
        $response->header([
            'Access-Control-Allow-Origin'  => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-Lang',
            'Content-Type'                 => 'application/json; charset=utf-8',
            'X-Content-Type-Options'       => 'nosniff',
            'X-Frame-Options'              => 'DENY',
        ]);

        return $response;
    }
}
