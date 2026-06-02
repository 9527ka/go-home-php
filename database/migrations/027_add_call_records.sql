-- =====================================================================
-- 027_add_call_records.sql
--
-- 私聊语音通话：通话记录表
--
-- 背景：接入腾讯云 TRTC 纯音频，1v1 语音通话。信令走 WebSocket，
-- 通话流水需要落库以便聊天气泡显示时长/未接/已拒绝等状态。
-- 同时在 private_messages.msg_type 扩展 voice_call，media_info 存
-- {call_id, duration, status}，结束时写入。
-- =====================================================================

SET NAMES utf8mb4;

-- ----------------------------
-- 1. 通话记录表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `call_records` (
    `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `call_id`      VARCHAR(64)      NOT NULL COMMENT '通话唯一标识（TRTC roomId 字符串或 UUID）',
    `caller_id`    BIGINT UNSIGNED  NOT NULL COMMENT '主叫用户 ID',
    `callee_id`    BIGINT UNSIGNED  NOT NULL COMMENT '被叫用户 ID',
    `type`         VARCHAR(10)      NOT NULL DEFAULT 'voice' COMMENT 'voice/video（MVP 仅 voice）',
    `status`       VARCHAR(20)      NOT NULL DEFAULT 'invited' COMMENT 'invited/accepted/completed/missed/declined/canceled/busy/timeout',
    `duration`     INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT '通话时长（秒），结束时写入',
    `started_at`   DATETIME         NULL COMMENT '接通时间（accepted 时写入）',
    `ended_at`     DATETIME         NULL COMMENT '结束时间',
    `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发起时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_call_id` (`call_id`),
    KEY `idx_caller_created` (`caller_id`, `created_at` DESC),
    KEY `idx_callee_created` (`callee_id`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='私聊语音通话记录表';

-- ----------------------------
-- 2. 扩展 private_messages.msg_type 注释（值已由代码层约束，无需改结构）
--    新增 voice_call 类型，media_info = {call_id, duration, status, caller_id, callee_id}
-- ----------------------------
ALTER TABLE `private_messages`
    MODIFY COLUMN `msg_type` VARCHAR(20) NOT NULL DEFAULT 'text'
    COMMENT 'text/image/video/voice/red_packet/voice_call';
