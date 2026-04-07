-- 016: 添加「关于我们」页面配置到 wallet_settings 表

INSERT INTO `wallet_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('about_version', 'v1.0.9', NOW()),
('about_telegram', '@go_home_007', NOW()),
('about_website_url', 'https://gohome.douwen.me', NOW()),
('about_website_name', 'gohome.douwen.me', NOW()),
('about_mission', '帮助每一个走失的生命找到回家的路。为走失人员、宠物及丢失物品提供免费的信息发布与传播平台。', NOW()),
('about_safety', '所有信息经过人工审核，联系电话脱敏展示。举报功能保障信息质量，保护用户隐私安全。', NOW()),
('about_free_service', '平台所有功能完全免费。发布启事、分享传播，所有操作均不收取任何费用。', NOW()),
('about_disclaimer', '本平台仅提供信息发布与传播服务，不保证信息的真实性与准确性。如遇紧急情况请立即拨打110报警电话。发布虚假信息将被永久封禁。', NOW()),
('about_privacy', '我们重视用户隐私保护。个人信息仅用于平台服务，不会泄露给第三方。联系电话以脱敏形式展示。', NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
