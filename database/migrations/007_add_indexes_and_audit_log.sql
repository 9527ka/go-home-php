-- ============================================
-- 007: 添加性能索引 + 管理员审计日志表
-- ============================================

-- ========== 性能索引 ==========

-- posts 表：用户+状态联合索引（我的启事查询）
ALTER TABLE posts ADD INDEX idx_user_status (user_id, status);

-- posts 表：分类+状态+创建时间（列表筛选排序）
ALTER TABLE posts ADD INDEX idx_category_status_created (category, status, created_at DESC);

-- posts 表：城市+状态（城市筛选）
ALTER TABLE posts ADD INDEX idx_city_status (lost_city, status);

-- clues 表：帖子+状态（线索列表）
ALTER TABLE clues ADD INDEX idx_post_status (post_id, status);

-- notifications 表：用户+已读+创建时间（通知列表）
ALTER TABLE notifications ADD INDEX idx_user_read_created (user_id, is_read, created_at DESC);

-- chat_messages 表：创建时间（聊天历史加载）
ALTER TABLE chat_messages ADD INDEX idx_created (created_at DESC);

-- favorites 表：用户+帖子唯一约束（防重复收藏）
ALTER TABLE favorites ADD UNIQUE INDEX uk_user_post (user_id, post_id);

-- reports 表：用户+目标唯一约束（防重复举报）
ALTER TABLE reports ADD UNIQUE INDEX uk_user_target (user_id, target_type, target_id);


-- ========== 管理员审计日志表 ==========

CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED NOT NULL COMMENT '操作管理员ID',
    `action`      VARCHAR(50) NOT NULL COMMENT '操作类型: approve/reject/takedown/ban_user/delete_clue/send_notify',
    `target_type` VARCHAR(30) NOT NULL COMMENT '操作对象类型: post/user/clue/report',
    `target_id`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作对象ID',
    `detail`      TEXT NULL COMMENT '操作详情(JSON)',
    `ip`          VARCHAR(45) NULL COMMENT '操作IP',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_admin_created` (`admin_id`, `created_at` DESC),
    INDEX `idx_action_created` (`action`, `created_at` DESC),
    INDEX `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员操作审计日志';
