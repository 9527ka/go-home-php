-- ============================================
-- 迁移 004: 新增反馈表 + 聊天消息表
-- ============================================

SET NAMES utf8mb4;

-- ----------------------------
-- 用户反馈表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `feedbacks` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED  NOT NULL COMMENT '用户ID',
    `content`     TEXT             NOT NULL COMMENT '反馈内容',
    `contact`     VARCHAR(100)     NOT NULL DEFAULT '' COMMENT '联系方式(可选)',
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=待查看 1=已查看 2=已回复',
    `admin_reply` TEXT             DEFAULT NULL COMMENT '管理员回复(预留)',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户反馈';

-- ----------------------------
-- 聊天室消息表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED  NOT NULL COMMENT '发送者ID',
    `content`     VARCHAR(500)     NOT NULL COMMENT '消息内容',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='聊天室消息';
