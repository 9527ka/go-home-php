<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

class PostValidate extends Validate
{
    protected $rule = [
        'category'      => 'require|in:1,2,3,4',
        'name'          => 'require|max:50',
        'gender'        => 'in:0,1,2',
        'age'           => 'max:20',
        'species'       => 'max:50',
        'appearance'    => 'require|min:10|max:2000',
        'description'   => 'max:5000',
        'lost_at'       => 'require|date',
        'lost_province' => 'max:50',
        'lost_city'     => 'require|max:50',
        'lost_district' => 'max:50',
        'lost_address'  => 'max:255',
        'contact_name'  => 'max:50',
        'contact_phone' => 'require|max:20',
        'images'        => 'array|max:9',
    ];

    protected $message = [
        'category.require'    => '请选择类别',
        'category.in'         => '类别无效',
        'name.require'        => '请填写名字/称呼',
        'name.max'            => '名字不能超过50个字符',
        'appearance.require'  => '请填写体貌特征',
        'appearance.min'      => '体貌特征至少10个字符',
        'lost_at.require'     => '请填写走失时间',
        'lost_at.date'        => '走失时间格式不正确',
        'lost_city.require'   => '请选择走失城市',
        'contact_phone.require' => '请填写联系电话',
        'images.max'          => '最多上传9张图片',
    ];

    protected $scene = [
        'create' => [
            'category', 'name', 'gender', 'age', 'species',
            'appearance', 'description', 'lost_at',
            'lost_province', 'lost_city', 'lost_district', 'lost_address',
            'contact_name', 'contact_phone', 'images',
        ],
        'update' => [
            'name', 'gender', 'age', 'species',
            'appearance', 'description',
            'lost_province', 'lost_city', 'lost_district', 'lost_address',
            'contact_name', 'contact_phone',
        ],
    ];
}
