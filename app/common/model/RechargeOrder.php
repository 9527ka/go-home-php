<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class RechargeOrder extends Model
{
    protected $table = 'recharge_orders';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $append = ['status_text'];

    const STATUS_PENDING  = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    const STATUS_NAMES = [
        self::STATUS_PENDING  => '待审核',
        self::STATUS_APPROVED => '已通过',
        self::STATUS_REJECTED => '已拒绝',
    ];

    /**
     * 模型事件：创建前自动生成订单号
     */
    public static function onBeforeInsert(self $model): void
    {
        if (empty($model->order_no)) {
            $model->order_no = 'RC' . date('YmdHis') . mt_rand(1000, 9999);
        }
    }

    public function getStatusTextAttr($value, $data): string
    {
        return self::STATUS_NAMES[$data['status'] ?? -1] ?? '未知';
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }
}
