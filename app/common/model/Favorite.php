<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class Favorite extends Model
{
    protected $table = 'favorites';
    protected $autoWriteTimestamp = false;

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
