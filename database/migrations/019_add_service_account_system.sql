-- 官方客服系统：用户类型字段 + 每客服服务用户上限配置

-- 1. users 表新增 user_type 字段
ALTER TABLE `users` ADD COLUMN `user_type` TINYINT NOT NULL DEFAULT 0
  COMMENT '0=普通用户 1=官方客服' AFTER `status`;

-- 2. 标记已有客服账号
UPDATE `users` SET `user_type` = 1 WHERE `account` = '18800000008';

-- 3. 添加客服分配配置
INSERT INTO `wallet_settings` (`setting_key`, `setting_value`, `updated_at`)
VALUES ('service_users_per_account', '1000', NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
