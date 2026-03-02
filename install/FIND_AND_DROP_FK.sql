-- ============================================================================
-- Step 1: Find all foreign keys referencing odoo_orders
-- ============================================================================
-- Run this first to see what's blocking:
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    REFERENCED_TABLE_NAME
FROM 
    information_schema.KEY_COLUMN_USAGE
WHERE 
    REFERENCED_TABLE_NAME IN ('odoo_orders', 'odoo_invoices', 'odoo_bdos')
    AND TABLE_SCHEMA = 'zrismpsz_cny';

-- ============================================================================
-- Step 2: Drop the foreign keys (REPLACE with actual constraint names from Step 1)
-- ============================================================================
-- Example (you'll need to replace these with actual names):
-- ALTER TABLE some_table DROP FOREIGN KEY fk_constraint_name;

-- ============================================================================
-- Step 3: After dropping FKs, drop and recreate tables
-- ============================================================================
-- Then run FIX_TABLES_WITH_FK.sql
