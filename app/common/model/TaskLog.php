<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class TaskLog extends Model
{
    protected $table = 'task_logs';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }
}
