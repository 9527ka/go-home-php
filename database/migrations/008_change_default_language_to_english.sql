-- ============================================
-- 008: 修改默认语言为英文
-- ============================================

-- 将默认语言从简体中文改为英文
UPDATE `languages` 
SET `is_default` = CASE 
    WHEN `code` = 'en' THEN 1 
    WHEN `code` = 'zh-CN' THEN 0 
    ELSE `is_default` 
END,
`sort_order` = CASE 
    WHEN `code` = 'en' THEN 1 
    WHEN `code` = 'zh-CN' THEN 2 
    WHEN `code` = 'zh-TW' THEN 3 
    ELSE `sort_order` 
END;

-- 确保英文语言是启用状态
UPDATE `languages` SET `status` = 1 WHERE `code` = 'en';