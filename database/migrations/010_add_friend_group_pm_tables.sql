-- ============================================
-- 好友 / 群组 / 私聊 相关表
-- MySQL 8.0+
-- ============================================

SET NAMES utf8mb4;

-- ----------------------------
-- 1. 好友请求表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `friend_requests` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `from_id`     BIGINT UNSIGNED  NOT NULL COMMENT '发送者',
    `to_id`       BIGINT UNSIGNED  NOT NULL COMMENT '接收者',
    `message`     VARCHAR(200)     NOT NULL DEFAULT '' COMMENT '验证消息',
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=待处理 1=已接受 2=已拒绝',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_to_status` (`to_id`, `status`, `created_at` DESC),
    KEY `idx_from_to` (`from_id`, `to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友请求表';

-- ----------------------------
-- 2. 好友关系表（双向存储，每对好友两条记录）
-- ----------------------------
CREATE TABLE IF NOT EXISTS `friendships` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED  NOT NULL COMMENT '用户A',
    `friend_id`   BIGINT UNSIGNED  NOT NULL COMMENT '用户B（好友）',
    `remark`      VARCHAR(50)      NOT NULL DEFAULT '' COMMENT '好友备注',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_friend` (`user_id`, `friend_id`),
    KEY `idx_friend_id` (`friend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友关系表';

-- ----------------------------
-- 3. 群组表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `groups` (
    `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(50)      NOT NULL COMMENT '群名',
    `avatar`        VARCHAR(255)     NOT NULL DEFAULT '' COMMENT '群头像',
    `description`   VARCHAR(500)     NOT NULL DEFAULT '' COMMENT '群简介',
    `owner_id`      BIGINT UNSIGNED  NOT NULL COMMENT '群主',
    `max_members`   INT UNSIGNED     NOT NULL DEFAULT 100 COMMENT '最大人数',
    `member_count`  INT UNSIGNED     NOT NULL DEFAULT 1 COMMENT '当前人数',
    `status`        TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=活跃 2=已解散',
    `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_owner_id` (`owner_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群组表';

-- ----------------------------
-- 4. 群成员表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `group_members` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `group_id`    BIGINT UNSIGNED  NOT NULL,
    `user_id`     BIGINT UNSIGNED  NOT NULL,
    `role`        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=普通成员 1=管理员 2=群主',
    `joined_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_user` (`group_id`, `user_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群成员表';

-- ----------------------------
-- 5. 私聊消息表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `private_messages` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `from_id`     BIGINT UNSIGNED  NOT NULL COMMENT '发送者',
    `to_id`       BIGINT UNSIGNED  NOT NULL COMMENT '接收者',
    `msg_type`    VARCHAR(10)      NOT NULL DEFAULT 'text' COMMENT 'text/image/video/voice',
    `content`     TEXT             NOT NULL COMMENT '消息内容',
    `media_url`   VARCHAR(500)     NOT NULL DEFAULT '',
    `thumb_url`   VARCHAR(500)     NOT NULL DEFAULT '',
    `media_info`  JSON             NULL COMMENT '媒体附加信息',
    `is_read`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=未读 1=已读',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_conversation` (`from_id`, `to_id`, `created_at` DESC),
    KEY `idx_to_unread` (`to_id`, `is_read`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='私聊消息表';

-- ----------------------------
-- 6. 群聊消息表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `group_messages` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `group_id`    BIGINT UNSIGNED  NOT NULL,
    `user_id`     BIGINT UNSIGNED  NOT NULL COMMENT '发送者',
    `msg_type`    VARCHAR(10)      NOT NULL DEFAULT 'text' COMMENT 'text/image/video/voice',
    `content`     TEXT             NOT NULL,
    `media_url`   VARCHAR(500)     NOT NULL DEFAULT '',
    `thumb_url`   VARCHAR(500)     NOT NULL DEFAULT '',
    `media_info`  JSON             NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_group_created` (`group_id`, `created_at` DESC),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群聊消息表';
