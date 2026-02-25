<?php
declare(strict_types=1);

use think\facade\Route;

// ================================================
// 管理后台路由（非多应用模式兼容）
// ⚠️ 多应用模式下优先使用 app/admin/route/app.php
// ================================================

Route::group('admin', function () {

    // ---- 公开接口（不需要管理员登录） ----
    Route::post('auth/login', 'admin/Auth/login');

    // ---- 需要管理员登录的接口 ----
    Route::group('', function () {

        // 管理员信息
        Route::get('auth/info', 'admin/Auth/info');

        // 仪表盘统计
        Route::get('dashboard', 'admin/Auth/dashboard');

        // 审核
        Route::get('audit/list', 'admin/PostAudit/list');
        Route::post('audit/approve', 'admin/PostAudit/approve');
        Route::post('audit/reject', 'admin/PostAudit/reject');
        Route::post('audit/takedown', 'admin/PostAudit/takedown');

        // 举报管理
        Route::get('report/list', 'admin/ReportManage/list');
        Route::post('report/handle', 'admin/ReportManage/handle');

        // 用户管理
        Route::get('user/list', 'admin/UserManage/list');
        Route::post('user/status', 'admin/UserManage/updateStatus');

        // 线索管理
        Route::get('clue/list', 'admin/ClueManage/list');
        Route::post('clue/status', 'admin/ClueManage/updateStatus');
        Route::post('clue/delete', 'admin/ClueManage/delete');

        // 通知管理
        Route::post('notify/send', 'admin/NotifyManage/send');

        // 管理员管理
        Route::get('manager/list', 'admin/AdminManage/list');
        Route::post('manager/create', 'admin/AdminManage/create');
        Route::post('manager/update', 'admin/AdminManage/update');
        Route::post('manager/delete', 'admin/AdminManage/delete');

        // 系统设置
        Route::get('settings/languages', 'admin/SystemSettings/languages');
        Route::post('settings/language/update', 'admin/SystemSettings/updateLanguage');
        Route::get('settings/regions', 'admin/SystemSettings/regions');

    })->middleware(\app\admin\middleware\AdminAuth::class);

})->middleware(\app\api\middleware\ApiResponse::class);
