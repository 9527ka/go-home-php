-- ============================================
-- 020: 增加用户平台标识
-- 记录用户登录的客户端平台 (ios/android)
-- ============================================

ALTER TABLE `users`
  ADD COLUMN `platform` VARCHAR(20) NULL DEFAULT NULL
  COMMENT '客户端平台 ios/android' AFTER `auth_provider`;
