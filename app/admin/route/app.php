<?php
// +----------------------------------------------------------------------
// | Admin 应用路由定义
// | 多应用模式下，此文件对应 /admin/* 路径
// +----------------------------------------------------------------------
declare(strict_types=1);

use think\facade\Route;

// ---- 公开接口（不需要管理员登录） ----
Route::post('auth/login', 'Auth/login');

// ---- 需要管理员登录的接口 ----
Route::group('', function () {

    // 管理员信息
    Route::get('auth/info', 'Auth/info');

    // 仪表盘统计
    Route::get('dashboard', 'Auth/dashboard');

    // 审核
    Route::get('audit/list', 'PostAudit/list');
    Route::post('audit/approve', 'PostAudit/approve');
    Route::post('audit/reject', 'PostAudit/reject');
    Route::post('audit/takedown', 'PostAudit/takedown');

    // 举报管理
    Route::get('report/list', 'ReportManage/list');
    Route::post('report/handle', 'ReportManage/handle');

    // 用户管理
    Route::get('user/list', 'UserManage/list');
    Route::post('user/status', 'UserManage/updateStatus');
    Route::post('user/update', 'UserManage/update');
    Route::post('user/type', 'UserManage/updateUserType');

    // 线索管理
    Route::get('clue/list', 'ClueManage/list');
    Route::post('clue/status', 'ClueManage/updateStatus');
    Route::post('clue/delete', 'ClueManage/delete');

    // 通知管理
    Route::post('notify/send', 'NotifyManage/send');

    // 管理员管理
    Route::get('manager/list', 'AdminManage/list');
    Route::post('manager/create', 'AdminManage/create');
    Route::post('manager/update', 'AdminManage/update');
    Route::post('manager/delete', 'AdminManage/delete');

    // 系统设置
    Route::get('settings/languages', 'SystemSettings/languages');
    Route::post('settings/language/update', 'SystemSettings/updateLanguage');
    Route::get('settings/regions', 'SystemSettings/regions');

    // 钱包管理
    Route::get('wallet/recharge/list', 'WalletManage/rechargeList');
    Route::post('wallet/recharge/approve', 'WalletManage/rechargeApprove');
    Route::post('wallet/recharge/reject', 'WalletManage/rechargeReject');
    Route::get('wallet/withdrawal/list', 'WalletManage/withdrawalList');
    Route::post('wallet/withdrawal/approve', 'WalletManage/withdrawalApprove');
    Route::post('wallet/withdrawal/reject', 'WalletManage/withdrawalReject');
    Route::get('wallet/transactions', 'WalletManage/transactions');
    Route::get('wallet/red-packet/list', 'WalletManage/redPacketList');
    Route::get('wallet/red-packet/claims', 'WalletManage/redPacketClaims');
    Route::get('wallet/settings', 'WalletManage/settings');
    Route::post('wallet/settings/update', 'WalletManage/settingsUpdate');

    // 聊天监控与管控
    Route::get('chat/private', 'ChatManage/privateList');
    Route::get('chat/group', 'ChatManage/groupList');
    Route::get('chat/groups', 'ChatManage/groups');
    Route::post('chat/group/ban', 'ChatManage/toggleGroupBan');
    Route::post('chat/group/mute', 'ChatManage/toggleGroupAllMute');
    Route::post('chat/member/mute', 'ChatManage/muteMember');

    // 签到/任务管理
    Route::get('sign/stats', 'SignManage/stats');
    Route::get('sign/logs', 'SignManage/signLogs');
    Route::get('sign/task-logs', 'SignManage/taskLogs');
    Route::get('sign/tasks', 'SignManage/taskDefinitions');
    Route::post('sign/task/update', 'SignManage/updateTask');
    Route::post('sign/toggle', 'SignManage/toggle');

    // VIP 管理
    Route::get('vip/levels', 'VipManage/levels');
    Route::post('vip/level/update', 'VipManage/updateLevel');
    Route::get('vip/orders', 'VipManage/orders');
    Route::get('vip/users', 'VipManage/users');
    Route::post('vip/user/grant', 'VipManage/grantUser');

    // 抽奖管理
    Route::get('lottery/pools', 'LotteryManage/pools');
    Route::post('lottery/pool/update', 'LotteryManage/updatePool');
    Route::get('lottery/prizes', 'LotteryManage/prizes');
    Route::post('lottery/prize/create', 'LotteryManage/createPrize');
    Route::post('lottery/prize/update', 'LotteryManage/updatePrize');
    Route::post('lottery/prize/delete', 'LotteryManage/deletePrize');
    Route::get('lottery/logs', 'LotteryManage/logs');
    Route::get('lottery/stats', 'LotteryManage/stats');

    // 找回故事审核
    Route::get('found-story/list', 'FoundStoryManage/list');
    Route::post('found-story/approve', 'FoundStoryManage/approve');
    Route::post('found-story/reject', 'FoundStoryManage/reject');

})->middleware(\app\admin\middleware\AdminAuth::class);
