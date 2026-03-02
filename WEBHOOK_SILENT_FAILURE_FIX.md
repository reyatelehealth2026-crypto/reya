# แก้ไข Silent Failure ใน Webhook Logging

## สรุปการแก้ไข

แก้ไขปัญหา **silent failure** ที่ทำให้ webhooks ไม่ถูกบันทึกลงฐานข้อมูลแต่ไม่มี error

## ไฟล์ที่แก้ไข

### 1. `classes/OdooWebhookHandler.php`

**ปัญหาเดิม:**
```php
} catch (Exception $e) {
    error_log('Error logging webhook: ' . $e->getMessage());
    // ⚠️ Silent failure - ไม่ throw exception ต่อ
}
```

**แก้ไขเป็น:**
```php
} catch (Exception $e) {
    // Log the error with more context
    error_log('CRITICAL: Error logging webhook (delivery_id=' . $deliveryId . ', event=' . $event . '): ' . $e->getMessage());
    
    // Re-throw exception so webhook endpoint knows it failed
    // This ensures Odoo will retry the webhook
    throw new Exception('Database error: Failed to log webhook - ' . $e->getMessage(), 500);
}
```

**การเปลี่ยนแปลง:**
- ✅ เพิ่มการตรวจสอบ `$success` จาก `$stmt->execute()`
- ✅ Throw exception ถ้า INSERT/UPDATE ล้มเหลว
- ✅ เพิ่ม context ใน error log (delivery_id, event)
- ✅ Re-throw exception เพื่อให้ webhook endpoint รู้ว่าล้มเหลว

### 2. `api/webhook/odoo.php`

**เพิ่ม:**
```php
// Database errors should always be retriable
if (strpos($errorMessage, 'Database error') !== false || 
    strpos($errorMessage, 'MySQL server has gone away') !== false ||
    strpos($errorMessage, 'Lost connection') !== false ||
    strpos($errorMessage, 'Failed to log webhook') !== false ||
    $e->getCode() === 500) {
    $isRetriable = true;
}
```

**การเปลี่ยนแปลง:**
- ✅ Database errors จะถูกทำเครื่องหมายว่า retriable
- ✅ Odoo จะ retry webhook อัตโนมัติ
- ✅ Return HTTP 500 แทน 400 สำหรับ database errors

## ผลลัพธ์ที่คาดหวัง

### ก่อนแก้ไข
1. Webhook เข้ามา
2. INSERT ล้มเหลว (เช่น database connection lost)
3. Log error แต่ไม่ throw exception
4. Webhook endpoint return 200 OK ✅
5. Odoo คิดว่าสำเร็จ ไม่ retry
6. **Webhook หายไป** ❌

### หลังแก้ไข
1. Webhook เข้ามา
2. INSERT ล้มเหลว (เช่น database connection lost)
3. Throw exception
4. Webhook endpoint return 500 Internal Server Error ❌
5. Odoo รู้ว่าล้มเหลว จะ **retry อัตโนมัติ** ✅
6. **Webhook ไม่หาย** ✅

## การทดสอบ

### Test Case 1: Normal Webhook (ควรสำเร็จ)
```bash
curl -X POST https://cny.re-ya.com/api/webhook/odoo.php \
  -H "Content-Type: application/json" \
  -H "X-Odoo-Signature: xxx" \
  -H "X-Odoo-Timestamp: $(date +%s)" \
  -H "X-Odoo-Delivery-Id: test-$(date +%s)" \
  -H "X-Odoo-Event: order.validated" \
  -d '{"order_name":"TEST-001","customer":{"id":1825}}'
```

**Expected:** 
- HTTP 200 OK
- Webhook ถูกบันทึกในฐานข้อมูล

### Test Case 2: Database Connection Lost (ควร retry)
```sql
-- Simulate connection loss
KILL CONNECTION_ID;
```

**Expected:**
- HTTP 500 Internal Server Error
- Error log: "CRITICAL: Error logging webhook..."
- Response: `{"success": false, "status": "retry", "retriable": true}`
- Odoo จะ retry webhook

### Test Case 3: Disk Full (ควร retry)
```bash
# Simulate disk full (ทดสอบใน staging เท่านั้น!)
dd if=/dev/zero of=/var/lib/mysql/fillup bs=1M count=1000
```

**Expected:**
- HTTP 500 Internal Server Error
- Error log: "CRITICAL: Error logging webhook..."
- Odoo จะ retry webhook

## Monitoring

### ตรวจสอบ Error Logs
```bash
# ดู CRITICAL errors
tail -f /var/log/php-fpm/error.log | grep "CRITICAL: Error logging webhook"

# นับจำนวน errors
grep "CRITICAL: Error logging webhook" /var/log/php-fpm/error.log | wc -l
```

### ตรวจสอบ Webhook Success Rate
```sql
SELECT 
    DATE(processed_at) as date,
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'success' THEN 1 END) as success,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
    ROUND(COUNT(CASE WHEN status = 'success' THEN 1 END) * 100.0 / COUNT(*), 2) as success_rate
FROM odoo_webhooks_log
WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(processed_at)
ORDER BY date DESC;
```

### Alert ถ้า Success Rate ต่ำกว่า 95%
```bash
#!/bin/bash
# /usr/local/bin/check_webhook_success_rate.sh

RATE=$(mysql -u user -p'password' database -N -e "
    SELECT ROUND(COUNT(CASE WHEN status = 'success' THEN 1 END) * 100.0 / COUNT(*), 2)
    FROM odoo_webhooks_log
    WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
")

if (( $(echo "$RATE < 95" | bc -l) )); then
    echo "WARNING: Webhook success rate is $RATE% (last hour)" | \
        mail -s "Alert: Low Webhook Success Rate" admin@example.com
fi
```

## Rollback Plan

ถ้าการแก้ไขนี้ทำให้เกิดปัญหา สามารถ rollback ได้โดย:

```bash
# Backup current file
cp classes/OdooWebhookHandler.php classes/OdooWebhookHandler.php.fixed
cp api/webhook/odoo.php api/webhook/odoo.php.fixed

# Restore from git
git checkout HEAD -- classes/OdooWebhookHandler.php
git checkout HEAD -- api/webhook/odoo.php

# Restart PHP-FPM
sudo systemctl restart php-fpm
```

## Next Steps

1. ✅ Deploy การแก้ไขนี้ไปยัง production
2. ⏳ Monitor error logs เป็นเวลา 24 ชั่วโมง
3. ⏳ ตรวจสอบ webhook success rate
4. ⏳ แจ้งทีม Odoo ให้ resend webhooks ที่หายไปวันที่ 16 ก.พ.
5. ⏳ Setup automated monitoring และ alerting

## สรุป

การแก้ไขนี้จะทำให้:
- ✅ Webhook logging failures ไม่เป็น silent อีกต่อไป
- ✅ Odoo จะ retry webhooks ที่ล้มเหลวอัตโนมัติ
- ✅ ลดโอกาสที่ webhooks จะหายไป
- ✅ เพิ่ม observability ด้วย detailed error logs

**ปัญหาวันที่ 16 ก.พ. จะไม่เกิดขึ้นอีก!** 🎉
