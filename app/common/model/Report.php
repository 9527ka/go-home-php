<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Report extends Model
{
    protected $table = 'reports';
    protected $autoWriteTimestamp = false;

    // 举报目标类型
    const TARGET_POST = 1;
    const TARGET_CLUE = 2;
    const TARGET_USER = 3;

    // 举报原因
    const REASON_FAKE    = 1; // 虚假信息
    const REASON_AD      = 2; // 广告
    const REASON_ILLEGAL = 3; // 涉及违法
    const REASON_HARASS  = 4; // 骚扰
    const REASON_OTHER   = 5; // 其他

    // 处理状态
    const STATUS_PENDING  = 0; // 待处理
    const STATUS_VALID    = 1; // 已处理-有效
    const STATUS_INVALID  = 2; // 已处理-无效
    const STATUS_IGNORED  = 3; // 忽略

    public function reporter()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }
}
