<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class FriendRequest extends Model
{
    protected $table = 'friend_requests';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 关联发送者
     */
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_id')
            ->field('id,nickname,avatar,user_code');
    }

    /**
     * 关联接收者
     */
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_id')
            ->field('id,nickname,avatar,user_code');
    }
}
