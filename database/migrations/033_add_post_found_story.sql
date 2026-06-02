-- =============================================
-- 033: 找回故事反馈 — 已找回标记 + 故事提交/审核/奖励
-- =============================================

CREATE TABLE IF NOT EXISTS `post_found_stories` (
    `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `post_id`        BIGINT UNSIGNED  NOT NULL,
    `user_id`        BIGINT UNSIGNED  NOT NULL COMMENT '发布者',
    `content`        TEXT             NOT NULL COMMENT '找回经过',
    `images`         VARCHAR(1000)    NOT NULL DEFAULT '' COMMENT '图片路径逗号分隔',
    `found_at`       DATETIME         NULL COMMENT '找回时间（用户填）',
    `reward_amount`  DECIMAL(16,2)    NOT NULL DEFAULT 0.00 COMMENT '填写奖励金额(快照)',
    `is_rewarded`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=未发 1=已发',
    `status`         TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=待审 1=通过 2=驳回',
    `audit_remark`   VARCHAR(255)     NOT NULL DEFAULT '',
    `audited_by`     BIGINT UNSIGNED  NULL,
    `audited_at`     DATETIME         NULL,
    `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_post_id` (`post_id`),
    KEY `idx_status_created` (`status`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='找回故事';

-- 配置项
INSERT INTO `wallet_settings` (`setting_key`, `setting_value`) VALUES
('found_story_reward',         '10'),
('found_story_reward_enabled', '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
