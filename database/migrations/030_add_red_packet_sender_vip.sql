-- =============================================
-- 030: 红包快照发送者 VIP 等级（用于特效不随后续升降级变化）
-- =============================================

ALTER TABLE `red_packets`
    ADD COLUMN `sender_vip_level` VARCHAR(20) NOT NULL DEFAULT 'normal'
        COMMENT '发送时快照的 VIP level_key（normal/silver/.../supreme）' AFTER `user_id`;

-- 历史红包默认为 normal（兼容旧数据）
