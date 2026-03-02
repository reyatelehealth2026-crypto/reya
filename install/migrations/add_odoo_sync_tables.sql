-- Migration: Add Odoo Real-time Sync Tables
-- Purpose: Store orders, invoices, and BDOs from Odoo webhooks for fast querying
-- Created: 2026-03-02

-- ============================================================================
-- 1. Orders Table (from order.* and sale.* webhooks)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `odoo_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL COMMENT 'Odoo sale.order ID',
  `order_name` VARCHAR(100) NOT NULL COMMENT 'SO number e.g. SO2602-05352',
  `partner_id` INT UNSIGNED DEFAULT NULL COMMENT 'Odoo partner ID',
  `customer_ref` VARCHAR(50) DEFAULT NULL COMMENT 'Customer reference code',
  `line_user_id` VARCHAR(100) DEFAULT NULL COMMENT 'LINE user ID',
  `salesperson_id` INT UNSIGNED DEFAULT NULL,
  `salesperson_name` VARCHAR(200) DEFAULT NULL,
  
  -- Order details
  `state` VARCHAR(50) DEFAULT NULL COMMENT 'Current state: draft, sale, done, cancel, etc.',
  `state_display` VARCHAR(100) DEFAULT NULL COMMENT 'Thai display name',
  `amount_total` DECIMAL(14,2) DEFAULT 0.00,
  `amount_tax` DECIMAL(14,2) DEFAULT 0.00,
  `amount_untaxed` DECIMAL(14,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'THB',
  
  -- Dates
  `date_order` DATETIME DEFAULT NULL COMMENT 'Order date',
  `expected_delivery` DATE DEFAULT NULL,
  `payment_date` DATETIME DEFAULT NULL,
  
  -- Payment & delivery status
  `payment_status` VARCHAR(50) DEFAULT NULL COMMENT 'awaiting_payment, paid, etc.',
  `delivery_status` VARCHAR(50) DEFAULT NULL COMMENT 'to_delivery, in_delivery, delivered',
  `is_paid` BOOLEAN DEFAULT FALSE,
  `is_delivered` BOOLEAN DEFAULT FALSE,
  
  -- Tracking
  `items_count` INT DEFAULT 0,
  `latest_event` VARCHAR(100) DEFAULT NULL COMMENT 'Last webhook event type',
  `webhook_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Latest webhook log ID',
  
  -- Timestamps
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Last webhook sync time',
  
  -- Indexes
  UNIQUE KEY `uk_order_id` (`order_id`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_customer_ref` (`customer_ref`),
  KEY `idx_line_user` (`line_user_id`),
  KEY `idx_state` (`state`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_date_order` (`date_order`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Odoo orders synced from webhooks in real-time';

-- ============================================================================
-- 2. Invoices Table (from invoice.* webhooks)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `odoo_invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT UNSIGNED NOT NULL COMMENT 'Odoo account.move ID',
  `invoice_number` VARCHAR(100) NOT NULL COMMENT 'Invoice number e.g. HS26025367',
  `order_id` INT UNSIGNED DEFAULT NULL COMMENT 'Related sale order ID',
  `order_name` VARCHAR(100) DEFAULT NULL COMMENT 'Related SO number',
  `partner_id` INT UNSIGNED DEFAULT NULL,
  `customer_ref` VARCHAR(50) DEFAULT NULL,
  `line_user_id` VARCHAR(100) DEFAULT NULL,
  `salesperson_id` INT UNSIGNED DEFAULT NULL,
  `salesperson_name` VARCHAR(200) DEFAULT NULL,
  
  -- Invoice details
  `state` VARCHAR(50) DEFAULT NULL COMMENT 'draft, posted, paid, cancel',
  `invoice_state` VARCHAR(50) DEFAULT NULL COMMENT 'Alias for state',
  `payment_state` VARCHAR(50) DEFAULT NULL COMMENT 'not_paid, in_payment, paid, partial',
  `amount_total` DECIMAL(14,2) DEFAULT 0.00,
  `amount_tax` DECIMAL(14,2) DEFAULT 0.00,
  `amount_untaxed` DECIMAL(14,2) DEFAULT 0.00,
  `amount_residual` DECIMAL(14,2) DEFAULT 0.00 COMMENT 'Remaining amount to pay',
  `currency` VARCHAR(10) DEFAULT 'THB',
  
  -- Dates
  `invoice_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `payment_date` DATETIME DEFAULT NULL,
  
  -- Payment details
  `payment_term` VARCHAR(100) DEFAULT NULL COMMENT 'e.g. โอนก่อนส่ง',
  `payment_method` VARCHAR(50) DEFAULT NULL COMMENT 'cash, bank_transfer, promptpay, etc.',
  `is_paid` BOOLEAN DEFAULT FALSE,
  `is_overdue` BOOLEAN DEFAULT FALSE,
  
  -- PDF & tracking
  `pdf_url` VARCHAR(500) DEFAULT NULL,
  `latest_event` VARCHAR(100) DEFAULT NULL,
  `webhook_id` BIGINT UNSIGNED DEFAULT NULL,
  
  -- Timestamps
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Indexes
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Odoo invoices synced from webhooks in real-time';

-- ============================================================================
-- 3. BDOs Table (from bdo.* webhooks - Back Delivery Orders)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `odoo_bdos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `bdo_id` INT UNSIGNED NOT NULL COMMENT 'Odoo BDO ID',
  `bdo_name` VARCHAR(100) NOT NULL COMMENT 'BDO number e.g. BDO2602-02561',
  `order_id` INT UNSIGNED DEFAULT NULL COMMENT 'Related sale order ID',
  `order_name` VARCHAR(100) DEFAULT NULL COMMENT 'Related SO number',
  `partner_id` INT UNSIGNED DEFAULT NULL,
  `customer_ref` VARCHAR(50) DEFAULT NULL,
  `line_user_id` VARCHAR(100) DEFAULT NULL,
  `salesperson_id` INT UNSIGNED DEFAULT NULL,
  `salesperson_name` VARCHAR(200) DEFAULT NULL,
  
  -- BDO details
  `state` VARCHAR(50) DEFAULT 'confirmed' COMMENT 'BDO state',
  `amount_total` DECIMAL(14,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'THB',
  
  -- Dates
  `bdo_date` DATE DEFAULT NULL COMMENT 'BDO issue date',
  `expected_delivery` DATE DEFAULT NULL,
  
  -- Tracking
  `latest_event` VARCHAR(100) DEFAULT NULL,
  `webhook_id` BIGINT UNSIGNED DEFAULT NULL,
  
  -- Timestamps
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Indexes
  UNIQUE KEY `uk_bdo_id` (`bdo_id`),
  KEY `idx_bdo_name` (`bdo_name`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_customer_ref` (`customer_ref`),
  KEY `idx_line_user` (`line_user_id`),
  KEY `idx_state` (`state`),
  KEY `idx_bdo_date` (`bdo_date`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Odoo BDOs (Back Delivery Orders) synced from webhooks in real-time';

-- ============================================================================
-- 4. Add webhook_synced flag to odoo_webhooks_log
-- ============================================================================
ALTER TABLE `odoo_webhooks_log` 
ADD COLUMN IF NOT EXISTS `synced_to_tables` BOOLEAN DEFAULT FALSE COMMENT 'Whether this webhook has been synced to dedicated tables',
ADD INDEX IF NOT EXISTS `idx_synced` (`synced_to_tables`, `processed_at`);
