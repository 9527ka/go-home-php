<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class LotteryPrize extends Model
{
    protected $table = 'lottery_prizes';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
