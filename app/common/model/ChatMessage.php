<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    // JSON 字段自动序列化
    protected $json = ['media_info'];
    protected $jsonAssoc = true;

    /**
     * 获取器：媒体文件完整URL
     */
    public function getMediaUrlAttr($value): string
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }
}
