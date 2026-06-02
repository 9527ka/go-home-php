-- =============================================
-- 029: VIP 等级体系
-- =============================================

-- VIP 等级配置表（管理后台 CRUD）
CREATE TABLE IF NOT EXISTS `vip_levels` (
    `id`                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `level_key`             VARCHAR(20)      NOT NULL COMMENT '等级标识 normal/silver/gold/platinum/diamond/supreme',
    `level_name`            VARCHAR(30)      NOT NULL COMMENT '等级名称',
    `level_order`           TINYINT UNSIGNED NOT NULL COMMENT '等级序号 1-6，越大越高',
    `price`                 DECIMAL(16,2)    NOT NULL DEFAULT 0.00 COMMENT '月卡售价（爱心币）,0=免费等级',
    `duration_days`         INT UNSIGNED     NOT NULL DEFAULT 30 COMMENT '有效期（天）',
    `sign_bonus_rate`       DECIMAL(6,4)     NOT NULL DEFAULT 0.0000 COMMENT '签到爱心加成 0.05=+5%',
    `crit_prob_bonus`       DECIMAL(6,4)     NOT NULL DEFAULT 0.0000 COMMENT '暴击概率加成 0.02=+2%',
    `crit_max_multiple`     TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '允许的最大暴击倍率 5/10/20',
    `withdraw_fee_rate`     DECIMAL(6,4)     NOT NULL DEFAULT 0.3000 COMMENT '提现手续费率',
    `withdraw_daily_limit`  DECIMAL(16,2)    NOT NULL DEFAULT 1000.00 COMMENT '每日提现额度',
    `icon_url`              VARCHAR(500)     NOT NULL DEFAULT '' COMMENT '徽章图片',
    `badge_effect_key`      VARCHAR(30)      NOT NULL DEFAULT 'none' COMMENT '头像边框效果键',
    `name_effect_key`       VARCHAR(30)      NOT NULL DEFAULT 'none' COMMENT '昵称特效键',
    `red_packet_skin_url`   VARCHAR(500)     NOT NULL DEFAULT '' COMMENT '红包皮肤',
    `red_packet_effect_key` VARCHAR(30)      NOT NULL DEFAULT 'none' COMMENT '红包动效键',
    `is_enabled`            TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `sort_order`            INT UNSIGNED     NOT NULL DEFAULT 0,
    `created_at`            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_level_key` (`level_key`),
    UNIQUE KEY `uk_level_order` (`level_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='VIP 等级配置';

-- 用户 VIP 状态（仅非普通用户写入，读取时若 expired_at<now 视为普通）
CREATE TABLE IF NOT EXISTS `user_vip` (
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `level_key`   VARCHAR(20)     NOT NULL DEFAULT 'normal',
    `expired_at`  DATETIME        NOT NULL COMMENT '到期时间',
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    KEY `idx_expired_at` (`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户 VIP 状态';

-- VIP 购买订单
CREATE TABLE IF NOT EXISTS `vip_orders` (
    `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`         BIGINT UNSIGNED  NOT NULL,
    `level_key`       VARCHAR(20)      NOT NULL,
    `price`           DECIMAL(16,2)    NOT NULL COMMENT '支付金额',
    `duration_days`   INT UNSIGNED     NOT NULL COMMENT '本单增加的天数',
    `balance_before`  DECIMAL(16,2)    NOT NULL,
    `balance_after`   DECIMAL(16,2)    NOT NULL,
    `prev_expired_at` DATETIME         NULL COMMENT '购买前到期时间(空=之前非VIP)',
    `new_expired_at`  DATETIME         NOT NULL COMMENT '购买后到期时间',
    `status`          TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=成功 0=失败',
    `remark`          VARCHAR(255)     NOT NULL DEFAULT '',
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_created` (`user_id`, `created_at`),
    KEY `idx_level_created` (`level_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='VIP 购买订单';

-- 初始化 6 个 VIP 等级（价格/数值按用户确认的最终方案）
INSERT INTO `vip_levels` (
    `level_key`, `level_name`, `level_order`, `price`, `duration_days`,
    `sign_bonus_rate`, `crit_prob_bonus`, `crit_max_multiple`,
    `withdraw_fee_rate`, `withdraw_daily_limit`,
    `badge_effect_key`, `name_effect_key`, `red_packet_effect_key`,
    `sort_order`
) VALUES
('normal',   '普通', 1, 0.00,      30, 0.0000, 0.0000,  5, 0.3000, 1000.00,    'none',             'none',            'none',             1),
('silver',   '白银', 2, 500.00,    30, 0.0500, 0.0200,  5, 0.2500, 5000.00,    'gray_border',      'gray_text',       'silver_skin',      2),
('gold',     '黄金', 3, 1000.00,   30, 0.1000, 0.0400,  5, 0.2000, 10000.00,   'gold_border',      'gold_text',       'gold_skin',        3),
('platinum', '铂金', 4, 2000.00,   30, 0.1500, 0.0600, 10, 0.1500, 50000.00,   'gradient_border',  'gradient_text',   'platinum_skin',    4),
('diamond',  '钻石', 5, 5000.00,   30, 0.2500, 0.1000, 10, 0.1000, 100000.00,  'glow_pulse',       'glow_text',       'diamond_skin',     5),
('supreme',  '至尊', 6, 10000.00,  30, 0.4000, 0.1500, 20, 0.0500, 500000.00,  'rotating_halo',    'rainbow_anim',    'supreme_skin',     6);

-- 扩展钱包流水类型
ALTER TABLE `wallet_transactions`
    MODIFY COLUMN `type` TINYINT UNSIGNED NOT NULL COMMENT '1=充值 2=提现 3=捐赠支出 4=收到捐赠 5=购买曝光 6=发红包 7=收红包 8=红包退回 9=提现退回 10=签到奖励 11=任务奖励 12=奖励释放 13=悬赏冻结 14=悬赏发放 15=悬赏收入 16=悬赏退还 17=VIP购买';

-- 新增签到 20x 配置（M1 需要，这里一并建好避免重复 migration）
INSERT INTO `wallet_settings` (`setting_key`, `setting_value`) VALUES
('sign_bonus_20x_multiplier', '20'),
('sign_bonus_20x_rate',       '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
