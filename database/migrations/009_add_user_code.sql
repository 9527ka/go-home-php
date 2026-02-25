-- ============================================
-- 009: 用户表新增 user_code（用户编号）字段
-- 替代直接展示自增 ID，防止暴露平台用户量
-- ============================================

-- 添加 user_code 字段
ALTER TABLE `users`
    ADD COLUMN `user_code` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '用户编号（对外展示）' AFTER `nickname`;

-- 为已有用户生成 user_code（GH + 6位随机字母数字）
-- 注意：需要在应用层执行一次性脚本来为存量用户补充，这里只做表结构变更

-- 添加唯一索引
ALTER TABLE `users`
    ADD UNIQUE KEY `uk_user_code` (`user_code`);
