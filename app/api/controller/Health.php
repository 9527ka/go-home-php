<?php
declare(strict_types=1);

namespace app\api\controller;

use think\facade\Db;
use think\Response;

/**
 * 健康检查接口
 */
class Health extends BaseApi
{
    /**
     * 健康检查
     * GET /api/health
     *
     * 返回服务状态、数据库连接状态、基础统计
     */
    public function check(): Response
    {
        $status = 'ok';
        $checks = [];

        // 数据库连接检查
        try {
            Db::query('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error';
            $status = 'degraded';
        }

        // 上传目录可写检查
        $uploadPath = app()->getRootPath() . 'public/uploads';
        $checks['upload_dir'] = is_writable($uploadPath) ? 'ok' : 'error';

        // 基础统计（仅数据库正常时）
        $stats = null;
        if ($checks['database'] === 'ok') {
            try {
                $stats = [
                    'total_posts'  => (int)Db::table('posts')->where('status', 1)->count(),
                    'total_users'  => (int)Db::table('users')->whereNull('deleted_at')->count(),
                    'total_clues'  => (int)Db::table('clues')->where('status', 1)->count(),
                    'found_count'  => (int)Db::table('posts')->where('status', 2)->count(),
                ];
            } catch (\Exception $e) {
                $stats = null;
            }
        }

        return $this->success([
            'status'    => $status,
            'checks'    => $checks,
            'stats'     => $stats,
            'version'   => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
