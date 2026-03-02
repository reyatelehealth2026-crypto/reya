# Odoo Real-time Sync Tables Migration

## Overview
ระบบซิงค์ข้อมูลจาก Odoo webhooks แบบ real-time ไปยังตารางเฉพาะ (`odoo_orders`, `odoo_invoices`, `odoo_bdos`) เพื่อ:
- ✅ เพิ่มความเร็วในการ query (ไม่ต้อง JSON_EXTRACT จาก webhook log)
- ✅ ข้อมูลทันสมัยทันทีที่มี webhook เข้ามา
- ✅ รองรับ state updates ทุกขั้นตอน
- ✅ ง่ายต่อการ query และ join ข้อมูล

## Architecture

### Before (Old System)
```
Odoo Webhook → webhook.php → odoo_webhooks_log (JSON payload)
                                      ↓
                          API ต้อง query JSON ทุกครั้ง (ช้า)
```

### After (New System)
```
Odoo Webhook → webhook.php → odoo_webhooks_log (JSON payload)
                                      ↓
                          OdooSyncService (real-time)
                                      ↓
                    ┌─────────────────┼─────────────────┐
                    ↓                 ↓                 ↓
            odoo_orders      odoo_invoices      odoo_bdos
                    ↓                 ↓                 ↓
                  API query ตรงจากตาราง (เร็ว)
```

## Installation Steps

### 1. Run Migration SQL
```bash
mysql -u root -p zrismpsz_cny < add_odoo_sync_tables.sql
```

หรือใน phpMyAdmin:
- Import file `add_odoo_sync_tables.sql`

### 2. Verify Tables Created
```sql
SHOW TABLES LIKE 'odoo_%';
-- Should show: odoo_orders, odoo_invoices, odoo_bdos
```

### 3. Backfill Historical Data
```bash
cd /path/to/re-ya/install
php backfill_odoo_sync_tables.php --batch=1000
```

Options:
- `--batch=1000` - จำนวน records ต่อ batch (default: 1000)
- `--offset=0` - เริ่มต้นที่ offset (default: 0)

### 4. Monitor Progress
Script จะแสดง:
```
=== Odoo Sync Tables Backfill ===
Batch size: 1000
Starting offset: 0

Total unsynced webhooks: 5432
Estimated batches: 6

Processing batch at offset 0...
  ✓ Processed: 1000 records
  ✓ Orders: 456, Invoices: 234, BDOs: 123
  ✗ Errors: 0
  Progress: 18.41%
...
```

### 5. Verify Data
```sql
-- Check counts
SELECT COUNT(*) FROM odoo_orders;
SELECT COUNT(*) FROM odoo_invoices;
SELECT COUNT(*) FROM odoo_bdos;

-- Sample data
SELECT * FROM odoo_orders ORDER BY updated_at DESC LIMIT 10;
SELECT * FROM odoo_invoices WHERE is_overdue = TRUE LIMIT 10;
SELECT * FROM odoo_bdos ORDER BY bdo_date DESC LIMIT 10;
```

## How It Works

### Real-time Sync
1. Odoo ส่ง webhook → `webhook.php`
2. `OdooWebhookHandler::processWebhook()` ประมวลผล
3. `OdooWebhookHandler::syncToOdooTables()` เรียก `OdooSyncService`
4. `OdooSyncService::syncWebhook()` บันทึกลงตารางที่เหมาะสม:
   - `order.*`, `sale.*` → `odoo_orders`
   - `invoice.*` → `odoo_invoices`
   - `bdo.*` → `odoo_bdos`
5. Mark `synced_to_tables = TRUE` ใน webhook log

### Event Mapping

**Orders** (`odoo_orders`):
- `order.confirmed`, `order.paid`, `order.delivered`
- `order.picker_assigned`, `order.picking`, `order.picked`
- `order.packing`, `order.packed`, `order.reserved`
- `order.awaiting_payment`, `order.to_delivery`, `order.in_delivery`
- `sale.order.confirmed`, `sale.order.done`
- `delivery.completed`, `delivery.departed`
- `payment.confirmed`, `payment.received`

**Invoices** (`odoo_invoices`):
- `invoice.posted`, `invoice.paid`, `invoice.created`, `invoice.overdue`

**BDOs** (`odoo_bdos`):
- `bdo.confirmed`, `bdo.updated`, `bdo.done`, `bdo.cancelled`

## Database Schema

### odoo_orders
Key fields:
- `order_id` (UNIQUE) - Odoo sale.order ID
- `order_name` - SO number
- `state`, `state_display` - Current state
- `payment_status`, `delivery_status` - Derived from events
- `is_paid`, `is_delivered` - Boolean flags
- `amount_total`, `date_order`, `payment_date`
- `partner_id`, `customer_ref`, `line_user_id`

### odoo_invoices
Key fields:
- `invoice_id` (UNIQUE) - Odoo account.move ID
- `invoice_number` - Invoice number
- `order_id`, `order_name` - Related order
- `state`, `payment_state` - Invoice state
- `amount_total`, `amount_residual` - Total & remaining
- `invoice_date`, `due_date`, `payment_date`
- `is_paid`, `is_overdue` - Boolean flags
- `payment_method`, `payment_term`

### odoo_bdos
Key fields:
- `bdo_id` (UNIQUE) - Odoo BDO ID
- `bdo_name` - BDO number
- `order_id`, `order_name` - Related order
- `state`, `amount_total`
- `bdo_date`, `expected_delivery`

## API Updates

APIs ที่ได้รับการอัพเดทให้ query จากตารางใหม่:
- ✅ `api/odoo-webhooks-dashboard.php` → `getOdooOrders()`, `getOdooInvoices()`, `getOdooBdos()`
- ✅ `api/slip-match-orders.php` → `search_orders` action
- ✅ `odoo-dashboard.js` → `showCustomerDetail()`, `openMultiOrderMatch()`

## Performance Benefits

### Before
```sql
-- Slow: JSON extraction on every query
SELECT 
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.order_name')) as order_name,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.amount_total')) as amount
FROM odoo_webhooks_log
WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.customer.partner_id')) = '12345'
  AND event_type LIKE 'order.%'
GROUP BY order_id;
```

### After
```sql
-- Fast: Direct column query with indexes
SELECT order_name, amount_total, state, payment_status
FROM odoo_orders
WHERE partner_id = 12345
ORDER BY date_order DESC;
```

**Speed improvement: ~10-50x faster** depending on data size

## Maintenance

### Re-sync Specific Records
```php
require_once 'classes/OdooSyncService.php';
$syncService = new OdooSyncService($db);

// Re-sync from webhook log
$stmt = $db->prepare("SELECT payload, event_type, id FROM odoo_webhooks_log WHERE id = ?");
$stmt->execute([12345]);
$wh = $stmt->fetch(PDO::FETCH_ASSOC);
$payload = json_decode($wh['payload'], true);
$syncService->syncWebhook($payload, $wh['event_type'], $wh['id']);
```

### Monitor Sync Status
```sql
-- Check sync coverage
SELECT 
  COUNT(*) as total,
  SUM(synced_to_tables) as synced,
  SUM(NOT synced_to_tables) as pending
FROM odoo_webhooks_log
WHERE event_type IN ('order.confirmed', 'invoice.posted', 'bdo.confirmed');
```

### Indexes
All tables have optimized indexes for:
- Partner ID, Customer Ref, LINE User ID
- State, Payment Status, Delivery Status
- Dates (order_date, due_date, bdo_date)
- Updated timestamp

## Troubleshooting

### Issue: Backfill stuck or slow
**Solution**: Reduce batch size
```bash
php backfill_odoo_sync_tables.php --batch=500
```

### Issue: Duplicate key errors
**Solution**: Tables use `UNIQUE` constraints - duplicates are automatically updated (UPSERT)

### Issue: Missing data in new tables
**Solution**: Check if webhook was marked as synced
```sql
SELECT * FROM odoo_webhooks_log 
WHERE synced_to_tables = FALSE 
  AND event_type LIKE 'order.%'
LIMIT 10;
```

Then re-run backfill for those specific records.

## Rollback (if needed)

```sql
-- Drop new tables
DROP TABLE IF EXISTS odoo_orders;
DROP TABLE IF EXISTS odoo_invoices;
DROP TABLE IF EXISTS odoo_bdos;

-- Remove sync flag column
ALTER TABLE odoo_webhooks_log DROP COLUMN IF EXISTS synced_to_tables;
```

## Next Steps

1. ✅ Run migration SQL
2. ✅ Run backfill script
3. ✅ Verify data in new tables
4. ✅ Test APIs (orders, invoices, BDOs should load faster)
5. ✅ Monitor webhook processing (new webhooks auto-sync)
6. 🎯 Enjoy faster queries and real-time data!
