<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

class PostValidate extends Validate
{
    protected $rule = [
        'category'      => 'require|in:1,2,4',
        'name'          => 'require|max:50',
        'appearance'    => 'require|min:10|max:2000',
        'description'   => 'max:5000',
        'lost_at'       => 'require|date',
        'lost_province' => 'max:50',
        'lost_city'     => 'require|max:100',
        'lost_district' => 'max:50',
        'lost_address'  => 'max:255',
        'images'        => 'array|max:9',
        'visibility'    => 'in:1,2',
        'reward_amount' => 'float|egt:0|elt:100000',
    ];

    protected $message = [
        'category.require'    => '请选择类别',
        'category.in'         => '类别无效',
        'name.require'        => '请填写标题',
        'name.max'            => '标题不能超过50个字符',
        'appearance.require'  => '请填写体貌特征',
        'appearance.min'      => '体貌特征至少10个字符',
        'lost_at.require'     => '请填写走失时间',
        'lost_at.date'        => '走失时间格式不正确',
        'lost_city.require'   => '请选择走失城市',
        'images.max'          => '最多上传9张图片',
        'visibility.in'       => '可见性设置无效',
    ];

    protected $scene = [
        'create' => [
            'category', 'name',
            'appearance', 'description', 'lost_at',
            'lost_province', 'lost_city', 'lost_district', 'lost_address',
            'images', 'visibility', 'reward_amount',
        ],
        'update' => [
            'name',
            'appearance', 'description',
            'lost_province', 'lost_city', 'lost_district', 'lost_address',
            'visibility',
        ],
    ];
}
