<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class PrivateMessage extends Model
{
    protected $table = 'private_messages';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    // JSON 字段
    protected $json = ['media_info'];
    protected $jsonAssoc = true;

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
