-- 011: 新增帖子状态 5=举报屏蔽
-- 举报确认违规时，帖子进入屏蔽状态，待用户修改后重新提交审核

ALTER TABLE `posts`
    MODIFY COLUMN `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=待审核 1=已发布 2=已找到 3=已关闭 4=审核驳回 5=举报屏蔽';
