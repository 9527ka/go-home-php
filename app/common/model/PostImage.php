<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class PostImage extends Model
{
    protected $table = 'post_images';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    /**
     * 获取器：图片完整URL
     */
    public function getImageUrlAttr($value): string
    {
        return self::formatUrl($value);
    }

    /**
     * 获取器：缩略图完整URL
     */
    public function getThumbUrlAttr($value): string
    {
        return self::formatUrl($value);
    }

    /**
     * 格式化URL，添加https前缀
     */
    protected static function formatUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }
        // 如果已经是完整URL，直接返回
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        // 添加https前缀
        return 'https://home.dengshop.com' . $url;
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
