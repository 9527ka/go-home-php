<?php
/**
 * JWT 配置
 *
 * 重要：生产环境必须在 .env 中设置 JWT_SECRET
 */
return [
    // JWT 签名密钥（务必在 .env 中覆盖此默认值）
    'secret'  => env('JWT_SECRET', 'a7c4f2e8b1d6039f5e8a2c4d7b9f1e3a6c8d0b2e4f7a9c1d3e5f8b0a2c4d6e8'),
    // JWT 过期时间（秒）
    'expire'  => (int)env('JWT_EXPIRE', 86400 * 7),  // 默认 7 天
];
