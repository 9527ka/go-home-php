<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;

class Clue extends Model
{
    use SoftDelete;

    protected $table = 'clues';
    protected $deleteTime = 'deleted_at';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $hidden = ['deleted_at'];

    /**
     * 获取器：图片数组（自动补全URL）
     */
    public function getImagesAttr($value): array
    {
        if (empty($value)) return [];
        $cdnUrl = env('APP_CDN_URL', 'https://home.dengshop.com');
        return array_map(function ($url) use ($cdnUrl) {
            $url = trim($url);
            if (empty($url)) return '';
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }
            return $cdnUrl . $url;
        }, explode(',', $value));
    }

    /**
     * 修改器：图片数组转字符串
     */
    public function setImagesAttr($value): string
    {
        if (is_array($value)) {
            return implode(',', $value);
        }
        return (string)$value;
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }
}
