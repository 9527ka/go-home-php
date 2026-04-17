<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class LotteryLog extends Model
{
    protected $table = 'lottery_logs';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar,user_code');
    }
}
