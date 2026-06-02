-- =====================================================================
-- 026_release_unique_keys_on_delete.sql
--
-- 注销账号时释放唯一键占用（uk_apple_id / uk_account）
--
-- 背景：users 表有 uk_apple_id 和 uk_account 两个唯一键。
-- 现有软删除仅设置 status=2 + deleted_at，原字段值依然占着唯一键，
-- 导致同一 Apple ID / 手机号 / 邮箱注销后无法重新注册，
-- INSERT 时报 SQLSTATE[23000] 1062 Duplicate entry ... for key 'uk_apple_id'
--
-- 解决：注销时将 apple_id / account 置 NULL 释放唯一键，
-- 原值保留到 deleted_apple_id / deleted_account 便于审计追溯。
-- =====================================================================

ALTER TABLE `users`
  ADD COLUMN `deleted_apple_id` VARCHAR(200) NULL DEFAULT NULL
    COMMENT '注销账号时保留的 Apple ID 原值（审计追溯用）' AFTER `apple_id`,
  ADD COLUMN `deleted_account` VARCHAR(191) NULL DEFAULT NULL
    COMMENT '注销账号时保留的手机号/邮箱原值（审计追溯用）' AFTER `account`;
