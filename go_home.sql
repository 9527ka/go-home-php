-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: go_home
-- ------------------------------------------------------
-- Server version	5.7.44-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_audit_logs`
--

DROP TABLE IF EXISTS `admin_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL COMMENT '操作管理员ID',
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型: approve/reject/takedown/ban_user/delete_clue/send_notify',
  `target_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作对象类型: post/user/clue/report',
  `target_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '操作对象ID',
  `detail` text COLLATE utf8mb4_unicode_ci COMMENT '操作详情(JSON)',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作IP',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_created` (`admin_id`,`created_at`),
  KEY `idx_action_created` (`action`,`created_at`),
  KEY `idx_target` (`target_type`,`target_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员操作审计日志';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_audit_logs`
--

LOCK TABLES `admin_audit_logs` WRITE;
/*!40000 ALTER TABLE `admin_audit_logs` DISABLE KEYS */;
INSERT INTO `admin_audit_logs` VALUES (1,1,'reject','post',11,'{\"remark\":\"1221\"}','172.69.176.42','2026-02-26 11:51:31'),(2,1,'approve','post',10,NULL,'172.69.176.42','2026-02-26 11:51:37'),(3,1,'takedown','post',10,'{\"report_id\":10,\"remark\":\"777788888\"}','162.158.107.52','2026-02-26 12:03:21'),(4,1,'takedown','post',12,'{\"report_id\":9,\"remark\":\"88888888888\"}','162.158.107.51','2026-02-26 12:05:29'),(5,1,'approve','post',12,NULL,'162.158.88.154','2026-02-26 15:13:25'),(6,1,'takedown','post',12,'{\"report_id\":11,\"remark\":\"8899999\"}','162.158.88.155','2026-02-26 15:14:50'),(7,1,'reject','post',9,'{\"remark\":\"55646\"}','108.162.226.156','2026-02-26 15:37:51'),(8,1,'approve','post',13,NULL,'172.70.92.246','2026-02-26 15:52:48'),(9,1,'takedown','post',13,'{\"report_id\":12,\"remark\":\"9998889899\"}','172.70.92.246','2026-02-26 15:53:39'),(10,1,'reject','post',13,'{\"remark\":\"678678678\"}','172.70.92.246','2026-02-26 15:54:48'),(11,1,'takedown','post',6,'{\"report_id\":13,\"remark\":\"已处理, 感谢您的监督\"}','162.159.98.8','2026-03-03 14:01:02');
/*!40000 ALTER TABLE `admin_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'bcrypt',
  `realname` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '1=审核员 2=超管',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '1=正常 2=禁用',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'admin','$2y$10$8X6IHgHbJuHJnjsMNC5fKOr1yhyk6UnRIhcDibHtjafZ0p3xYF6Hm','超级管理员',2,1,'2026-03-02 13:52:12','2026-02-10 14:28:18','2026-03-02 13:52:12');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT '发送者ID',
  `msg_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text' COMMENT '消息类型: text/image/video/voice',
  `content` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '消息内容',
  `media_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '媒体文件URL',
  `thumb_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '缩略图URL(图片/视频封面)',
  `media_info` json DEFAULT NULL COMMENT '媒体扩展信息(宽高/时长等)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_msg_type` (`msg_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='聊天室消息';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
INSERT INTO `chat_messages` VALUES (9,2,'image','','/uploads/20260211/0e7f42678a9f65fd885ac4f536811f11.jpg','/uploads/20260211/0e7f42678a9f65fd885ac4f536811f11_thumb.jpg',NULL,'2026-02-11 20:13:58'),(10,2,'image','','/uploads/20260211/01953a71fdaf52cef4aebeb6e3be1afa.jpg','/uploads/20260211/01953a71fdaf52cef4aebeb6e3be1afa_thumb.jpg',NULL,'2026-02-11 20:15:41'),(11,2,'image','','/uploads/20260211/0d1609109a9b233035ca3be2b52869cb.jpg','/uploads/20260211/0d1609109a9b233035ca3be2b52869cb_thumb.jpg',NULL,'2026-02-11 20:16:24'),(12,2,'image','','/uploads/20260211/99f86aa93948fbc61a1e6765d981942d.jpg','/uploads/20260211/99f86aa93948fbc61a1e6765d981942d_thumb.jpg',NULL,'2026-02-11 20:19:10'),(13,2,'text','😇😇🤗🤗🤗🤗🤗🤗🤗🤗','','',NULL,'2026-02-11 20:24:26'),(14,2,'text','嘿嘿','','',NULL,'2026-02-11 20:40:29'),(15,2,'text','嘿嘿','','',NULL,'2026-02-11 20:40:36'),(16,2,'image','','https://go-home.dengshop.com/uploads/20260211/5a5e9acf0db42e3f4d135140fcf9ed06.jpg','https://go-home.dengshop.com/uploads/20260211/5a5e9acf0db42e3f4d135140fcf9ed06_thumb.jpg',NULL,'2026-02-11 20:48:48'),(17,2,'image','','https://go-home.dengshop.com/uploads/20260211/4c12003f96795d5537a4ee490a4b6ed4.jpg','https://go-home.dengshop.com/uploads/20260211/4c12003f96795d5537a4ee490a4b6ed4_thumb.jpg',NULL,'2026-02-11 20:50:04'),(18,7,'text','2','','',NULL,'2026-02-11 23:13:38'),(19,7,'text','😅😅','','',NULL,'2026-02-11 23:13:41'),(20,7,'text','😄😄😁😁','','',NULL,'2026-02-11 23:13:51'),(21,7,'text','111','','',NULL,'2026-02-11 23:14:25'),(22,8,'image','','/uploads/20260211/0ee6958f231148aaf5ef924f4d78b857.jpg','/uploads/20260211/0ee6958f231148aaf5ef924f4d78b857_thumb.jpg',NULL,'2026-02-11 23:26:38'),(23,8,'text','2021年12月17日出生，于2021年12月28日早晨5点左右发现被遗弃在湖南省浏阳市荷花街道浏河村，被村民夫妇捡拾并抚养至今。被捡拾时身穿白底小花的和尚服，一件浅黄色的薄棉衣，用黄色的条纹被包裹着放在一个老式的竹编箩筐内，箩筐内放有一张红色的纸条，上面写有小孩的出生日期：2021年12月17日（阳历）午时出生。','','',NULL,'2026-02-11 23:26:55'),(24,9,'text','👌','','',NULL,'2026-02-11 23:29:32'),(25,9,'image','','/uploads/20260211/dd571bed8cb7968f6fe07f9ce7ad5610.jpg','/uploads/20260211/dd571bed8cb7968f6fe07f9ce7ad5610_thumb.jpg',NULL,'2026-02-11 23:29:40'),(26,9,'text','寻人启事：谷欣依，女，44岁，身高1.62米左右，河北省保定市人，在保定化妆品公司驻上海杨浦区办事处工作，是化妆品营销负责人，在上海工作有9年了。和朱先生通过交友软件认识，因软件关闭于一年前2023年失去联系。','','',NULL,'2026-02-11 23:29:45'),(27,9,'text','现在她的朋友朱先生，急切的需要联系到她，希望她本人或认识她的朋友，尽快联系朱先生，必有重谢。','','',NULL,'2026-02-11 23:29:58'),(28,10,'voice','','/uploads/20260212/af7508a92d69fc3afe291bdc7e564b50.m4a','','{\"duration\": 60}','2026-02-12 14:20:05'),(29,10,'voice','','/uploads/20260212/ad3e887fd6b6fadbf6a9c3ec361b0053.m4a','','{\"duration\": 5}','2026-02-12 14:31:32'),(42,2,'text','12','','',NULL,'2026-02-25 23:03:12'),(43,2,'text','😅','','',NULL,'2026-02-25 23:03:18'),(44,23,'text','566','','',NULL,'2026-02-26 11:08:36'),(45,2,'text','246','','',NULL,'2026-02-26 15:17:36'),(46,25,'text','😆😅','','',NULL,'2026-02-26 15:31:57'),(47,25,'text','11','','',NULL,'2026-03-02 13:45:40'),(48,25,'text','22','','',NULL,'2026-03-02 13:45:40'),(49,25,'text','11','','',NULL,'2026-03-02 13:45:40'),(50,25,'text','12','','',NULL,'2026-03-02 14:57:35'),(51,2,'text','hi','','',NULL,'2026-03-02 18:15:18'),(52,2,'text','😆','','',NULL,'2026-03-03 00:25:03');
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clues`
--

DROP TABLE IF EXISTS `clues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clues` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL COMMENT '关联启事',
  `user_id` bigint(20) unsigned NOT NULL COMMENT '线索提供者',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '线索内容',
  `images` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '图片路径逗号分隔',
  `contact` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联系方式(可选)',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '1=正常 2=已删除 3=被举报',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`,`created_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_post_status` (`post_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='线索表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clues`
--

LOCK TABLES `clues` WRITE;
/*!40000 ALTER TABLE `clues` DISABLE KEYS */;
INSERT INTO `clues` VALUES (1,2,1,'我在中关村附近看到一位类似的老人','','13900139000',1,'2026-02-10 15:01:25',NULL),(2,4,2,'我在小区门口看到一只流浪的田园犬，毛色和照片上的很像，在垃圾桶附近觅食','/uploads/20260210/0a5df2f24913ef55a03dbe31e079342d.jpg','13912345678',1,'2026-02-10 15:11:54',NULL),(3,1,13,'我之前在中山石岐区的一家内衣店买过东西，店主好像就叫王艳，不过最近路过发现那家店已经关门了','/uploads/20260215/a9c6498bc6af95ae85a05c3a20d4765f.jpg,/uploads/20260215/b5a335fc0912d94d9cea176091724cc6.jpg','',1,'2026-02-15 11:29:27',NULL),(4,8,2,'我是浏河村附近的居民，记得2021年12月底那天早上确实听到过婴儿哭声，当时还看到一辆白色面包车停在村口','/uploads/20260218/198a814dc0454a6e4e289e23b9830210.jpg','',1,'2026-02-18 23:58:06',NULL),(5,8,23,'我在荷花街道经营小卖部，那天凌晨有一个年轻女人来买过东西，看起来很紧张，怀里好像抱着什么','/uploads/20260226/517840bd243b64c1b5e223b4022f0a9f.jpg','',1,'2026-02-26 11:46:39',NULL);
/*!40000 ALTER TABLE `clues` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `favorites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `post_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_post` (`user_id`,`post_id`),
  KEY `idx_post_id` (`post_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收藏表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorites`
--

LOCK TABLES `favorites` WRITE;
/*!40000 ALTER TABLE `favorites` DISABLE KEYS */;
INSERT INTO `favorites` VALUES (1,1,2,'2026-02-10 15:01:24');
/*!40000 ALTER TABLE `favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feedbacks`
--

DROP TABLE IF EXISTS `feedbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedbacks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT '用户ID',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '反馈内容',
  `contact` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联系方式(可选)',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=待查看 1=已查看 2=已回复',
  `admin_reply` text COLLATE utf8mb4_unicode_ci COMMENT '管理员回复(预留)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户反馈';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feedbacks`
--

LOCK TABLES `feedbacks` WRITE;
/*!40000 ALTER TABLE `feedbacks` DISABLE KEYS */;
INSERT INTO `feedbacks` VALUES (1,2,'123123','123123',2,'13123123','2026-02-11 17:28:38');
/*!40000 ALTER TABLE `feedbacks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `friend_requests`
--

DROP TABLE IF EXISTS `friend_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `friend_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_id` bigint(20) unsigned NOT NULL COMMENT '发送者',
  `to_id` bigint(20) unsigned NOT NULL COMMENT '接收者',
  `message` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '验证消息',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=待处理 1=已接受 2=已拒绝',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_to_status` (`to_id`,`status`,`created_at`),
  KEY `idx_from_to` (`from_id`,`to_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友请求表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `friend_requests`
--

LOCK TABLES `friend_requests` WRITE;
/*!40000 ALTER TABLE `friend_requests` DISABLE KEYS */;
INSERT INTO `friend_requests` VALUES (1,0,11,'',0,'2026-02-25 13:55:04','2026-02-25 13:55:04'),(2,0,22,'',0,'2026-02-25 13:57:08','2026-02-25 13:57:08'),(3,0,2,'',1,'2026-02-25 14:05:32','2026-02-25 16:22:26'),(4,0,6,'112233',0,'2026-02-25 14:24:02','2026-02-25 14:24:02'),(5,2,10,'',0,'2026-02-25 22:58:43','2026-02-25 22:58:43');
/*!40000 ALTER TABLE `friend_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `friendships`
--

DROP TABLE IF EXISTS `friendships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `friendships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT '用户A',
  `friend_id` bigint(20) unsigned NOT NULL COMMENT '用户B（好友）',
  `remark` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '好友备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_friend` (`user_id`,`friend_id`),
  KEY `idx_friend_id` (`friend_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友关系表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `friendships`
--

LOCK TABLES `friendships` WRITE;
/*!40000 ALTER TABLE `friendships` DISABLE KEYS */;
INSERT INTO `friendships` VALUES (1,2,0,'','2026-02-25 16:22:25'),(2,0,2,'','2026-02-25 16:22:25');
/*!40000 ALTER TABLE `friendships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_members`
--

DROP TABLE IF EXISTS `group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=普通成员 1=管理员 2=群主',
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_group_user` (`group_id`,`user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群成员表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_members`
--

LOCK TABLES `group_members` WRITE;
/*!40000 ALTER TABLE `group_members` DISABLE KEYS */;
INSERT INTO `group_members` VALUES (1,1,2,2,'2026-02-25 16:22:44');
/*!40000 ALTER TABLE `group_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_messages`
--

DROP TABLE IF EXISTS `group_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL COMMENT '发送者',
  `msg_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text' COMMENT 'text/image/video/voice',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `thumb_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_info` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_group_created` (`group_id`,`created_at`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群聊消息表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_messages`
--

LOCK TABLES `group_messages` WRITE;
/*!40000 ALTER TABLE `group_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `group_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '群名',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '群头像',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '群简介',
  `owner_id` bigint(20) unsigned NOT NULL COMMENT '群主',
  `max_members` int(10) unsigned NOT NULL DEFAULT '100' COMMENT '最大人数',
  `member_count` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '当前人数',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '1=活跃 2=已解散',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群组表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `languages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='语言表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` VALUES (1,'zh-CN','简体中文',1,1,1),(2,'zh-TW','繁體中文',0,1,2),(3,'en','English',0,1,3),(4,'ja','日本語',0,0,4),(5,'ko','한국어',0,0,5);
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT '接收者',
  `type` tinyint(3) unsigned NOT NULL COMMENT '1=线索 2=审核通过 3=审核驳回 4=举报处理 5=系统',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `post_id` bigint(20) unsigned DEFAULT NULL COMMENT '关联启事',
  `is_read` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=未读 1=已读',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`,`created_at`),
  KEY `idx_user_read_created` (`user_id`,`is_read`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,2,'您的启事已审核通过','您发布的「测试老人」已通过审核，现已公开展示。祝早日找到！',3,0,'2026-02-10 14:59:43'),(2,1,2,'您的启事已审核通过','您发布的「测试老人」已通过审核，现已公开展示。祝早日找到！',2,0,'2026-02-10 14:59:46'),(3,2,2,'您的启事已审核通过','您发布的「123」已通过审核，现已公开展示。祝早日找到！',1,1,'2026-02-10 14:59:48'),(4,1,1,'您的启事收到新线索','有人为「测试老人」提供了新线索，请尽快查看。',2,0,'2026-02-10 15:01:24'),(5,2,2,'您的启事已审核通过','您发布的「中华田园犬」已通过审核，现已公开展示。祝早日找到！',4,1,'2026-02-10 15:11:02'),(6,2,1,'您的启事收到新线索','有人为「中华田园犬」提供了新线索，请尽快查看。',4,1,'2026-02-10 15:11:53'),(7,2,2,'您的启事已审核通过','您发布的「被捡拾抚养人」已通过审核，现已公开展示。祝早日找到！',5,1,'2026-02-10 20:53:35'),(8,2,2,'您的启事已审核通过','您发布的「谷欣依」已通过审核，现已公开展示。祝早日找到！',6,1,'2026-02-11 17:39:17'),(9,2,2,'您的启事已审核通过','您发布的「被捡拾抚养人」已通过审核，现已公开展示。祝早日找到！',8,1,'2026-02-11 22:57:01'),(10,2,2,'您的启事已审核通过','您发布的「王艳」已通过审核，现已公开展示。祝早日找到！',7,1,'2026-02-11 22:57:03'),(11,2,1,'您的启事收到新线索','有人为「王艳」提供了新线索，请尽快查看。',1,1,'2026-02-15 11:29:26'),(12,2,1,'您的启事收到新线索','有人为「被捡拾抚养人」提供了新线索，请尽快查看。',8,1,'2026-02-18 23:58:06'),(13,2,1,'您的启事收到新线索','有人为「被捡拾抚养人」提供了新线索，请尽快查看。',8,0,'2026-02-26 11:46:38'),(14,23,2,'您的启事已审核通过','您发布的「11122」已通过审核，现已公开展示。祝早日找到！',12,1,'2026-02-26 11:48:37'),(15,2,3,'您的启事未通过审核','您发布的「123123」未通过审核。原因：1221。请修改后重新提交。',11,0,'2026-02-26 11:50:11'),(16,2,3,'您的启事未通过审核','您发布的「123123」未通过审核。原因：1221。请修改后重新提交。',11,0,'2026-02-26 11:50:16'),(17,2,3,'您的启事未通过审核','您发布的「123123」未通过审核。原因：1221。请修改后重新提交。',11,0,'2026-02-26 11:51:31'),(18,2,2,'您的启事已审核通过','您发布的「3455555」已通过审核，现已公开展示。祝早日找到！',10,0,'2026-02-26 11:51:37'),(19,2,3,'您的启事未通过审核','您发布的「3455555」未通过审核。原因：您的启事因被举报违规已被屏蔽：777788888。请修改后重新提交。请修改后重新提交。',10,1,'2026-02-26 12:03:20'),(20,23,3,'您的启事未通过审核','您发布的「11122」未通过审核。原因：您的启事因被举报违规已被屏蔽：88888888888。请修改后重新提交。请修改后重新提交。',12,1,'2026-02-26 12:05:29'),(21,23,2,'您的启事已审核通过','您发布的「11122666」已通过审核，现已公开展示。祝早日找到！',12,0,'2026-02-26 15:13:24'),(22,23,6,'您的启事因举报违规已被屏蔽','您发布的「11122666」因被举报违规已被屏蔽。原因：8899999。请修改后重新提交审核。',12,0,'2026-02-26 15:14:49'),(23,8,3,'您的启事未通过审核','您发布的「2」未通过审核。原因：55646。请修改后重新提交。',9,0,'2026-02-26 15:37:50'),(24,25,2,'您的启事已审核通过','您发布的「5555」已通过审核，现已公开展示。祝早日找到！',13,0,'2026-02-26 15:52:47'),(25,25,6,'您的启事因举报违规已被屏蔽','您发布的「5555」因被举报违规已被屏蔽。原因：9998889899。请修改后重新提交审核。',13,1,'2026-02-26 15:53:39'),(26,25,3,'您的启事未通过审核','您发布的「555588788778」未通过审核。原因：678678678。请修改后重新提交。',13,0,'2026-02-26 15:54:47'),(27,2,6,'您的启事因举报违规已被屏蔽','您发布的「谷欣依」因被举报违规已被屏蔽。原因：已处理, 感谢您的监督。请修改后重新提交审核。',6,0,'2026-03-03 14:01:02');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `post_images`
--

DROP TABLE IF EXISTS `post_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '图片路径',
  `thumb_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '缩略图路径',
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '排序(0=封面)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='启事图片表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `post_images`
--

LOCK TABLES `post_images` WRITE;
/*!40000 ALTER TABLE `post_images` DISABLE KEYS */;
INSERT INTO `post_images` VALUES (1,1,'https://go-home.dengshop.com/uploads/20260211/a629287593609fe0199e6846319b58cc.jpg','/uploads/20260211/a629287593609fe0199e6846319b58cc_thumb.jpg',0,'2026-02-10 14:50:36'),(2,4,'/uploads/20260210/3db693c1fd1ebe5d97192339dd30a973.jpg','/uploads/20260210/3db693c1fd1ebe5d97192339dd30a973_thumb.jpg',0,'2026-02-10 15:10:43'),(3,4,'/uploads/20260210/8144bc121bb255fd256ee7fed2a4c04b.jpg','/uploads/20260210/8144bc121bb255fd256ee7fed2a4c04b_thumb.jpg',1,'2026-02-10 15:10:43'),(4,5,'/uploads/20260210/235dcd3b1e1b51f91caf5160a9a70e83.jpg','/uploads/20260210/235dcd3b1e1b51f91caf5160a9a70e83_thumb.jpg',0,'2026-02-10 20:45:34'),(5,6,'/uploads/20260211/f5f83fb6f3cb802e8fab9649fd36bdfb.jpg','/uploads/20260211/f5f83fb6f3cb802e8fab9649fd36bdfb_thumb.jpg',0,'2026-02-11 17:39:00'),(6,7,'https://go-home.dengshop.com/uploads/20260211/a629287593609fe0199e6846319b58cc.jpg','/uploads/20260211/a629287593609fe0199e6846319b58cc_thumb.jpg',0,'2026-02-11 22:53:08'),(7,8,'/uploads/20260211/7602de54b2e4ffaa18316b9c90ab5dfd.jpg','/uploads/20260211/7602de54b2e4ffaa18316b9c90ab5dfd_thumb.jpg',0,'2026-02-11 22:56:34'),(8,10,'https://go-home.dengshop.com/uploads/20260212/64916a7490af9ed5863a7f4e8e41a49f.jpg','https://go-home.dengshop.com/uploads/20260212/64916a7490af9ed5863a7f4e8e41a49f_thumb.jpg',0,'2026-02-12 11:00:06'),(9,11,'/uploads/20260218/e1a919b20b0e1a74fa53223db8925948.jpg','/uploads/20260218/e1a919b20b0e1a74fa53223db8925948_thumb.jpg',0,'2026-02-18 23:34:40'),(10,12,'/uploads/20260226/155b266ca97c10131136ebdb7b23665f.jpg','/uploads/20260226/155b266ca97c10131136ebdb7b23665f_thumb.jpg',0,'2026-02-26 11:48:26'),(11,13,'/uploads/20260226/7ba1b55c0e916c903041ad8c32c16223.jpg','/uploads/20260226/7ba1b55c0e916c903041ad8c32c16223_thumb.jpg',0,'2026-02-26 15:52:25');
/*!40000 ALTER TABLE `post_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `post_translations`
--

DROP TABLE IF EXISTS `post_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_translations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL,
  `lang` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '语言代码',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `appearance` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lost_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_post_lang` (`post_id`,`lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='启事多语言翻译表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `post_translations`
--

LOCK TABLES `post_translations` WRITE;
/*!40000 ALTER TABLE `post_translations` DISABLE KEYS */;
/*!40000 ALTER TABLE `post_translations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT '发布者',
  `category` tinyint(3) unsigned NOT NULL COMMENT '1=å® ç‰© 2=è€äºº 3=å„¿ç«¥ 4=å…¶å®ƒç‰©å“',
  `lang` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'zh-CN' COMMENT '原始语言',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '名字/称呼/宠物名',
  `gender` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=未知 1=男/公 2=女/母',
  `age` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '年龄描述',
  `species` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '宠物品种(仅宠物类)',
  `appearance` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '体貌特征描述',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '补充说明/事件经过',
  `lost_at` datetime NOT NULL COMMENT '走失时间',
  `lost_province` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lost_city` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lost_district` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lost_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '详细地址',
  `lost_longitude` decimal(10,7) DEFAULT NULL COMMENT '经度',
  `lost_latitude` decimal(10,7) DEFAULT NULL COMMENT '纬度',
  `contact_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联系人姓名',
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '联系电话',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=待审核 1=已发布 2=已找到 3=已关闭 4=审核驳回 5=举报屏蔽',
  `audit_remark` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '审核备注',
  `audited_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核管理员ID',
  `audited_at` datetime DEFAULT NULL,
  `view_count` int(10) unsigned NOT NULL DEFAULT '0',
  `clue_count` int(10) unsigned NOT NULL DEFAULT '0',
  `share_count` int(10) unsigned NOT NULL DEFAULT '0',
  `is_top` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=否 1=置顶',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category_status` (`category`,`status`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_lost_city_status` (`lost_city`,`status`),
  KEY `idx_lost_at` (`lost_at`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_category_status_created` (`category`,`status`,`created_at`),
  KEY `idx_city_status` (`lost_city`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='启事表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `posts`
--

LOCK TABLES `posts` WRITE;
/*!40000 ALTER TABLE `posts` DISABLE KEYS */;
INSERT INTO `posts` VALUES (1,2,2,'zh-CN','王艳',2,'40','','身高1.65米左右, 脸微胖','山东省青岛市人，在广东省中山市石岐区开了一家内衣针织店，有台路虎车。和傅先生通过有缘交友软件认识，于2022年初失去联系。\n现在她的朋友傅先生，急切的需要联系到她，希望她本人或认识她的朋友，尽快联系傅先生，必有重谢。','2022-01-13 22:48:00','','广东省中山市','','石岐区',NULL,NULL,'','13852438500',1,'',1,'2026-02-11 20:57:03',26,1,0,0,'2026-02-11 20:53:09','2026-03-03 13:59:24',NULL),(2,1,2,'zh-CN','测试老人',1,'70','','身高165cm，白头发，走路时需要拐杖','走失时穿蓝色外套','2026-02-09 14:00:00','','北京','海淀区','中关村南大街',NULL,NULL,'张三','13800138000',4,'',1,'2026-02-10 14:59:46',1,1,0,0,'2026-02-10 14:53:47','2026-02-11 22:57:59',NULL),(3,1,2,'zh-CN','测试老人',1,'70','','身高165cm白头发走路时需要拐杖辅助行走','走失时穿蓝色外套','2026-02-09 14:00:00','','北京','海淀区','中关村南大街',NULL,NULL,'张三','13800138000',4,'',1,'2026-02-10 14:59:43',2,0,0,0,'2026-02-10 14:59:24','2026-02-11 22:58:01',NULL),(4,2,1,'zh-CN','中华田园犬',1,'12','21','12212212112','12212122','2026-02-10 15:09:54','','122121','1221','2121',NULL,NULL,'21212','18800000002',4,'',1,'2026-02-10 15:11:02',3,1,0,0,'2026-02-10 15:10:44','2026-02-11 22:58:03',NULL),(5,2,3,'zh-CN','被捡拾抚养人',1,'4','','被捡拾时用一件披风包裹着，地上放有一个袋子，袋子里有一套婴儿的打底衣、一桶牛奶和一个奶瓶，婴儿身上有一个红包，红包上写有“无尝赠养','2022年2月24日出生。2022年2月27日凌晨1点左右发现被遗弃在湖南省浏阳市荷花街道南环村家门口旁边的木架上，被村民捡拾并抚养至今。被捡拾时用一件披风包裹着，地上放有一个袋子，袋子里有一套婴儿的打底衣、一桶牛奶和一个奶瓶，婴儿身上有一个红包，红包上写有“无尝赠养，出生年月2022年正月24日16点30分，阳历2022年2月24日16点30分”。红包里有200元现金。\n现进行公告，如有其生父母和其他监护人信息或者其他相关线索，请及时与我们联系。','2022-02-27 13:28:00','','湖南省浏阳市','','荷花街道南环村',NULL,NULL,'110寻人网','13852438500',1,'',1,'2026-02-10 20:53:35',15,0,0,0,'2026-02-10 20:45:34','2026-03-03 13:58:43',NULL),(6,2,2,'zh-CN','谷欣依',2,'44','','身高1.62米左右, 长相清甜','河北省保定市人，在保定化妆品公司驻上海杨浦区办事处工作，是化妆品营销负责人，在上海工作有9年了。和朱先生通过交友软件认识，因软件关闭于一年前2023年失去联系。\r\n现在她的朋友朱先生，急切的需要联系到她，希望她本人或认识她的朋友，尽快联系朱先生，必有重谢。','2025-02-11 17:36:00','','河北省保定市','','保定化妆品公司驻上海杨浦区办事处',NULL,NULL,'110寻人网','138 5243 8500',5,'因用户举报被屏蔽：已处理, 感谢您的监督。请修改后重新提交审核。',1,'2026-03-03 14:01:02',14,0,0,0,'2026-02-11 17:39:01','2026-03-03 14:01:02',NULL),(8,2,3,'zh-CN','被捡拾抚养人',2,'5','','被捡拾时身穿白底小花的和尚服，一件浅黄色的薄棉衣，用黄色的条纹被包裹着放在一个老式的竹编箩筐内，箩筐内放有一张红色的纸条，上面写有小孩的出生日期：2021年12月17日（阳历）午时出生。','于2021年12月28日早晨5点左右发现被遗弃在湖南省浏阳市荷花街道浏河村，被村民夫妇捡拾并抚养至今。','2021-12-28 05:20:00','','湖南省浏阳市','','荷花街道浏河村',NULL,NULL,'110寻人网','13852438500',1,'',1,'2026-02-11 22:57:01',71,2,0,0,'2026-02-11 22:56:35','2026-03-03 13:59:37',NULL),(9,8,2,'zh-CN','2',0,'123','','12312221212121','123','2026-02-11 23:16:16','','123','123','123',NULL,NULL,'123','18899999999',4,'55646',1,'2026-02-26 15:37:50',0,0,0,0,'2026-02-11 23:16:43','2026-02-26 15:37:51',NULL),(10,2,2,'zh-CN','3455555',1,'','','12212222266777777','22222','2026-02-12 15:12:00','','111','','2222',NULL,NULL,'3123','18881111111',4,'因用户举报被屏蔽：777788888。请修改后重新提交审核。',1,'2026-02-26 12:03:20',2,0,0,0,'2026-02-12 11:00:06','2026-02-26 15:15:27',NULL),(11,2,2,'zh-CN','123123',0,'','','123213jjjjjjjj','123123','2026-02-18 23:33:59','','123123','','123123',NULL,NULL,'AA','18800000001',4,'1221',1,'2026-02-26 11:51:31',1,0,0,0,'2026-02-18 23:34:41','2026-03-03 00:24:48',NULL),(12,23,2,'zh-CN','11122666',0,'12666','','122122212121666','2212126666','2026-02-26 11:48:03','','1221','','1221221',NULL,NULL,'1212','18800009999',5,'因用户举报被屏蔽：8899999。请修改后重新提交审核。',1,'2026-02-26 15:14:49',7,0,0,0,'2026-02-26 11:48:27','2026-02-26 15:14:50',NULL),(13,25,2,'zh-CN','555588788778',0,'34','','556678899777777','77666','2026-02-26 15:51:58','','5656787878','','456456788787',NULL,NULL,'','18899990000',4,'678678678',1,'2026-02-26 15:54:47',3,0,0,0,'2026-02-26 15:52:26','2026-02-26 15:54:48',NULL);
/*!40000 ALTER TABLE `posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `private_messages`
--

DROP TABLE IF EXISTS `private_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `private_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_id` bigint(20) unsigned NOT NULL COMMENT '发送者',
  `to_id` bigint(20) unsigned NOT NULL COMMENT '接收者',
  `msg_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text' COMMENT 'text/image/video/voice',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '消息内容',
  `media_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `thumb_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_info` json DEFAULT NULL COMMENT '媒体附加信息',
  `is_read` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=未读 1=已读',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`from_id`,`to_id`,`created_at`),
  KEY `idx_to_unread` (`to_id`,`is_read`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='私聊消息表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `private_messages`
--

LOCK TABLES `private_messages` WRITE;
/*!40000 ALTER TABLE `private_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `private_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `regions`
--

DROP TABLE IF EXISTS `regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `regions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned NOT NULL DEFAULT '0',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` tinyint(3) unsigned NOT NULL COMMENT '1=省 2=市 3=区',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='地区表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `regions`
--

LOCK TABLES `regions` WRITE;
/*!40000 ALTER TABLE `regions` DISABLE KEYS */;
/*!40000 ALTER TABLE `regions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT '举报人',
  `target_type` tinyint(3) unsigned NOT NULL COMMENT '1=启事 2=线索 3=用户',
  `target_id` bigint(20) unsigned NOT NULL COMMENT '被举报对象ID',
  `reason` tinyint(3) unsigned NOT NULL COMMENT '1=虚假 2=广告 3=违法 4=骚扰 5=其他',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '补充说明',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0=待处理 1=有效 2=无效 3=忽略',
  `handled_by` bigint(20) unsigned DEFAULT NULL COMMENT '处理人',
  `handle_remark` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `handled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='举报表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
INSERT INTO `reports` VALUES (1,13,1,6,3,'I',2,1,'','2026-02-26 15:38:33','2026-02-15 11:27:21'),(2,13,1,1,2,'',2,1,'','2026-02-26 15:38:30','2026-02-15 11:28:06'),(3,16,1,8,5,'',2,1,'','2026-02-26 15:38:24','2026-02-16 12:53:41'),(4,16,1,1,4,'',2,1,'','2026-02-26 15:38:18','2026-02-16 12:55:05'),(5,2,1,8,2,'',2,1,'','2026-02-26 15:38:15','2026-02-18 23:32:20'),(6,18,1,6,3,'',2,1,'','2026-02-26 15:38:11','2026-02-20 16:11:19'),(7,2,3,10,4,'blocked_by_user',2,1,'','2026-02-26 15:38:07','2026-02-25 16:25:42'),(8,23,1,8,2,'656',2,1,'','2026-02-26 15:37:58','2026-02-26 11:09:01'),(9,23,1,12,1,'11111111111',1,1,'88888888888','2026-02-26 12:05:29','2026-02-26 11:53:36'),(10,23,1,10,1,'6666666666',1,1,'777788888','2026-02-26 12:03:20','2026-02-26 12:03:10'),(11,24,1,12,4,'88888888',1,1,'8899999','2026-02-26 15:14:49','2026-02-26 15:14:29'),(12,25,1,13,2,'8768678678',1,1,'9998889899','2026-02-26 15:53:39','2026-02-26 15:53:22'),(13,27,1,6,3,'',1,1,'已处理, 感谢您的监督','2026-03-03 14:01:02','2026-03-03 13:59:52');
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nickname` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_code` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '用户编号（对外展示）',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `account` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'æ‰‹æœºå·/é‚®ç®±ï¼ˆç¬¬ä¸‰æ–¹ç™»å½•å¯ä¸ºç©ºï¼‰',
  `account_type` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '1=手机号 2=邮箱',
  `apple_id` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Apple ç”¨æˆ·æ ‡è¯†ç¬¦',
  `auth_provider` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT 'æ³¨å†Œæ¥æº 1=æ‰‹æœº/é‚®ç®± 2=Apple',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'å¯†ç å“ˆå¸Œï¼ˆç¬¬ä¸‰æ–¹ç™»å½•å¯ä¸ºç©ºï¼‰',
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '公开联系电话',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '1=正常 2=禁言 3=封禁',
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_account` (`account`),
  UNIQUE KEY `uk_apple_id` (`apple_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'爱心志愿者4181','GHB419517E','','test@test.com',2,NULL,1,'$2y$10$o25lFZIhTXPlr5/cPqojvezdK9a.x0I3gTAyNtMe1H7YL8B3tafSC','',1,'2026-02-10 15:13:27','127.0.0.1','2026-02-10 14:28:38','2026-02-25 15:42:53',NULL),(2,'阳光市民7967','GHD3D176A3','/uploads/20260218/76f5610257ab566758791a6d03f8104b.jpg','18800000001',1,NULL,1,'$2y$10$.86IsrfJxiZ9EBysck5TgOQimPhC1u9MD64.YMhaQ3CpIZjCiM9hO','',1,'2026-03-02 18:13:51','172.71.241.52','2026-02-10 14:39:43','2026-03-02 18:13:52',NULL),(3,'善良用户1252','GH4DBD176F','','test2@test.com',2,NULL,1,'$2y$10$xWmsTjZVXJJXy/M3nERV2eF0bKXCPoSAlIj8RTQOrVKubqm486hSm','',1,'2026-02-10 14:40:32','127.0.0.1','2026-02-10 14:40:32','2026-02-25 15:42:53',NULL),(4,'爱心用户9684','GH06B83739','','test3@test.com',2,NULL,1,'$2y$10$mUUICiYkj992GqPN8JfSSe5J8cAVKs29VgciKSzOlnvzzTHufc7OK','',1,'2026-02-10 14:41:08','127.0.0.1','2026-02-10 14:41:08','2026-02-25 15:42:53',NULL),(5,'善良朋友6194','GH37E144C3','','test5@test.com',2,NULL,1,'$2y$10$l8XsX89an11fJakOGKII7OyBMP9qspIe9/w.GTNHJgIb5lKRIpTx.','',1,'2026-02-10 14:41:38','127.0.0.1','2026-02-10 14:41:39','2026-02-25 15:42:53',NULL),(6,'热心市民9356','GH70BE5D57','','18800000002',1,NULL,1,'$2y$10$mAzB.7mjZ.DWeOCbTtZkIuT4pK3UsbqixwHyRrhIXgtSEKXFnWaBe','',1,'2026-02-25 14:26:53','162.159.98.8','2026-02-10 14:44:11','2026-02-25 15:42:53',NULL),(7,'阳光市民8150','GH0947449D','','18800000009',1,NULL,1,'$2y$10$Ea/3SUx77ACSkJShrDaYXuWTJrHV5CcMs4jmErgjPdaiWG0XoM1pO','',1,'2026-02-11 23:13:30','162.158.163.220','2026-02-11 23:13:31','2026-02-25 15:42:53',NULL),(8,'阳光市民5436','GH48513D30','','18800000008',1,NULL,1,'$2y$10$qT9BwaTAhSBKZRl/79Rap.QN8xwulQ.gx3Rw4E97cYl01LT9kVILS','',1,'2026-02-11 23:21:36','162.158.108.43','2026-02-11 23:15:24','2026-02-25 15:42:53',NULL),(9,'阳光志愿者8365','GH0B4591B3','','18800000007',1,NULL,1,'$2y$10$92EFG6yiQJYKULyYX6P6ieYXMnBFDnJnYOugJGIwN1mnnpZixHFae','',1,'2026-02-11 23:28:28','172.69.176.42','2026-02-11 23:28:28','2026-02-25 15:42:53',NULL),(10,'善良市民6692','GH9EADF639','','guest_260212_15c0da96',3,NULL,3,'$2y$10$3sXwvWRKr74Dc4RiVedLIuaDoF3rA58Q3wADba.cTMBAKXwWoxmVq','',1,'2026-02-12 13:54:53','162.158.88.132','2026-02-12 13:54:54','2026-02-25 15:42:53',NULL),(11,'阳光市民8854','GHBA88BB40','','guest_260212_8f8f8c62',3,NULL,3,'$2y$10$yTRIzT/PAMsKhxAA0JrA7.IJlQ3ie4cq8/733Of6fkLBd8k52cuji','',1,'2026-02-12 14:29:27','104.23.251.109','2026-02-12 14:29:28','2026-02-25 15:42:53',NULL),(12,'善良伙伴7402','GH59F36B9C','','guest_260212_b4e3ea5e',3,NULL,3,'$2y$10$2V.Npr1ttlRNTmQ91Z7OEOjZIEiG.GjokkM6PVg32xE6p/rZGU162','',1,'2026-02-12 14:59:44','162.158.171.12','2026-02-12 14:59:45','2026-02-25 15:42:53',NULL),(13,'爱心伙伴2131','GHDA7702CA','','guest_260215_ce4a95e8',3,NULL,3,'$2y$10$ju.ar.q38GxWoPqdtZbCr.tmSpI1ATlX/RdFrLvOBRnj6x2A7fupm','',1,'2026-02-15 11:26:29','104.23.195.67','2026-02-15 11:26:29','2026-02-25 15:42:53',NULL),(14,'希望朋友8559','GH8DB6AB09','','guest_260216_b6557def',3,NULL,3,'$2y$10$mqXkAHZb.O.ag6.er1eOuuleQErEORNNGoour4dqWKYyEQhAct1Bq','',1,'2026-02-16 04:45:51','172.69.74.214','2026-02-16 04:45:51','2026-02-25 15:42:53',NULL),(15,'热心市民4438','GH402212F2','','guest_260216_dec78dbb',3,NULL,3,'$2y$10$0i0grBzy87eJg0Xtwvc3iejY5J7LDXPIx9TaE5rJotVAVRB8tPGB2','',1,'2026-02-16 11:51:03','172.69.74.214','2026-02-16 11:51:03','2026-02-25 15:42:53',NULL),(16,'阳光伙伴3096','GH8736D0C9','','guest_260216_fa0d6e86',3,NULL,3,'$2y$10$f4kE.EMq9G/NsmR.2xh7GO6oBuPESinSQZw42SWHiOmMSaBBKo3/C','',1,'2026-02-16 12:52:09','172.69.74.214','2026-02-16 12:52:10','2026-02-25 15:42:53',NULL),(17,'阳光志愿者1946','GH12C523AB','','guest_260218_ed75b34d',3,NULL,3,'$2y$10$/e78GmqZUuyrzbb6vTsVb.6brOT/Q1D.HiK4/t.ZfAiDnUqooPQKi','',1,'2026-02-18 14:28:40','172.69.74.214','2026-02-18 14:28:40','2026-02-25 15:42:53',NULL),(18,'阳光朋友2750','GH7206DDE6','','guest_260220_863a2e90',3,NULL,3,'$2y$10$8NGF7MAeSVh4q97XtlgY3O4kOSVurAqZ/N.SOLEttPxP3iiXJTn.u','',1,'2026-02-20 16:07:55','104.23.195.66','2026-02-20 16:07:55','2026-02-25 15:42:53',NULL),(19,'热心市民9917','GHC031C04F','','guest_260222_d8bbab64',3,NULL,3,'$2y$10$6NGUppJx/8NbNPiw/HxafeTYoe1valLas4N5tk3YF9y6/GEPWECM2','',1,'2026-02-22 17:27:23','104.23.195.66','2026-02-22 17:27:24','2026-02-25 15:42:53',NULL),(20,'爱心伙伴3680','GH8EE0834D','','guest_260224_6d7ffad6',3,NULL,3,'$2y$10$XT2a74rJo4lp9DXqsnas6OnVkn9.e4mX3Omx4KgQRDZBbA9Acab62','',1,'2026-02-24 16:20:08','172.70.189.94','2026-02-24 16:20:08','2026-02-25 15:42:53',NULL),(21,'希望用户4231','GHE4C4FE90','','guest_260224_35ca45d5',3,NULL,3,'$2y$10$1NN6g0sFFXo9dkTJxLbjdOKPQ0kDkdCV4fwBYLWpa4tsDbx4G5qA2','',1,'2026-02-24 18:25:30','162.158.189.117','2026-02-24 18:25:30','2026-02-25 15:42:53',NULL),(22,'希望市民5034','GHXDTYFPGV','','guest_260225_05e17721',3,NULL,3,'$2y$10$WgqtDzhKdC8ebLaT8b6CS.71J.w7jF3/UdJ1oEcLJQSSUc57rWgsa','',1,'2026-02-25 13:56:52','162.159.98.9','2026-02-25 13:56:52','2026-02-25 13:56:52',NULL),(23,'热心市民6794','GH9SA9BCGE','','guest_260226_a3fdf9c6',3,NULL,3,'$2y$10$eSi/xRvPGt6TE8dp/9ZcouNCD5nxt3jLdNPoeSmCyWsSTxbn99g9a','',1,'2026-02-26 11:08:30','162.158.171.12','2026-02-26 11:08:30','2026-02-26 11:08:30',NULL),(24,'热心志愿者3025','GH3C39ASNC','','guest_260226_f1660740',3,NULL,3,'$2y$10$vlp4hggwU4jKtvH7ivg4J.8AQAD8kr0kis8.9QsGNnjIuIxlnW38u','',1,'2026-02-26 15:14:22','172.68.211.72','2026-02-26 15:14:22','2026-02-26 15:14:22',NULL),(25,'阳光市民3555','GHJ6TQUKR3','','guest_260226_f0f3af7a',3,NULL,3,'$2y$10$4Lfg4DARGchBoLVT5mtZ6ubwHBSoqBJAeyEVUljxzIN5Vuki5w2fa','',1,'2026-02-26 15:31:37','162.158.190.29','2026-02-26 15:31:38','2026-02-26 15:31:38',NULL),(26,'爱心用户1397','GHESAWPEPY','','guest_260226_f42914ce',3,NULL,3,'$2y$10$RYp.c/bsurVe2Sxjn1arxew.eRq/RlVCwJzKYjO2FCkudKlAyn6ve','',1,'2026-02-26 17:08:44','104.23.195.66','2026-02-26 17:08:44','2026-02-26 17:08:44',NULL),(27,'热心伙伴8544','GHJWQF787D','','guest_260303_e5669c75',3,NULL,3,'$2y$10$Lswop4R2I12BnjICPfL9V.zcNqBeFagi5KknnYl1LDTOnUwNNu.AO','',1,'2026-03-03 13:59:23','172.71.154.71','2026-03-03 13:59:23','2026-03-03 13:59:23',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'go_home'
--

--
-- Dumping routines for database 'go_home'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-04 16:52:28
