<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class PostBoost extends Model
{
    protected $table = 'post_boosts';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    const STATUS_ACTIVE   = 1;
    const STATUS_EXPIRED  = 2;
    const STATUS_CANCELLED = 3;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * 作用域：生效中
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('expire_at', '>', date('Y-m-d H:i:s'));
    }
}
