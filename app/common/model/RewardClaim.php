<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class RewardClaim extends Model
{
    protected $table = 'reward_claims';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    /**
     * 关联：发布者(支付方)
     */
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id')
            ->field('id,nickname,avatar');
    }

    /**
     * 关联：线索提供者(收款方)
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

    /**
     * 关联：线索
     */
    public function clue()
    {
        return $this->belongsTo(Clue::class, 'clue_id');
    }
}
