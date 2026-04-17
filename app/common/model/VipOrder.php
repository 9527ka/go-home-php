<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class VipOrder extends Model
{
    protected $table = 'vip_orders';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    const STATUS_SUCCESS = 1;
    const STATUS_FAILED  = 0;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar,user_code');
    }
}
