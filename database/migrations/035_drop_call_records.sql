-- =====================================================================
-- 035_drop_call_records.sql
--
-- 私聊语音通话切换到腾讯云 TUICallKit（客户端 SDK 内置通话记录 & UI & 信令），
-- 后端自建的 call_records 表和 voice_call 信令不再使用，予以删除。
--
-- 依赖：
--   - 原迁移 027_add_call_records.sql 创建了 call_records 表
--   - CallSignalingHandler.php 已删除（见 ChatServer.php）
-- =====================================================================

DROP TABLE IF EXISTS `call_records`;
