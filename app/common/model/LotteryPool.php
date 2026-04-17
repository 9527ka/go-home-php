<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class LotteryPool extends Model
{
    protected $table = 'lottery_pools';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    public function prizes()
    {
        return $this->hasMany(LotteryPrize::class, 'pool_id')
            ->order('sort_order', 'asc');
    }
}
