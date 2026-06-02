-- 024: 群组增加公告字段；将 id=1 的群组作为公共聊天室，注册用户自动加入

-- 1. 群组增加 announcement 字段
ALTER TABLE `groups`
  ADD COLUMN `announcement` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '群公告' AFTER `description`;

-- 2. 群成员表增加 alias 字段（我在本群昵称）
ALTER TABLE `group_members`
  ADD COLUMN `alias` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '我在本群昵称' AFTER `role`;

-- 3. 初始化 id=1 的公共聊天室群组（若不存在）
INSERT INTO `groups` (`id`, `name`, `avatar`, `description`, `announcement`, `owner_id`, `max_members`, `member_count`, `status`, `created_at`, `updated_at`)
SELECT 1, '公共聊天室', '', '所有用户都可以在这里交流', '欢迎加入公共聊天室', 0, 1000000, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `groups` WHERE `id` = 1);

-- 4. 将所有现有用户加入 id=1 的公共聊天室（幂等）
INSERT INTO `group_members` (`group_id`, `user_id`, `role`, `joined_at`)
SELECT 1, u.id, 0, NOW()
FROM `users` u
WHERE NOT EXISTS (
  SELECT 1 FROM `group_members` gm WHERE gm.group_id = 1 AND gm.user_id = u.id
);

-- 5. 更新群成员人数
UPDATE `groups` SET `member_count` = (SELECT COUNT(*) FROM `group_members` WHERE `group_id` = 1) WHERE `id` = 1;
