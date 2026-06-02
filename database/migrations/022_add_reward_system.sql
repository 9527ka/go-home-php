-- =============================================
-- 022: 悬赏系统 — 启事发布者设置悬赏，线索提供者领取
-- =============================================

-- 启事表增加悬赏字段
ALTER TABLE `posts`
    ADD COLUMN `reward_amount` DECIMAL(16,2) NOT NULL DEFAULT 0.00 COMMENT '悬赏总金额' AFTER `clue_count`,
    ADD COLUMN `reward_paid`   DECIMAL(16,2) NOT NULL DEFAULT 0.00 COMMENT '已发放悬赏金额' AFTER `reward_amount`;

-- 悬赏发放记录
CREATE TABLE IF NOT EXISTS `reward_claims` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id`      BIGINT UNSIGNED NOT NULL COMMENT '关联启事',
    `clue_id`      BIGINT UNSIGNED NOT NULL COMMENT '关联线索',
    `from_user_id` BIGINT UNSIGNED NOT NULL COMMENT '发布者(支付方)',
    `to_user_id`   BIGINT UNSIGNED NOT NULL COMMENT '线索提供者(收款方)',
    `amount`       DECIMAL(16,2)  NOT NULL COMMENT '发放金额',
    `message`      VARCHAR(200)   NOT NULL DEFAULT '' COMMENT '发放留言',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_post` (`post_id`),
    KEY `idx_clue` (`clue_id`),
    KEY `idx_from` (`from_user_id`),
    KEY `idx_to` (`to_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='悬赏发放记录';

-- 钱包流水类型扩展（type 13=悬赏冻结 14=悬赏发放 15=悬赏收入 16=悬赏退还）
-- 注: 类型值在 WalletTransactionType 枚举类中定义，此处仅更新 comment
ALTER TABLE `wallet_transactions`
    MODIFY COLUMN `type` TINYINT UNSIGNED NOT NULL COMMENT '1=充值 2=提现 3=捐赠支出 4=收到捐赠 5=购买曝光 6=发红包 7=收红包 8=红包退回 9=提现退回 10=签到奖励 11=任务奖励 12=奖励释放 13=悬赏冻结 14=悬赏发放 15=悬赏收入 16=悬赏退还';

-- 钱包配置: 悬赏最低金额
INSERT INTO `wallet_settings` (`setting_key`, `setting_value`) VALUES
('min_reward', '100')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
