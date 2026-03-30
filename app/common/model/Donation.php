<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Donation extends Model
{
    protected $table = 'donations';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    /**
     * 关联：捐赠者
     */
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id')
            ->field('id,nickname,avatar');
    }

    /**
     * 关联：接收者
     */
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id')
            ->field('id,nickname,avatar');
    }

    /**
     * 关联：启事
     */
    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id')
            ->field('id,name,category');
    }
}
