-- 为所有已注册用户添加默认客服好友（account=18800000008, id=8）
-- 跳过客服自己和已有好友关系的用户

SET @service_id = (SELECT id FROM `users` WHERE `account` = '18800000008' LIMIT 1);

-- 用户 → 客服
INSERT IGNORE INTO `friendships` (`user_id`, `friend_id`, `created_at`)
SELECT u.id, @service_id, NOW()
FROM `users` u
WHERE u.id != @service_id
  AND u.`status` = 1
  AND u.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM `friendships` f
    WHERE f.user_id = u.id AND f.friend_id = @service_id
  );

-- 客服 → 用户
INSERT IGNORE INTO `friendships` (`user_id`, `friend_id`, `created_at`)
SELECT @service_id, u.id, NOW()
FROM `users` u
WHERE u.id != @service_id
  AND u.`status` = 1
  AND u.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM `friendships` f
    WHERE f.user_id = @service_id AND f.friend_id = u.id
  );

-- 给没有收到过客服消息的用户发送欢迎消息
INSERT INTO `private_messages` (`from_id`, `to_id`, `content`, `msg_type`, `is_read`, `created_at`)
SELECT @service_id, u.id, '欢迎使用回家了么！有任何问题可以随时联系客服。', 'text', 0, NOW()
FROM `users` u
WHERE u.id != @service_id
  AND u.`status` = 1
  AND u.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM `private_messages` pm
    WHERE pm.from_id = @service_id AND pm.to_id = u.id
  );
