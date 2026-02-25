<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 管理员审计日志
 */
class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_logs';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    // 操作类型常量
    const ACTION_APPROVE     = 'approve';
    const ACTION_REJECT      = 'reject';
    const ACTION_TAKEDOWN    = 'takedown';
    const ACTION_BAN_USER    = 'ban_user';
    const ACTION_DELETE_CLUE  = 'delete_clue';
    const ACTION_SEND_NOTIFY = 'send_notify';

    /**
     * 关联管理员
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id')
            ->field('id,username');
    }

    /**
     * 记录审计日志
     */
    public static function log(int $adminId, string $action, string $targetType, int $targetId, ?string $detail = null, ?string $ip = null): void
    {
        $log = new static();
        $log->admin_id    = $adminId;
        $log->action      = $action;
        $log->target_type = $targetType;
        $log->target_id   = $targetId;
        $log->detail       = $detail;
        $log->ip          = $ip;
        $log->save();
    }
}
