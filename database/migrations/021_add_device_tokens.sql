-- ============================================
-- 021: 增加设备推送令牌表 (APNs)
-- ============================================

CREATE TABLE IF NOT EXISTS `device_tokens` (
    `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED  NOT NULL COMMENT '用户 ID',
    `device_token`  VARCHAR(255)     NOT NULL COMMENT 'APNs device token (hex)',
    `platform`      VARCHAR(10)      NOT NULL DEFAULT 'ios' COMMENT 'ios/android',
    `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_device_token` (`device_token`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='APNs 推送令牌';
