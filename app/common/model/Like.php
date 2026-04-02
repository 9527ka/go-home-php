<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Like extends Model
{
    protected $table = 'likes';
    protected $autoWriteTimestamp = false;

    // 目标类型
    const TARGET_POST    = 1;
    const TARGET_COMMENT = 2;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar,user_code');
    }
}
