<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Follow extends Model
{
    protected $table = 'follows';
    protected $autoWriteTimestamp = false;

    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id')
            ->field('id,nickname,avatar,user_code');
    }

    public function following()
    {
        return $this->belongsTo(User::class, 'following_id')
            ->field('id,nickname,avatar,user_code');
    }
}
