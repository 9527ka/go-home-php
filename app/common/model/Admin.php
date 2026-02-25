<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Admin extends Model
{
    protected $table = 'admins';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $hidden = ['password'];

    const ROLE_AUDITOR = 1;     // 审核员
    const ROLE_SUPER_ADMIN = 2; // 超级管理员

    public function setPasswordAttr(string $value): string
    {
        return password_hash($value, PASSWORD_BCRYPT);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }
}
