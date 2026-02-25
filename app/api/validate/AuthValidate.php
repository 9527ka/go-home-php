<?php
declare(strict_types=1);

namespace app\api\validate;

use think\Validate;

class AuthValidate extends Validate
{
    protected $rule = [
        'account'      => 'require|min:3|max:100',
        'password'     => 'require|min:6|max:32',
        'account_type' => 'in:1,2',
        'nickname'     => 'max:50',
    ];

    protected $message = [
        'account.require'  => '请输入账号(手机号或邮箱)',
        'account.min'      => '账号长度不能少于3个字符',
        'account.max'      => '账号长度不能超过100个字符',
        'password.require' => '请输入密码',
        'password.min'     => '密码不能少于6个字符',
        'password.max'     => '密码不能超过32个字符',
    ];

    protected $scene = [
        'register' => ['account', 'password', 'account_type'],
        'login'    => ['account', 'password'],
    ];
}
