<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class RedPacket extends Model
{
    protected $table = 'red_packets';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    const TARGET_PUBLIC  = 1; // 公共聊天室
    const TARGET_PRIVATE = 2; // 私聊
    const TARGET_GROUP   = 3; // 群聊

    const STATUS_ACTIVE  = 1; // 进行中
    const STATUS_CLAIMED = 2; // 已领完
    const STATUS_EXPIRED = 3; // 已过期退回

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }

    public function claims()
    {
        return $this->hasMany(RedPacketClaim::class, 'red_packet_id')
            ->order('created_at', 'asc');
    }

    /**
     * 是否可领取
     */
    public function isClaimable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->remaining_count > 0
            && strtotime($this->expire_at) > time();
    }
}
