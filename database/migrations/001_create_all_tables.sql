-- ============================================
-- 《回家了么》数据库初始化脚本
-- MySQL 8.0+
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. 用户表
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nickname`      VARCHAR(50)     NOT NULL DEFAULT '',
    `avatar`        VARCHAR(255)    NOT NULL DEFAULT '',
    `account`       VARCHAR(100)    NOT NULL COMMENT '手机号或邮箱',
    `account_type`  TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=手机号 2=邮箱',
    `password`      VARCHAR(255)    NOT NULL COMMENT 'bcrypt哈希',
    `contact_phone` VARCHAR(20)     NOT NULL DEFAULT '' COMMENT '公开联系电话',
    `status`        TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=正常 2=禁言 3=封禁',
    `last_login_at` DATETIME        NULL,
    `last_login_ip` VARCHAR(45)     NOT NULL DEFAULT '',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME        NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_account` (`account`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ----------------------------
-- 2. 启事表
-- ----------------------------
DROP TABLE IF EXISTS `posts`;
CREATE TABLE `posts` (
    `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`         BIGINT UNSIGNED  NOT NULL COMMENT '发布者',
    `category`        TINYINT UNSIGNED NOT NULL COMMENT '1=宠物 2=成年人 3=儿童',
    `lang`            VARCHAR(10)      NOT NULL DEFAULT 'zh-CN' COMMENT '原始语言',
    `name`            VARCHAR(50)      NOT NULL COMMENT '名字/称呼/宠物名',
    `gender`          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=未知 1=男/公 2=女/母',
    `age`             VARCHAR(20)      NOT NULL DEFAULT '' COMMENT '年龄描述',
    `species`         VARCHAR(50)      NOT NULL DEFAULT '' COMMENT '宠物品种(仅宠物类)',
    `appearance`      TEXT             NOT NULL COMMENT '体貌特征描述',
    `description`     TEXT             NOT NULL COMMENT '补充说明/事件经过',
    `lost_at`         DATETIME         NOT NULL COMMENT '走失时间',
    `lost_province`   VARCHAR(50)      NOT NULL DEFAULT '',
    `lost_city`       VARCHAR(50)      NOT NULL DEFAULT '',
    `lost_district`   VARCHAR(50)      NOT NULL DEFAULT '',
    `lost_address`    VARCHAR(255)     NOT NULL DEFAULT '' COMMENT '详细地址',
    `lost_longitude`  DECIMAL(10,7)    NULL COMMENT '经度',
    `lost_latitude`   DECIMAL(10,7)    NULL COMMENT '纬度',
    `contact_name`    VARCHAR(50)      NOT NULL DEFAULT '' COMMENT '联系人姓名',
    `contact_phone`   VARCHAR(20)      NOT NULL COMMENT '联系电话',
    `status`          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=待审核 1=已发布 2=已找到 3=已关闭 4=审核驳回',
    `audit_remark`    VARCHAR(255)     NOT NULL DEFAULT '' COMMENT '审核备注',
    `audited_by`      BIGINT UNSIGNED  NULL COMMENT '审核管理员ID',
    `audited_at`      DATETIME         NULL,
    `view_count`      INT UNSIGNED     NOT NULL DEFAULT 0,
    `clue_count`      INT UNSIGNED     NOT NULL DEFAULT 0,
    `share_count`     INT UNSIGNED     NOT NULL DEFAULT 0,
    `is_top`          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=否 1=置顶',
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`      DATETIME         NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_category_status` (`category`, `status`),
    KEY `idx_status_created` (`status`, `created_at` DESC),
    KEY `idx_lost_city_status` (`lost_city`, `status`),
    KEY `idx_lost_at` (`lost_at`),
    KEY `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='启事表';

-- ----------------------------
-- 3. 启事图片表
-- ----------------------------
DROP TABLE IF EXISTS `post_images`;
CREATE TABLE `post_images` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `post_id`     BIGINT UNSIGNED  NOT NULL,
    `image_url`   VARCHAR(255)     NOT NULL COMMENT '图片路径',
    `thumb_url`   VARCHAR(255)     NOT NULL DEFAULT '' COMMENT '缩略图路径',
    `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序(0=封面)',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_post_id` (`post_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='启事图片表';

-- ----------------------------
-- 4. 启事多语言翻译表
-- ----------------------------
DROP TABLE IF EXISTS `post_translations`;
CREATE TABLE `post_translations` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id`      BIGINT UNSIGNED NOT NULL,
    `lang`         VARCHAR(10)     NOT NULL COMMENT '语言代码',
    `name`         VARCHAR(50)     NOT NULL DEFAULT '',
    `appearance`   TEXT            NOT NULL,
    `description`  TEXT            NOT NULL,
    `lost_address` VARCHAR(255)    NOT NULL DEFAULT '',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_post_lang` (`post_id`, `lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='启事多语言翻译表';

-- ----------------------------
-- 5. 线索表
-- ----------------------------
DROP TABLE IF EXISTS `clues`;
CREATE TABLE `clues` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `post_id`     BIGINT UNSIGNED  NOT NULL COMMENT '关联启事',
    `user_id`     BIGINT UNSIGNED  NOT NULL COMMENT '线索提供者',
    `content`     TEXT             NOT NULL COMMENT '线索内容',
    `images`      VARCHAR(1000)    NOT NULL DEFAULT '' COMMENT '图片路径逗号分隔',
    `contact`     VARCHAR(50)      NOT NULL DEFAULT '' COMMENT '联系方式(可选)',
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=正常 2=已删除 3=被举报',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME         NULL,
    PRIMARY KEY (`id`),
    KEY `idx_post_id` (`post_id`, `created_at` DESC),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='线索表';

-- ----------------------------
-- 6. 举报表
-- ----------------------------
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
    `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED  NOT NULL COMMENT '举报人',
    `target_type`   TINYINT UNSIGNED NOT NULL COMMENT '1=启事 2=线索 3=用户',
    `target_id`     BIGINT UNSIGNED  NOT NULL COMMENT '被举报对象ID',
    `reason`        TINYINT UNSIGNED NOT NULL COMMENT '1=虚假 2=广告 3=违法 4=骚扰 5=其他',
    `description`   VARCHAR(500)     NOT NULL DEFAULT '' COMMENT '补充说明',
    `status`        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=待处理 1=有效 2=无效 3=忽略',
    `handled_by`    BIGINT UNSIGNED  NULL COMMENT '处理人',
    `handle_remark` VARCHAR(255)     NOT NULL DEFAULT '',
    `handled_at`    DATETIME         NULL,
    `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_target` (`target_type`, `target_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='举报表';

-- ----------------------------
-- 7. 收藏表
-- ----------------------------
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `post_id`     BIGINT UNSIGNED NOT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_post` (`user_id`, `post_id`),
    KEY `idx_post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收藏表';

-- ----------------------------
-- 8. 通知表
-- ----------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED  NOT NULL COMMENT '接收者',
    `type`        TINYINT UNSIGNED NOT NULL COMMENT '1=线索 2=审核通过 3=审核驳回 4=举报处理 5=系统',
    `title`       VARCHAR(100)     NOT NULL,
    `content`     VARCHAR(500)     NOT NULL DEFAULT '',
    `post_id`     BIGINT UNSIGNED  NULL COMMENT '关联启事',
    `is_read`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=未读 1=已读',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_read` (`user_id`, `is_read`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知表';

-- ----------------------------
-- 9. 管理员表
-- ----------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
    `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `username`       VARCHAR(50)      NOT NULL,
    `password`       VARCHAR(255)     NOT NULL COMMENT 'bcrypt',
    `realname`       VARCHAR(50)      NOT NULL DEFAULT '',
    `role`           TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=审核员 2=超管',
    `status`         TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=正常 2=禁用',
    `last_login_at`  DATETIME         NULL,
    `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';

-- ----------------------------
-- 10. 地区表
-- ----------------------------
DROP TABLE IF EXISTS `regions`;
CREATE TABLE `regions` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `parent_id`   INT UNSIGNED     NOT NULL DEFAULT 0,
    `name`        VARCHAR(50)      NOT NULL,
    `level`       TINYINT UNSIGNED NOT NULL COMMENT '1=省 2=市 3=区',
    `sort_order`  INT UNSIGNED     NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='地区表';

-- ----------------------------
-- 11. 语言表
-- ----------------------------
DROP TABLE IF EXISTS `languages`;
CREATE TABLE `languages` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(10)      NOT NULL,
    `name`        VARCHAR(50)      NOT NULL,
    `is_default`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `sort_order`  INT UNSIGNED     NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='语言表';

-- ----------------------------
-- 初始数据
-- ----------------------------

-- 默认管理员 (密码: admin123)
INSERT INTO `admins` (`username`, `password`, `realname`, `role`, `status`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '超级管理员', 2, 1);

-- 默认语言
INSERT INTO `languages` (`code`, `name`, `is_default`, `status`, `sort_order`) VALUES
('zh-CN', '简体中文', 1, 1, 1),
('zh-TW', '繁體中文', 0, 1, 2),
('en',    'English',  0, 1, 3),
('ja',    '日本語',    0, 0, 4),
('ko',    '한국어',    0, 0, 5);

SET FOREIGN_KEY_CHECKS = 1;
