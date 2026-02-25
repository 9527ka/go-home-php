<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
use app\common\enum\PostCategory;
use app\common\enum\PostStatus;

class Post extends Model
{
    use SoftDelete;

    protected $table = 'posts';
    protected $deleteTime = 'deleted_at';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $hidden = ['deleted_at'];

    // JSON 序列化时追加的字段
    protected $append = ['category_text', 'status_text'];

    /**
     * 获取器：分类文本
     */
    public function getCategoryTextAttr($value, $data): string
    {
        return PostCategory::getName($data['category'] ?? 0);
    }

    /**
     * 获取器：状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        return PostStatus::getName($data['status'] ?? 0);
    }

    /**
     * 关联：发布者
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->field('id,nickname,avatar');
    }

    /**
     * 关联：图片
     */
    public function images()
    {
        return $this->hasMany(PostImage::class, 'post_id')
            ->order('sort_order', 'asc');
    }

    /**
     * 关联：线索
     */
    public function clues()
    {
        return $this->hasMany(Clue::class, 'post_id');
    }

    /**
     * 关联：翻译
     */
    public function translations()
    {
        return $this->hasMany(PostTranslation::class, 'post_id');
    }

    /**
     * 是否为未成人类别
     */
    public function isChildCategory(): bool
    {
        return PostCategory::isMinor($this->category);
    }

    /**
     * 是否已发布
     */
    public function isActive(): bool
    {
        return $this->status === PostStatus::ACTIVE;
    }

    /**
     * 作用域：已发布的
     */
    public function scopeActive($query)
    {
        return $query->where('status', PostStatus::ACTIVE);
    }

    /**
     * 作用域：待审核的
     */
    public function scopePending($query)
    {
        return $query->where('status', PostStatus::PENDING);
    }
}
