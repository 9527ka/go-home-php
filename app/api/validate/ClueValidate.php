<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

class ClueValidate extends Validate
{
    protected $rule = [
        'post_id' => 'require|integer|gt:0',
        'content' => 'require|min:5|max:2000',
        'images'  => 'array|max:3',
        'contact' => 'max:50',
    ];

    protected $message = [
        'post_id.require' => '请指定启事',
        'content.require' => '请填写线索内容',
        'content.min'     => '线索内容至少5个字符',
        'content.max'     => '线索内容不能超过2000个字符',
        'images.max'      => '最多上传3张图片',
    ];
}
