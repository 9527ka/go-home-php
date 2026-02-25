<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Feedback extends Model
{
    protected $table = 'feedbacks';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    // 状态
    const STATUS_PENDING  = 0; // 待查看
    const STATUS_READ     = 1; // 已查看
    const STATUS_REPLIED  = 2; // 已回复

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,account');
    }
}
