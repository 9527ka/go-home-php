-- ============================================
-- 迁移 005: 聊天消息支持多媒体类型
-- ============================================

SET NAMES utf8mb4;

-- 新增消息类型、媒体URL、媒体信息字段
ALTER TABLE `chat_messages`
    ADD COLUMN `msg_type`   VARCHAR(20)  NOT NULL DEFAULT 'text' COMMENT '消息类型: text/image/video/voice' AFTER `user_id`,
    ADD COLUMN `media_url`  VARCHAR(500) NOT NULL DEFAULT '' COMMENT '媒体文件URL' AFTER `content`,
    ADD COLUMN `thumb_url`  VARCHAR(500) NOT NULL DEFAULT '' COMMENT '缩略图URL(图片/视频封面)' AFTER `media_url`,
    ADD COLUMN `media_info` JSON         DEFAULT NULL COMMENT '媒体扩展信息(宽高/时长等)' AFTER `thumb_url`;

-- 为消息类型添加索引
ALTER TABLE `chat_messages`
    ADD KEY `idx_msg_type` (`msg_type`);
