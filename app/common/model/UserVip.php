<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class UserVip extends Model
{
    protected $table = 'user_vip';
    protected $pk    = 'user_id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /**
     * 是否在有效期内
     */
    public function isActive(): bool
    {
        if (empty($this->expired_at)) return false;
        return strtotime((string)$this->expired_at) > time();
    }
}
