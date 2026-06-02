-- =============================================
-- 031: 抽奖系统 — 奖池/奖品/流水
-- =============================================

-- 奖池（MVP 单奖池，保留 pool_key 扩展）
CREATE TABLE IF NOT EXISTS `lottery_pools` (
    `id`                              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `pool_key`                        VARCHAR(30)      NOT NULL DEFAULT 'main',
    `name`                            VARCHAR(50)      NOT NULL DEFAULT '主奖池',
    `cost_per_draw`                   DECIMAL(16,2)    NOT NULL DEFAULT 100.00 COMMENT '单次抽奖消耗爱心币',
    `daily_draw_limit`                INT UNSIGNED     NOT NULL DEFAULT 100    COMMENT '每日抽奖上限',
    `rate_limit_seconds`              INT UNSIGNED     NOT NULL DEFAULT 1      COMMENT '连抽最小间隔(秒)',
    `big_prize_threshold`             DECIMAL(16,2)    NOT NULL DEFAULT 500.00 COMMENT '大奖阈值(金额≥此值视为大奖)',
    `non_recharged_big_prize_weight`  DECIMAL(6,4)     NOT NULL DEFAULT 0.3000 COMMENT '非充值用户大奖权重系数(B3: 0.3)',
    `is_enabled`                      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`                      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pool_key` (`pool_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖奖池';

-- 奖品档位
CREATE TABLE IF NOT EXISTS `lottery_prizes` (
    `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `pool_id`        INT UNSIGNED     NOT NULL,
    `name`           VARCHAR(50)      NOT NULL,
    `reward_amount`  DECIMAL(16,2)    NOT NULL DEFAULT 0.00 COMMENT '中奖爱心币(0=谢谢参与)',
    `weight`         INT UNSIGNED     NOT NULL DEFAULT 100   COMMENT '权重',
    `rarity`         TINYINT UNSIGNED NOT NULL DEFAULT 0     COMMENT '0=普通 1=稀有 2=史诗 3=传说',
    `icon_url`       VARCHAR(500)     NOT NULL DEFAULT '',
    `sort_order`     INT UNSIGNED     NOT NULL DEFAULT 0,
    `is_enabled`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pool_enabled` (`pool_id`, `is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖奖品档位';

-- 抽奖流水
CREATE TABLE IF NOT EXISTS `lottery_logs` (
    `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`           BIGINT UNSIGNED  NOT NULL,
    `pool_id`           INT UNSIGNED     NOT NULL,
    `prize_id`          INT UNSIGNED     NOT NULL,
    `prize_name`        VARCHAR(50)      NOT NULL,
    `cost`              DECIMAL(16,2)    NOT NULL,
    `reward_amount`     DECIMAL(16,2)    NOT NULL,
    `is_big_prize`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否触发大奖',
    `is_recharged_user` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '抽奖时是否充值用户',
    `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_created` (`user_id`, `created_at`),
    KEY `idx_pool_created` (`pool_id`, `created_at`),
    KEY `idx_big_prize` (`is_big_prize`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖流水';

-- 初始奖池
INSERT INTO `lottery_pools` (`pool_key`, `name`, `cost_per_draw`, `daily_draw_limit`, `rate_limit_seconds`, `big_prize_threshold`, `non_recharged_big_prize_weight`) VALUES
('main', '心愿转盘', 100.00, 100, 1, 500.00, 0.3000);

-- 初始奖品（期望回报 ~75%）
-- 权重 = [45, 25, 20, 7, 2, 1], 奖金 = [0, 50, 100, 500, 1000, 5000]
-- 期望 = (0*45 + 50*25 + 100*20 + 500*7 + 1000*2 + 5000*1) / 100 = 76.5 爱心币
INSERT INTO `lottery_prizes` (`pool_id`, `name`, `reward_amount`, `weight`, `rarity`, `sort_order`) VALUES
(1, '谢谢参与', 0.00,    45, 0, 1),
(1, '小奖',     50.00,   25, 0, 2),
(1, '中奖',     100.00,  20, 1, 3),
(1, '大奖',     500.00,  7,  2, 4),
(1, '超级大奖', 1000.00, 2,  3, 5),
(1, '至尊头奖', 5000.00, 1,  3, 6);

-- 扩展钱包流水类型
ALTER TABLE `wallet_transactions`
    MODIFY COLUMN `type` TINYINT UNSIGNED NOT NULL COMMENT '1=充值 2=提现 3=捐赠支出 4=收到捐赠 5=购买曝光 6=发红包 7=收红包 8=红包退回 9=提现退回 10=签到奖励 11=任务奖励 12=奖励释放 13=悬赏冻结 14=悬赏发放 15=悬赏收入 16=悬赏退还 17=VIP购买 18=抽奖消耗 19=抽奖中奖';
