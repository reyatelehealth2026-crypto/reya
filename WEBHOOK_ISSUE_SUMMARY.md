# สรุปปัญหา Webhook วันที่ 16 ก.พ. 2026

## ข้อมูลที่พบ

### จาก Error Log
```
[16-Feb-2026 12:30:36] Webhook routing: order.to_delivery -> handleOrderToDelivery()
[16-Feb-2026 12:31:19] Webhook used legacy signature format (timestamp.payload). Please update Odoo module.
[16-Feb-2026 12:31:19] Webhook routing: invoice.created -> handleInvoiceCreated()
```

### จากฐานข้อมูล
| วัน | จำนวน webhooks | Success | Failed | Event Types | Orders |
|-----|----------------|---------|--------|-------------|--------|
| 15 ก.พ. | 33 | 30 | 3 | 12 | - |
| 14 ก.พ. | 918 | 918 | 0 | 15 | 234 |
| 13 ก.พ. | 944 | 944 | 0 | 13 | 268 |
| **16 ก.พ.** | **0** | **0** | **0** | **0** | **0** |

## ปัญหา

**Webhooks เข้ามาแล้วตาม error log แต่ไม่ได้บันทึกลงฐานข้อมูล**

## สาเหตุที่เป็นไปได้

### 1. Database Connection Issue
- Connection timeout หรือ connection pool เต็ม
- Database server restart ในช่วงเวลานั้น
- Network issue ระหว่าง web server กับ database server

### 2. Transaction Rollback
- มี error ใน webhook handler ทำให้ rollback transaction
- Exception ที่ไม่ได้ handle ทำให้ไม่ commit
- Duplicate key violation (delivery_id ซ้ำ)

### 3. Code Issue
- Bug ใน OdooWebhookHandler ที่ทำให้ไม่ INSERT
- Conditional logic ที่ skip การบันทึก
- Silent failure (catch exception แล้วไม่ log)

### 4. Server Issue
- Disk full ทำให้ INSERT ไม่ได้
- Memory limit exceeded
- PHP timeout

## การตรวจสอบเพิ่มเติม

### 1. ตรวจสอบ PHP Error Log
```bash
# ดู error log ในวันที่ 16 ก.พ.
grep "2026-02-16" /path/to/php-error.log
grep "16-Feb-2026" /path/to/php-error.log
```

### 2. ตรวจสอบ MySQL Error Log
```bash
# ดู MySQL error log
grep "2026-02-16" /var/log/mysql/error.log
```

### 3. ตรวจสอบ Webhook Handler Code
- ดูที่ `classes/OdooWebhookHandler.php`
- ตรวจสอบ method `logWebhook()` หรือ `insertWebhookLog()`
- ดู try-catch blocks ว่ามี silent failure หรือไม่

### 4. ตรวจสอบ Duplicate Delivery IDs
```sql
-- ดูว่ามี delivery_id ซ้ำหรือไม่
SELECT delivery_id, COUNT(*) as count
FROM odoo_webhooks_log
WHERE DATE(processed_at) = '2026-02-15'
GROUP BY delivery_id
HAVING count > 1;
```

## แนวทางแก้ไข

### ระยะสั้น (Immediate Fix)
1. เพิ่ม error logging ใน webhook handler
2. เพิ่ม try-catch ที่ครอบคลุมกว่าเดิม
3. Log ทุก exception ที่เกิดขึ้น

### ระยะยาว (Long-term Solution)
1. ใช้ queue system (Redis/RabbitMQ) สำหรับ webhook processing
2. เพิ่ม retry mechanism
3. เพิ่ม monitoring และ alerting
4. ใช้ Dead Letter Queue (DLQ) สำหรับ failed webhooks

## คำแนะนำ

1. **ตรวจสอบ error logs ทันที** - ดู PHP และ MySQL error logs ในวันที่ 16 ก.พ.
2. **ทดสอบ webhook handler** - ส่ง test webhook เพื่อดูว่าบันทึกได้หรือไม่
3. **เพิ่ม logging** - เพิ่ม detailed logging ใน OdooWebhookHandler
4. **Setup monitoring** - ติดตั้ง monitoring เพื่อ alert เมื่อ webhook ไม่ถูกบันทึก

## ข้อมูลเพิ่มเติม

- Webhook endpoint: `/api/webhook/odoo-webhook.php` (สันนิษฐาน)
- Handler class: `OdooWebhookHandler`
- Database table: `odoo_webhooks_log`
- Unique constraint: `delivery_id`
