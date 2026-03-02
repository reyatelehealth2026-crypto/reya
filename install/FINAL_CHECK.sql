-- ============================================================================
-- FINAL CHECK - Run this to see current state
-- ============================================================================

-- 1. Check if tables exist
SELECT 'Tables that exist:' as info;
SHOW TABLES LIKE 'odoo_%';

-- 2. Check odoo_orders structure
SELECT 'odoo_orders columns:' as info;
SHOW COLUMNS FROM odoo_orders;

-- 3. Check odoo_invoices structure  
SELECT 'odoo_invoices columns:' as info;
SHOW COLUMNS FROM odoo_invoices;

-- 4. Check odoo_bdos structure
SELECT 'odoo_bdos columns:' as info;
SHOW COLUMNS FROM odoo_bdos;

-- 5. Check row counts
SELECT 'Row counts:' as info;
SELECT 
    'odoo_orders' as table_name, 
    COUNT(*) as row_count 
FROM odoo_orders
UNION ALL
SELECT 
    'odoo_invoices' as table_name, 
    COUNT(*) as row_count 
FROM odoo_invoices
UNION ALL
SELECT 
    'odoo_bdos' as table_name, 
    COUNT(*) as row_count 
FROM odoo_bdos;

-- 6. Check if synced_to_tables column exists
SELECT 'Webhook log structure:' as info;
SHOW COLUMNS FROM odoo_webhooks_log LIKE 'synced_to_tables';
