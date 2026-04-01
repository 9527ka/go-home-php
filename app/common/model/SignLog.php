<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class SignLog extends Model
{
    protected $table = 'sign_logs';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }
}
