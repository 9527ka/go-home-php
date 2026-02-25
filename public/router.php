<?php
// +----------------------------------------------------------------------
// | PHP 内置 Web 服务器路由文件 (开发环境用)
// | 使用方式: php -S localhost:8000 router.php
// +----------------------------------------------------------------------

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 开发环境 CORS — 所有请求（包括静态文件）都需要
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Lang, x-lang');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

// 管理后台页面：仅 /admin 或 /admin/ 返回 index.html
if (preg_match('#^/admin/?$#', $uri)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/admin/index.html');
    return true;
}

// 非 /admin/ 开头的请求，如果是静态文件则手动输出（return false 会丢弃 CORS 头）
if (!preg_match('#^/admin/#', $uri) && is_file(__DIR__ . $uri)) {
    $file = __DIR__ . $uri;
    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json',
        'html' => 'text/html', 'htm' => 'text/html', 'txt' => 'text/plain',
        'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'ttf' => 'font/ttf', 'otf' => 'font/otf', 'mp4' => 'video/mp4',
        'pdf' => 'application/pdf',
    ][$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

// 修正被 PHP 内置服务器污染的 SCRIPT_NAME / PATH_INFO
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
unset($_SERVER['PATH_INFO']);

require __DIR__ . '/index.php';
