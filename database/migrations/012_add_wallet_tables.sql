-- =============================================
-- 012: 钱包系统 — USDT 充值/提现/捐赠/置顶/红包
-- =============================================

-- 用户钱包
CREATE TABLE IF NOT EXISTS `wallets` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `balance`          DECIMAL(16,2)  NOT NULL DEFAULT 0.00 COMMENT '可用余额(USDT)',
    `frozen_balance`   DECIMAL(16,2)  NOT NULL DEFAULT 0.00 COMMENT '冻结金额(提现审核中)',
    `total_recharged`  DECIMAL(16,2)  NOT NULL DEFAULT 0.00 COMMENT '累计充值',
    `total_withdrawn`  DECIMAL(16,2)  NOT NULL DEFAULT 0.00 COMMENT '累计提现',
    `total_donated`    DECIMAL(16,2)  NOT NULL DEFAULT 0.00 COMMENT '累计捐赠支出',
    `total_received`   DECIMAL(16,2)  NOT NULL DEFAULT 0.00 COMMENT '累计收到捐赠',
    `status`           TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=正常 2=冻结',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户钱包';

-- 钱包流水
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `type`             TINYINT UNSIGNED NOT NULL COMMENT '1=充值 2=提现 3=捐赠支出 4=收到捐赠 5=购买曝光 6=发红包 7=收红包 8=红包退回 9=提现退回',
    `amount`           DECIMAL(16,2)  NOT NULL COMMENT '金额(正数)',
    `balance_before`   DECIMAL(16,2)  NOT NULL COMMENT '变动前余额',
    `balance_after`    DECIMAL(16,2)  NOT NULL COMMENT '变动后余额',
    `related_id`       BIGINT UNSIGNED DEFAULT NULL COMMENT '关联ID(订单/捐赠/红包等)',
    `remark`           VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '备注',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_type` (`user_id`, `type`),
    KEY `idx_user_created` (`user_id`, `created_at`),
    KEY `idx_related` (`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钱包流水';

-- 充值订单
CREATE TABLE IF NOT EXISTS `recharge_orders` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `order_no`         VARCHAR(32)    NOT NULL COMMENT '订单号',
    `amount`           DECIMAL(16,2)  NOT NULL COMMENT '充值金额(USDT)',
    `tx_hash`          VARCHAR(100)   NOT NULL DEFAULT '' COMMENT '链上交易Hash',
    `screenshot_url`   VARCHAR(500)   NOT NULL DEFAULT '' COMMENT '支付截图',
    `status`           TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=待审核 1=已通过 2=已拒绝',
    `admin_id`         BIGINT UNSIGNED DEFAULT NULL COMMENT '审核管理员',
    `admin_remark`     VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '审核备注',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at`     DATETIME DEFAULT NULL COMMENT '审核时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_status` (`user_id`, `status`),
    KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值订单';

-- 提现订单
CREATE TABLE IF NOT EXISTS `withdrawal_orders` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `order_no`         VARCHAR(32)    NOT NULL COMMENT '订单号',
    `amount`           DECIMAL(16,2)  NOT NULL COMMENT '提现金额',
    `fee`              DECIMAL(16,2)  NOT NULL DEFAULT 0.00 COMMENT '手续费',
    `net_amount`       DECIMAL(16,2)  NOT NULL COMMENT '实际到账',
    `wallet_address`   VARCHAR(100)   NOT NULL COMMENT 'USDT钱包地址',
    `chain_type`       VARCHAR(20)    NOT NULL DEFAULT 'TRC20' COMMENT '链类型 TRC20/ERC20',
    `status`           TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=待审核 1=已通过 2=已拒绝 3=已打款',
    `admin_id`         BIGINT UNSIGNED DEFAULT NULL,
    `admin_remark`     VARCHAR(255)   NOT NULL DEFAULT '',
    `tx_hash`          VARCHAR(100)   NOT NULL DEFAULT '' COMMENT '打款交易Hash',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_status` (`user_id`, `status`),
    KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提现订单';

-- 捐赠记录
CREATE TABLE IF NOT EXISTS `donations` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `from_user_id`     BIGINT UNSIGNED NOT NULL COMMENT '捐赠者',
    `to_user_id`       BIGINT UNSIGNED NOT NULL COMMENT '接收者(启事发布者)',
    `post_id`          BIGINT UNSIGNED NOT NULL COMMENT '关联启事',
    `amount`           DECIMAL(16,2)  NOT NULL,
    `message`          VARCHAR(200)   NOT NULL DEFAULT '' COMMENT '留言',
    `is_anonymous`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否匿名',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_post` (`post_id`),
    KEY `idx_from` (`from_user_id`, `created_at`),
    KEY `idx_to` (`to_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='捐赠记录';

-- 启事置顶/曝光
CREATE TABLE IF NOT EXISTS `post_boosts` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `post_id`          BIGINT UNSIGNED NOT NULL,
    `hours`            INT UNSIGNED    NOT NULL COMMENT '购买小时数',
    `total_cost`       DECIMAL(16,2)  NOT NULL COMMENT '总费用',
    `hourly_rate`      DECIMAL(16,2)  NOT NULL DEFAULT 10.00 COMMENT '购买时单价',
    `start_at`         DATETIME NOT NULL,
    `expire_at`        DATETIME NOT NULL,
    `status`           TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=生效中 2=已过期 3=已取消',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_post_status` (`post_id`, `status`),
    KEY `idx_expire` (`expire_at`, `status`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='启事置顶';

-- 红包
CREATE TABLE IF NOT EXISTS `red_packets` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED NOT NULL COMMENT '发送者',
    `target_type`      TINYINT UNSIGNED NOT NULL COMMENT '1=公共聊天 2=私聊 3=群聊',
    `target_id`        BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '私聊对方ID或群ID,公共聊天为0',
    `total_amount`     DECIMAL(16,2)  NOT NULL COMMENT '总金额',
    `total_count`      INT UNSIGNED    NOT NULL COMMENT '总个数',
    `remaining_amount` DECIMAL(16,2)  NOT NULL COMMENT '剩余金额',
    `remaining_count`  INT UNSIGNED    NOT NULL COMMENT '剩余个数',
    `greeting`         VARCHAR(100)   NOT NULL DEFAULT '恭喜发财，大吉大利' COMMENT '祝福语',
    `status`           TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=进行中 2=已领完 3=已过期退回',
    `expire_at`        DATETIME NOT NULL COMMENT '过期时间',
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_target` (`target_type`, `target_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_expire` (`expire_at`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='红包';

-- 红包领取记录
CREATE TABLE IF NOT EXISTS `red_packet_claims` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `red_packet_id`    BIGINT UNSIGNED NOT NULL,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `amount`           DECIMAL(16,2)  NOT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_packet_user` (`red_packet_id`, `user_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='红包领取记录';

-- 钱包配置(KV表)
CREATE TABLE IF NOT EXISTS `wallet_settings` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`      VARCHAR(50)  NOT NULL,
    `setting_value`    TEXT         NOT NULL,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钱包配置';

-- 初始配置
INSERT INTO `wallet_settings` (`setting_key`, `setting_value`) VALUES
('wallet_enabled', '1'),
('usdt_address_trc20', ''),
('usdt_address_erc20', ''),
('min_recharge', '10'),
('min_withdrawal', '20'),
('withdrawal_fee_rate', '0.05'),
('boost_hourly_rate', '10'),
('min_donation', '1'),
('red_packet_expire_hours', '24'),
('max_red_packet_amount', '500');
