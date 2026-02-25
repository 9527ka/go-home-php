<?php
// +----------------------------------------------------------------------
// | API 应用路由定义
// | 多应用模式下，此文件对应 /api/* 路径
// +----------------------------------------------------------------------
declare(strict_types=1);

use think\facade\Route;

// ---- 认证（不需要登录）----
Route::post('auth/register', 'Auth/register');
Route::post('auth/login', 'Auth/login');
Route::post('auth/apple-signin', 'Auth/appleSignIn');
Route::post('auth/quick-login', 'Auth/quickLogin');

// ---- 公开接口（不需要登录）----
Route::get('health', 'Health/check');
Route::get('post/list', 'Post/list');
Route::get('post/detail', 'Post/detail');
Route::get('clue/list', 'Clue/list');
Route::get('chat/history', 'Chat/history');

// ---- 需要登录的接口 ----
Route::group('', function () {
    // 用户
    Route::get('auth/profile', 'Auth/profile');
    Route::post('auth/update', 'Auth/update');
    Route::post('auth/change-account', 'Auth/changeAccount');
    Route::post('auth/change-password', 'Auth/changePassword');
    Route::post('auth/delete-account', 'Auth/deleteAccount');

    // 启事
    Route::post('post/create', 'Post/create');
    Route::post('post/update', 'Post/update');
    Route::get('post/mine', 'Post/mine');
    Route::post('post/updateStatus', 'Post/updateStatus');

    // 线索
    Route::post('clue/create', 'Clue/create');

    // 上传
    Route::post('upload/image', 'Upload/image');
    Route::post('upload/images', 'Upload/images');

    // 举报
    Route::post('report/create', 'Report/create');

    // 反馈
    Route::post('feedback/create', 'Feedback/create');

    // 收藏
    Route::post('favorite/toggle', 'Favorite/toggle');
    Route::get('favorite/list', 'Favorite/list');

    // 通知
    Route::get('notification/list', 'Notification/list');
    Route::get('notification/unread', 'Notification/unread');
    Route::post('notification/read', 'Notification/read');

})->middleware(\app\api\middleware\AuthCheck::class);
