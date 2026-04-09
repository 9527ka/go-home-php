-- 017: Apple IAP 支持
-- 为 recharge_orders 表添加 IAP 相关字段

ALTER TABLE `recharge_orders`
  ADD COLUMN `payment_type`       TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=USDT手动 1=Apple IAP' AFTER `order_no`,
  ADD COLUMN `iap_product_id`     VARCHAR(100) NULL DEFAULT NULL COMMENT 'Apple IAP产品ID' AFTER `screenshot_url`,
  ADD COLUMN `iap_transaction_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Apple原始交易ID' AFTER `iap_product_id`,
  ADD COLUMN `iap_receipt`        TEXT          NULL COMMENT 'Apple收据数据' AFTER `iap_transaction_id`;

-- 防止同一笔 Apple 交易重复到账（幂等性保障）
-- NULL 值不受 UNIQUE 约束限制，USDT 手动充值的 iap_transaction_id 为 NULL 不会冲突
ALTER TABLE `recharge_orders`
  ADD UNIQUE KEY `uk_iap_transaction` (`iap_transaction_id`);
