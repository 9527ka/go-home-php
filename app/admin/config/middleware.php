<?php
// +----------------------------------------------------------------------
// | Admin 应用中间件定义
// +----------------------------------------------------------------------

return [
    // Admin 应用全局中间件
    // CORS + JSON 响应格式（复用 api 模块的中间件）
    \app\api\middleware\ApiResponse::class,
    // 频率限制
    \app\api\middleware\RateLimit::class,
];
