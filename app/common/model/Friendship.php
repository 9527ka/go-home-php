<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Friendship extends Model
{
    protected $table = 'friendships';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    /**
     * 关联好友用户信息
     */
    public function friend()
    {
        return $this->belongsTo(User::class, 'friend_id')
            ->field('id,nickname,avatar,account,user_code');
    }
}
