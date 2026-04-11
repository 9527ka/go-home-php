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
    Route::post('auth/quick-login', 'api/Auth/quickLogin');

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
        Route::post('auth/change-account', 'api/Auth/changeAccount');
        Route::post('auth/change-password', 'api/Auth/changePassword');
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
        Route::post('notification/deleteAll', 'api/Notification/deleteAll');

        // 好友
        Route::get('friend/search', 'api/Friend/search');
        Route::post('friend/request', 'api/Friend/request');
        Route::get('friend/requests', 'api/Friend/requests');
        Route::get('friend/request-count', 'api/Friend/requestCount');
        Route::post('friend/accept', 'api/Friend/accept');
        Route::post('friend/reject', 'api/Friend/reject');
        Route::get('friend/list', 'api/Friend/list');
        Route::post('friend/remove', 'api/Friend/remove');

        // 群组
        Route::post('group/create', 'api/Group/create');
        Route::get('group/list', 'api/Group/list');
        Route::get('group/detail', 'api/Group/detail');
        Route::post('group/update', 'api/Group/update');
        Route::post('group/invite', 'api/Group/invite');
        Route::post('group/leave', 'api/Group/leave');
        Route::post('group/kick', 'api/Group/kick');
        Route::post('group/disband', 'api/Group/disband');
        Route::get('group/messages', 'api/Group/messages');

        // 私聊 / 会话
        Route::get('pm/history', 'api/Pm/history');
        Route::get('pm/conversations', 'api/Pm/conversations');
        Route::post('pm/read', 'api/Pm/read');
        Route::post('pm/mute', 'api/Pm/mute');

        // 点赞
        Route::post('like/toggle', 'api/Like/toggle');
        Route::get('like/status', 'api/Like/status');
        Route::get('like/users', 'api/Like/users');

        // 评论
        Route::post('comment/create', 'api/Comment/create');
        Route::get('comment/list', 'api/Comment/list');
        Route::get('comment/replies', 'api/Comment/replies');
        Route::post('comment/delete', 'api/Comment/delete');

        // 关注
        Route::post('follow/toggle', 'api/Follow/toggle');
        Route::get('follow/status', 'api/Follow/status');
        Route::get('follow/followers', 'api/Follow/followers');
        Route::get('follow/following', 'api/Follow/following');
        Route::get('follow/recommend', 'api/Follow/recommend');

    })->middleware(\app\api\middleware\AuthCheck::class);

})->middleware([
    \app\api\middleware\ApiResponse::class,
    \app\api\middleware\RateLimit::class,
]);
