<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class GroupMember extends Model
{
    protected $table = 'group_members';
    protected $autoWriteTimestamp = false;

    /**
     * 关联用户
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

    /**
     * 是否群主
     */
    public function isOwner(): bool
    {
        return $this->role === 2;
    }

    /**
     * 是否管理员（含群主）
     */
    public function isAdmin(): bool
    {
        return $this->role >= 1;
    }
}
