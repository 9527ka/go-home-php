-- =============================================
-- 013: 签到 + 任务激励系统
-- =============================================

-- 1. wallets 表新增奖励冻结余额字段
ALTER TABLE `wallets`
  ADD COLUMN `reward_frozen_balance` DECIMAL(16,2) NOT NULL DEFAULT 0.00
    COMMENT '签到/任务奖励冻结余额' AFTER `frozen_balance`,
  ADD COLUMN `total_reward_earned` DECIMAL(16,2) NOT NULL DEFAULT 0.00
    COMMENT '累计签到+任务奖励' AFTER `total_received`;

-- 2. 用户签到状态（一用户一行）
CREATE TABLE IF NOT EXISTS `user_sign_status` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         BIGINT UNSIGNED NOT NULL,
    `current_streak`  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前连续天数(1-7)',
    `last_sign_date`  DATE DEFAULT NULL COMMENT '最后签到日期',
    `total_sign_days` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计签到天数',
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户签到状态';

-- 3. 签到日志
CREATE TABLE IF NOT EXISTS `sign_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `sign_date`     DATE NOT NULL COMMENT '签到日期',
    `day_in_cycle`  TINYINT UNSIGNED NOT NULL COMMENT '周期中第几天(1-7)',
    `base_reward`   DECIMAL(16,2) NOT NULL COMMENT '基础奖励',
    `bonus_rate`    TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '暴击倍率(1/2/5)',
    `final_reward`  DECIMAL(16,2) NOT NULL COMMENT '最终奖励(base*rate)',
    `ip`            VARCHAR(45) DEFAULT NULL,
    `device_id`     VARCHAR(100) DEFAULT NULL COMMENT '设备ID(预留)',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_date` (`user_id`, `sign_date`),
    KEY `idx_date` (`sign_date`),
    KEY `idx_ip_date` (`ip`, `sign_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='签到日志';

-- 4. 任务定义（管理员可配置）
CREATE TABLE IF NOT EXISTS `task_definitions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_key`      VARCHAR(30) NOT NULL COMMENT '任务标识',
    `name`          VARCHAR(50) NOT NULL COMMENT '任务名称',
    `description`   VARCHAR(200) NOT NULL DEFAULT '',
    `reward`        DECIMAL(16,2) NOT NULL COMMENT '完成奖励(USDT)',
    `target_count`  INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '需要完成次数',
    `sort_order`    INT UNSIGNED NOT NULL DEFAULT 0,
    `is_enabled`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_task_key` (`task_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务定义';

-- 初始任务数据
INSERT INTO `task_definitions` (`task_key`, `name`, `description`, `reward`, `target_count`, `sort_order`) VALUES
('login',            '每日登录',     '登录APP即可完成',           0.10, 1, 1),
('chat_3',           '聊天3次',      '发送3条聊天消息',           0.20, 3, 2),
('complete_profile', '完善资料',     '完善个人资料信息',          0.50, 1, 3),
('purchase',         '完成一笔消费', '完成任意消费操作',          1.00, 1, 4),
('invite',           '邀请用户',     '成功邀请一位新用户注册',    2.00, 1, 5);

-- 5. 每日任务完成记录
CREATE TABLE IF NOT EXISTS `task_logs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         BIGINT UNSIGNED NOT NULL,
    `task_key`        VARCHAR(30) NOT NULL,
    `log_date`        DATE NOT NULL,
    `progress`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前进度',
    `is_completed`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `is_rewarded`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `reward_amount`   DECIMAL(16,2) NOT NULL DEFAULT 0.00,
    `completed_at`    DATETIME DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_task_date` (`user_id`, `task_key`, `log_date`),
    KEY `idx_date` (`log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务完成日志';

-- 6. 签到系统配置项
INSERT INTO `wallet_settings` (`setting_key`, `setting_value`) VALUES
('sign_enabled', '1'),
('sign_rewards', '[0.1,0.2,0.3,0.5,0.8,1,2]'),
('sign_bonus_2x_rate', '10'),
('sign_bonus_5x_rate', '2'),
('reward_daily_release_rate', '10');
