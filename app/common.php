<?php
// +----------------------------------------------------------------------
// | 公共函数文件
// +----------------------------------------------------------------------

// 这里可以放置全局辅助函数

/**
 * 生成成功的 JSON 响应数组
 */
function api_success($data = null, string $msg = 'ok'): array
{
    return ['code' => 0, 'msg' => $msg, 'data' => $data, 'timestamp' => time()];
}

/**
 * 生成失败的 JSON 响应数组
 */
function api_error(int $code, string $msg = 'error', $data = null): array
{
    return ['code' => $code, 'msg' => $msg, 'data' => $data, 'timestamp' => time()];
}
