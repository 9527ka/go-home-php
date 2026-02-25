<?php
declare(strict_types=1);

use think\facade\Route;

// ================================================
// API 路由 — 客户端使用（非多应用模式兼容）
// ⚠️ 多应用模式下请使用 app/api/route/app.php
// ================================================

Route::group('api', function () {

    // ---- 认证（不需要登录）----
    Route::post('auth/register', 'api/Auth/register');
    Route::post('auth/login', 'api/Auth/login');
    Route::post('auth/apple-signin', 'api/Auth/appleSignIn');

    // ---- 公开接口（不需要登录）----
    Route::get('post/list', 'api/Post/list');
    Route::get('post/detail', 'api/Post/detail');
    Route::get('clue/list', 'api/Clue/list');
    Route::get('chat/history', 'api/Chat/history');

    // ---- 需要登录的接口 ----
    Route::group('', function () {
        // 用户
        Route::get('auth/profile', 'api/Auth/profile');
        Route::post('auth/update', 'api/Auth/update');
        Route::post('auth/delete-account', 'api/Auth/deleteAccount');

        // 启事
        Route::post('post/create', 'api/Post/create');
        Route::get('post/mine', 'api/Post/mine');
        Route::post('post/updateStatus', 'api/Post/updateStatus');

        // 线索
        Route::post('clue/create', 'api/Clue/create');

        // 上传
        Route::post('upload/image', 'api/Upload/image');
        Route::post('upload/images', 'api/Upload/images');
        Route::post('upload/video', 'api/Upload/video');
        Route::post('upload/voice', 'api/Upload/voice');

        // 举报
        Route::post('report/create', 'api/Report/create');

        // 反馈
        Route::post('feedback/create', 'api/Feedback/create');

        // 收藏
        Route::post('favorite/toggle', 'api/Favorite/toggle');
        Route::get('favorite/list', 'api/Favorite/list');

        // 通知
        Route::get('notification/list', 'api/Notification/list');
        Route::get('notification/unread', 'api/Notification/unread');
        Route::post('notification/read', 'api/Notification/read');

    })->middleware(\app\api\middleware\AuthCheck::class);

})->middleware([
    \app\api\middleware\ApiResponse::class,
    \app\api\middleware\RateLimit::class,
]);
