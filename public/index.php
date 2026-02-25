<?php
// +----------------------------------------------------------------------
// | 回家了么 - Web 入口文件
// +----------------------------------------------------------------------

namespace think;

// 加载基础文件
require __DIR__ . '/../vendor/autoload.php';
//支持跨域
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: x-requested-with, content-type, token, x-lang, authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 执行HTTP应用并响应
$http = (new App())->http;
$response = $http->run();
$response->send();
$http->end($response);
