<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;

class User extends Model
{
    use SoftDelete;

    protected $table = 'users';
    protected $deleteTime = 'deleted_at';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 隐藏敏感字段
    protected $hidden = ['password', 'apple_id', 'deleted_at'];

    /**
     * 模型事件：创建前自动生成 user_code
     */
    public static function onBeforeInsert(self $user): void
    {
        if (empty($user->user_code)) {
            $user->user_code = self::generateUserCode();
        }
    }

    /**
     * 生成唯一用户编号：GH + 8位大写字母数字
     * 格式示例：GH5K8M2NXR
     */
    public static function generateUserCode(): string
    {
        // 去掉容易混淆的字符 0/O, 1/I/L
        $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = 'GH';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }

            // 检查唯一性
            if (!self::where('user_code', $code)->find()) {
                return $code;
            }
        }

        // 极端情况：用时间戳 + 随机数从同一字符集生成
        $fallback = 'GH';
        $seed = md5((string)microtime(true) . random_int(0, 99999));
        for ($i = 0; $i < 8; $i++) {
            $fallback .= $chars[ord($seed[$i]) % strlen($chars)];
        }
        return $fallback;
    }

    /**
     * 密码写入自动加密（Apple 登录用户可能无密码）
     */
    public function setPasswordAttr(?string $value): ?string
    {
        if ($value === null || $value === '') return null;
        return password_hash($value, PASSWORD_BCRYPT);
    }

    /**
     * 验证密码
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * 关联：用户钱包
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }

    /**
     * 关联：用户发布的启事
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    /**
     * 关联：用户的通知
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    /**
     * 是否正常状态
     */
    public function isNormal(): bool
    {
        return $this->status === 1;
    }

    /**
     * 是否被封禁
     */
    public function isBanned(): bool
    {
        return $this->status === 3;
    }
}
