<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Wallet extends Model
{
    protected $table = 'wallets';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }

    /**
     * 余额是否充足
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return bccomp((string)$this->balance, (string)$amount, 2) >= 0;
    }

    /**
     * 是否正常状态
     */
    public function isNormal(): bool
    {
        return $this->status === 1;
    }
}
