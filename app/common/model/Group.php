<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Group extends Model
{
    protected $table = 'groups';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 关联群主
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id')
            ->field('id,nickname,avatar,user_code');
    }

    /**
     * 关联群成员
     */
    public function members()
    {
        return $this->hasMany(GroupMember::class, 'group_id');
    }

    /**
     * 是否活跃
     */
    public function isActive(): bool
    {
        return $this->status === 1;
    }

    /**
     * 是否已被管理员封禁
     */
    public function isBanned(): bool
    {
        return (int)$this->banned === 1;
    }

    /**
     * 是否开启全员禁言
     */
    public function isAllMuted(): bool
    {
        return (int)$this->all_muted === 1;
    }
}
