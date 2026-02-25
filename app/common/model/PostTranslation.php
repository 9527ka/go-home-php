<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class PostTranslation extends Model
{
    protected $table = 'post_translations';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
