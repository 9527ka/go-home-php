-- =============================================
-- 032: 用户定位字段（GPS + IP 兜底） + 附近启事配置
-- =============================================

ALTER TABLE `users`
    ADD COLUMN `last_latitude`  DECIMAL(10,7)    NULL AFTER `last_login_ip`,
    ADD COLUMN `last_longitude` DECIMAL(10,7)    NULL AFTER `last_latitude`,
    ADD COLUMN `location_source` TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT '0=无 1=GPS 2=IP' AFTER `last_longitude`,
    ADD COLUMN `location_updated_at` DATETIME NULL AFTER `location_source`,
    ADD KEY `idx_location` (`last_latitude`, `last_longitude`);

-- 附近启事配置项（管理后台可配）
INSERT INTO `wallet_settings` (`setting_key`, `setting_value`) VALUES
('nearby_enabled',           '1'),
('nearby_max_radius_km',     '500'),
('nearby_distance_weight',   '0.6'),
('nearby_recency_weight',    '0.4'),
('nearby_recency_decay_days', '30')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
