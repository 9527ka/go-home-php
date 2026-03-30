<?php
declare(strict_types=1);

namespace app\common\model;

use app\common\enum\WalletTransactionType;
use think\Model;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $append = ['type_text'];

    /**
     * 获取器：类型文本
     */
    public function getTypeTextAttr($value, $data): string
    {
        return WalletTransactionType::getName($data['type'] ?? 0);
    }

    /**
     * 关联：用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }
}
