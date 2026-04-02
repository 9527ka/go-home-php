-- ============================================
-- 社交互动系统：点赞 + 评论 + 关注
-- ============================================

SET NAMES utf8mb4;

-- ----------------------------
-- 1. 点赞表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `likes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL COMMENT '点赞用户',
  `target_type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=帖子 2=评论',
  `target_id`   BIGINT UNSIGNED NOT NULL COMMENT '目标ID',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_target` (`user_id`, `target_type`, `target_id`),
  KEY `idx_target` (`target_type`, `target_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='点赞表';

-- ----------------------------
-- 2. 评论表（支持楼中楼回复）
-- ----------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`          BIGINT UNSIGNED NOT NULL COMMENT '帖子ID',
  `user_id`          BIGINT UNSIGNED NOT NULL COMMENT '评论者',
  `parent_id`        BIGINT UNSIGNED DEFAULT NULL COMMENT '父评论ID（楼中楼）',
  `reply_to_user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '回复对象用户ID',
  `content`          VARCHAR(500) NOT NULL COMMENT '评论内容',
  `like_count`       INT UNSIGNED NOT NULL DEFAULT 0,
  `reply_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `status`           TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=正常 0=已删除 2=被举报隐藏',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_status_created` (`post_id`, `status`, `created_at` DESC),
  KEY `idx_post_status_likes` (`post_id`, `status`, `like_count` DESC),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评论表';

-- ----------------------------
-- 3. 关注表（单向关系）
-- ----------------------------
CREATE TABLE IF NOT EXISTS `follows` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `follower_id`  BIGINT UNSIGNED NOT NULL COMMENT '关注者',
  `following_id` BIGINT UNSIGNED NOT NULL COMMENT '被关注者',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_follow` (`follower_id`, `following_id`),
  KEY `idx_following` (`following_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='关注表';

-- ----------------------------
-- 4. posts 表新增字段
-- ----------------------------
ALTER TABLE `posts` ADD COLUMN `like_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点赞数' AFTER `share_count`;
ALTER TABLE `posts` ADD COLUMN `comment_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '评论数' AFTER `like_count`;

-- ----------------------------
-- 5. users 表新增字段
-- ----------------------------
ALTER TABLE `users` ADD COLUMN `follower_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '粉丝数' AFTER `status`;
ALTER TABLE `users` ADD COLUMN `following_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关注数' AFTER `follower_count`;
