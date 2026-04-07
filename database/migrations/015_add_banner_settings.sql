-- 015: 添加公告横幅配置到 wallet_settings 表
-- banner_enabled: 公告开关
-- banner_text: 公告内容
-- banner_link: 点击跳转链接

INSERT INTO `wallet_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('banner_enabled', '1', NOW()),
('banner_text', '✨ 抖文 — 用AI重新讲述世界 | 精选好文 · AI视频 · 每日更新 👉 www.douwen.me', NOW()),
('banner_link', 'https://www.douwen.me', NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
