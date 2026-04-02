<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $autoWriteTimestamp = false;

    // 通知类型
    const TYPE_CLUE_REPLY       = 1; // 线索回复
    const TYPE_AUDIT_PASS       = 2; // 审核通过
    const TYPE_AUDIT_REJECT     = 3; // 审核驳回
    const TYPE_REPORT_DONE      = 4; // 举报处理完成
    const TYPE_SYSTEM           = 5; // 系统通知
    const TYPE_REPORT_VIOLATION = 6; // 举报违规（通知帖子作者）

    // 互动通知类型
    const TYPE_LIKE_POST    = 10; // 有人赞了你的帖子
    const TYPE_LIKE_COMMENT = 11; // 有人赞了你的评论
    const TYPE_COMMENT      = 12; // 有人评论了你的帖子
    const TYPE_REPLY        = 13; // 有人回复了你的评论
    const TYPE_FOLLOW       = 14; // 有人关注了你

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
