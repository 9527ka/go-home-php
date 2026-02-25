<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用地址
    'app_host'         => env('APP_HOST', ''),
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 默认应用
    'default_app'      => 'api',
    // 默认时区
    'default_timezone'  => 'Asia/Shanghai',

    // 应用映射（自动多应用模式使用）
    'app_map'          => [],
    // 域名绑定（自动多应用模式使用）
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（自动多应用模式使用）
    'deny_app_list'    => ['common'],

    // 异常处理 handle 类
    'exception_handle' => \app\common\exception\ExceptionHandler::class,
];
