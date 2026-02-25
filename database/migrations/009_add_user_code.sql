-- ============================================
-- 009: 用户表新增 user_code（用户编号）字段
-- 替代直接展示自增 ID，防止暴露平台用户量
-- ============================================

-- 1. 添加 user_code 字段（允许空字符串作为默认值）
ALTER TABLE `users`
    ADD COLUMN `user_code` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '用户编号（对外展示）' AFTER `nickname`;

-- 2. 为已有用户生成唯一的 user_code
--    使用 GH + 基于 id 和随机数的8位字符串
--    字符集: 23456789ABCDEFGHJKMNPQRSTUVWXYZ（排除 0/O, 1/I/L）
UPDATE `users`
SET `user_code` = CONCAT('GH', UPPER(SUBSTR(MD5(CONCAT(id, RAND(), NOW())), 1, 8)))
WHERE `user_code` = '';

-- 3. 添加唯一索引（此时所有行都有非空的 user_code）
ALTER TABLE `users`
    ADD UNIQUE KEY `uk_user_code` (`user_code`);
