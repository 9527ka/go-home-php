-- ============================================
-- 003: 增加 Apple 授权登录支持
-- ============================================

-- 增加 apple_id 字段（Apple 用户唯一标识符）
ALTER TABLE `users`
  ADD COLUMN `apple_id` VARCHAR(200) NULL COMMENT 'Apple 用户标识符' AFTER `account_type`,
  ADD UNIQUE KEY `uk_apple_id` (`apple_id`);

-- 增加 auth_provider 字段标识注册来源
ALTER TABLE `users`
  ADD COLUMN `auth_provider` TINYINT UNSIGNED NOT NULL DEFAULT 1
  COMMENT '注册来源 1=手机/邮箱 2=Apple' AFTER `apple_id`;

-- 允许 account 和 password 为空（Apple 登录用户可能无密码）
ALTER TABLE `users`
  MODIFY COLUMN `account` VARCHAR(100) NULL COMMENT '手机号/邮箱（第三方登录可为空）',
  MODIFY COLUMN `password` VARCHAR(255) NULL DEFAULT NULL COMMENT '密码哈希（第三方登录可为空）';
