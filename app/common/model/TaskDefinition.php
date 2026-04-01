<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

class TaskDefinition extends Model
{
    protected $table = 'task_definitions';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
