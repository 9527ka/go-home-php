<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class RedPacketClaim extends Model
{
    protected $table = 'red_packet_claims';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }

    public function redPacket()
    {
        return $this->belongsTo(RedPacket::class, 'red_packet_id');
    }
}
