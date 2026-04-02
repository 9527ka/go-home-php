<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Comment extends Model
{
    protected $table = 'comments';
    protected $autoWriteTimestamp = false;

    // 状态
    const STATUS_DELETED = 0;
    const STATUS_NORMAL  = 1;
    const STATUS_HIDDEN  = 2;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar,user_code');
    }

    public function replyToUser()
    {
        return $this->belongsTo(User::class, 'reply_to_user_id')
            ->field('id,nickname,avatar,user_code');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->where('status', self::STATUS_NORMAL)
            ->order('created_at', 'asc');
    }
}
