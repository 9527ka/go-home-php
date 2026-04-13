<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class GroupMessage extends Model
{
    protected $table = 'group_messages';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    // JSON 字段
    protected $json = ['media_info', 'mentions'];
    protected $jsonAssoc = true;

    /**
     * 关联发送者
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar,user_code');
    }

    /**
     * 关联群组
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
