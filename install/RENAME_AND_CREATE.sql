-- ============================================================================
-- SOLUTION: Rename old tables instead of dropping them
-- ============================================================================

-- Rename old tables (this avoids FK constraint issues)
RENAME TABLE `odoo_orders` TO `odoo_orders_old`;
RENAME TABLE `odoo_invoices` TO `odoo_invoices_old`;
RENAME TABLE `odoo_bdos` TO `odoo_bdos_old`;

-- ============================================================================
-- Now create new tables with correct schema
-- ============================================================================

CREATE TABLE `odoo_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL COMMENT 'Odoo sale.order ID',
  `order_name` VARCHAR(100) NOT NULL COMMENT 'SO number',
  `partner_id` INT UNSIGNED DEFAULT NULL COMMENT 'Odoo partner ID',
  `customer_ref` VARCHAR(50) DEFAULT NULL,
  `line_user_id` VARCHAR(100) DEFAULT NULL,
  `salesperson_id` INT UNSIGNED DEFAULT NULL,
  `salesperson_name` VARCHAR(200) DEFAULT NULL,
  `state` VARCHAR(50) DEFAULT NULL,
  `state_display` VARCHAR(100) DEFAULT NULL,
  `amount_total` DECIMAL(14,2) DEFAULT 0.00,
  `amount_tax` DECIMAL(14,2) DEFAULT 0.00,
  `amount_untaxed` DECIMAL(14,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'THB',
  `date_order` DATETIME DEFAULT NULL,
  `expected_delivery` DATE DEFAULT NULL,
  `payment_date` DATETIME DEFAULT NULL,
  `payment_status` VARCHAR(50) DEFAULT NULL,
  `delivery_status` VARCHAR(50) DEFAULT NULL,
  `is_paid` BOOLEAN DEFAULT FALSE,
  `is_delivered` BOOLEAN DEFAULT FALSE,
  `items_count` INT DEFAULT 0,
  `latest_event` VARCHAR(100) DEFAULT NULL,
  `webhook_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_order_id` (`order_id`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_customer_ref` (`customer_ref`),
  KEY `idx_line_user` (`line_user_id`),
  KEY `idx_state` (`state`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_date_order` (`date_order`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `odoo_invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT UNSIGNED NOT NULL COMMENT 'Odoo account.move ID',
  `invoice_number` VARCHAR(100) NOT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `order_name` VARCHAR(100) DEFAULT NULL,
  `partner_id` INT UNSIGNED DEFAULT NULL,
  `customer_ref` VARCHAR(50) DEFAULT NULL,
  `line_user_id` VARCHAR(100) DEFAULT NULL,
  `salesperson_id` INT UNSIGNED DEFAULT NULL,
  `salesperson_name` VARCHAR(200) DEFAULT NULL,
  `state` VARCHAR(50) DEFAULT NULL,
  `invoice_state` VARCHAR(50) DEFAULT NULL,
  `payment_state` VARCHAR(50) DEFAULT NULL,
  `amount_total` DECIMAL(14,2) DEFAULT 0.00,
  `amount_tax` DECIMAL(14,2) DEFAULT 0.00,
  `amount_untaxed` DECIMAL(14,2) DEFAULT 0.00,
  `amount_residual` DECIMAL(14,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'THB',
  `invoice_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `payment_date` DATETIME DEFAULT NULL,
  `payment_term` VARCHAR(100) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `is_paid` BOOLEAN DEFAULT FALSE,
  `is_overdue` BOOLEAN DEFAULT FALSE,
  `pdf_url` VARCHAR(500) DEFAULT NULL,
  `latest_event` VARCHAR(100) DEFAULT NULL,
  `webhook_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_invoice_id` (`invoice_id`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_customer_ref` (`customer_ref`),
  KEY `idx_line_user` (`line_user_id`),
  KEY `idx_state` (`state`),
  KEY `idx_payment_state` (`payment_state`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_is_overdue` (`is_overdue`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `odoo_bdos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `bdo_id` INT UNSIGNED NOT NULL COMMENT 'Odoo BDO ID',
  `bdo_name` VARCHAR(100) NOT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `order_name` VARCHAR(100) DEFAULT NULL,
  `partner_id` INT UNSIGNED DEFAULT NULL,
  `customer_ref` VARCHAR(50) DEFAULT NULL,
  `line_user_id` VARCHAR(100) DEFAULT NULL,
  `salesperson_id` INT UNSIGNED DEFAULT NULL,
  `salesperson_name` VARCHAR(200) DEFAULT NULL,
  `state` VARCHAR(50) DEFAULT 'confirmed',
  `amount_total` DECIMAL(14,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'THB',
  `bdo_date` DATE DEFAULT NULL,
  `expected_delivery` DATE DEFAULT NULL,
  `latest_event` VARCHAR(100) DEFAULT NULL,
  `webhook_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_bdo_id` (`bdo_id`),
  KEY `idx_bdo_name` (`bdo_name`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_customer_ref` (`customer_ref`),
  KEY `idx_line_user` (`line_user_id`),
  KEY `idx_state` (`state`),
  KEY `idx_bdo_date` (`bdo_date`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Add sync flag to webhook log
-- ============================================================================
ALTER TABLE `odoo_webhooks_log` 
ADD COLUMN IF NOT EXISTS `synced_to_tables` BOOLEAN DEFAULT FALSE;

-- ============================================================================
-- VERIFICATION
-- ============================================================================
-- Run these to verify:
-- SHOW COLUMNS FROM odoo_orders;
-- SHOW COLUMNS FROM odoo_invoices;
-- SHOW COLUMNS FROM odoo_bdos;

-- ============================================================================
-- CLEANUP (Optional - run AFTER backfill succeeds)
-- ============================================================================
-- DROP TABLE IF EXISTS odoo_orders_old;
-- DROP TABLE IF EXISTS odoo_invoices_old;
-- DROP TABLE IF EXISTS odoo_bdos_old;
