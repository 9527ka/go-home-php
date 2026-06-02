-- ============================================
-- 023: 增加会话免打扰表
-- ============================================

CREATE TABLE IF NOT EXISTS `conversation_mutes` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED  NOT NULL COMMENT '用户 ID',
    `target_id`   BIGINT UNSIGNED  NOT NULL COMMENT '对方用户 ID 或群组 ID',
    `target_type` VARCHAR(10)      NOT NULL DEFAULT 'private' COMMENT 'private / group',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_target` (`user_id`, `target_id`, `target_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会话免打扰';
