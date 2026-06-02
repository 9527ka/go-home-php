-- 025: 聊天管控 — 群封禁、全员禁言、成员禁言到期
-- 配合 管理后台聊天监控与管控 + 群内单人禁言 两项功能

-- 1. groups 增加 banned（整个群是否被管理员封禁）、all_muted（全员禁言开关）
ALTER TABLE `groups`
  ADD COLUMN `banned`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=正常 1=已封禁（禁止发消息 & 加入）' AFTER `status`,
  ADD COLUMN `all_muted` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=正常 1=全员禁言（仅管理员可发言）' AFTER `banned`;

-- 2. group_members 增加 muted_until（NULL=未禁言；具体时间=禁言到期时间）
ALTER TABLE `group_members`
  ADD COLUMN `muted_until` DATETIME NULL DEFAULT NULL COMMENT '群内禁言到期时间，NULL=未禁言' AFTER `alias`;

-- 3. 群消息支持 @提及（mentions = [userId, ...]）
ALTER TABLE `group_messages`
  ADD COLUMN `mentions` JSON NULL DEFAULT NULL COMMENT '@提及的用户 ID 数组（NULL 或 空数组=未@）' AFTER `media_info`;

-- 5. 群邀请 token（二维码 / 邀请链接）
CREATE TABLE IF NOT EXISTS `group_invites` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `group_id`    BIGINT UNSIGNED  NOT NULL,
    `token`       CHAR(32)         NOT NULL COMMENT '邀请 token（URL-safe）',
    `created_by`  BIGINT UNSIGNED  NOT NULL COMMENT '发起邀请的用户',
    `expires_at`  DATETIME         NOT NULL COMMENT '过期时间（默认7天）',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token` (`token`),
    KEY `idx_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群邀请 token';
