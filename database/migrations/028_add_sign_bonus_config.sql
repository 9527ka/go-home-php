-- 签到暴击系统扩展：支持 2x/5x/10x 可配置倍率与概率，以及 N 天保底
-- 兼容存量：保留 sign_bonus_2x_rate / sign_bonus_5x_rate，默认概率调整为 12/3，新增 10x=1%

-- 更新默认概率（若已存在则保持原值，否则插入）
INSERT INTO `wallet_settings` (`setting_key`, `setting_value`) VALUES
('sign_bonus_2x_multiplier',      '2'),
('sign_bonus_5x_multiplier',      '5'),
('sign_bonus_10x_multiplier',    '10'),
('sign_bonus_10x_rate',           '1'),
('sign_bonus_guarantee_days',     '7'),
('sign_bonus_guarantee_min_rate', '5')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;

-- 原有 2x 概率默认由 10 调整为 12（仅在值仍为旧默认 '10' 时修正；若管理员已改则不动）
UPDATE `wallet_settings`
SET `setting_value` = '12'
WHERE `setting_key` = 'sign_bonus_2x_rate' AND `setting_value` = '10';

-- 原有 5x 概率默认由 2 调整为 3（仅在值仍为旧默认 '2' 时修正）
UPDATE `wallet_settings`
SET `setting_value` = '3'
WHERE `setting_key` = 'sign_bonus_5x_rate' AND `setting_value` = '2';
