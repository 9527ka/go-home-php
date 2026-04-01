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
Route::get('config/app', 'Config/app');
Route::get('post/list', 'Post/list');
Route::get('clue/list', 'Clue/list');
Route::get('chat/history', 'Chat/history');

// ---- 需要登录的接口 ----
Route::group('', function () {
    // 帖子详情（公开接口，但需走AuthCheck以识别登录用户身份，在白名单中不强制登录）
    Route::get('post/detail', 'Post/detail');
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

    // 好友
    Route::get('friend/search', 'Friend/search');
    Route::post('friend/request', 'Friend/request');
    Route::get('friend/requests', 'Friend/requests');
    Route::get('friend/request-count', 'Friend/requestCount');
    Route::post('friend/accept', 'Friend/accept');
    Route::post('friend/reject', 'Friend/reject');
    Route::get('friend/list', 'Friend/list');
    Route::post('friend/remove', 'Friend/remove');

    // 群组
    Route::post('group/create', 'Group/create');
    Route::get('group/list', 'Group/list');
    Route::get('group/detail', 'Group/detail');
    Route::post('group/update', 'Group/update');
    Route::post('group/invite', 'Group/invite');
    Route::post('group/leave', 'Group/leave');
    Route::post('group/kick', 'Group/kick');
    Route::post('group/disband', 'Group/disband');
    Route::get('group/messages', 'Group/messages');

    // 私信
    Route::get('pm/history', 'Pm/history');
    Route::get('pm/conversations', 'Pm/conversations');
    Route::post('pm/read', 'Pm/read');

    // 签到
    Route::post('sign', 'Sign/sign');
    Route::get('sign/status', 'Sign/status');

    // 任务
    Route::get('tasks', 'Task/list');
    Route::post('task/complete', 'Task/complete');

    // 钱包
    Route::get('wallet/info', 'Wallet/info');
    Route::get('wallet/transactions', 'Wallet/transactions');
    Route::post('wallet/recharge', 'Wallet/recharge');
    Route::get('wallet/recharge/list', 'Wallet/rechargeList');
    Route::post('wallet/withdraw', 'Wallet/withdraw');
    Route::get('wallet/withdraw/list', 'Wallet/withdrawList');
    Route::post('wallet/donate', 'Wallet/donate');
    Route::post('wallet/boost', 'Wallet/boost');
    Route::get('wallet/boost/active', 'Wallet/boostActive');

    // 红包
    Route::post('red-packet/send', 'RedPacket/send');
    Route::post('red-packet/claim', 'RedPacket/claim');
    Route::get('red-packet/detail', 'RedPacket/detail');

})->middleware(\app\api\middleware\AuthCheck::class);
