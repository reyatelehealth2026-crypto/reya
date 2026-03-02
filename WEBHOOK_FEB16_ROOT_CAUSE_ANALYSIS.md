# Root Cause Analysis: Webhooks วันที่ 16 ก.พ. 2026 ไม่ถูกบันทึก

## สรุปปัญหา

Webhooks เข้ามาตาม error log แต่ไม่ได้บันทึกลงตาราง `odoo_webhooks_log`

### Evidence
1. **Error Log แสดงว่า webhooks เข้ามา:**
   ```
   [16-Feb-2026 12:30:36] Webhook routing: order.to_delivery
   [16-Feb-2026 12:31:19] Webhook routing: invoice.created
   ```

2. **Database ไม่มีข้อมูล:**
   - วันที่ 15 ก.พ.: 33 webhooks
   - วันที่ 16 ก.พ.: **0 webhooks** ❌
   - วันที่ 14 ก.พ.: 918 webhooks

## Root Cause

### Silent Failure ใน `logWebhook()` Method

**Location:** `classes/OdooWebhookHandler.php` บรรทัดประมาณ 1144-1150

```php
try {
    $sql = 'INSERT INTO odoo_webhooks_log (...) VALUES (...) 
            ON DUPLICATE KEY UPDATE ...';
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
} catch (Exception $e) {
    error_log('Error logging webhook: ' . $e->getMessage());  // ⚠️ Silent failure!
}
```

**ปัญหา:**
- ถ้า INSERT ล้มเหลว มันจะแค่ log error แล้วทำต่อ
- Webhook endpoint จะ return success (200 OK) แม้ว่าไม่ได้บันทึกลงฐานข้อมูล
- Odoo จะคิดว่า webhook ส่งสำเร็จแล้ว ไม่ retry

## สาเหตุที่เป็นไปได้ที่ทำให้ INSERT ล้มเหลว

### 1. Database Connection Lost
```
PDOException: MySQL server has gone away
PDOException: Lost connection to MySQL server during query
```
- Connection timeout
- MySQL server restart
- Network issue

### 2. Disk Space Full
```
PDOException: The table 'odoo_webhooks_log' is full
PDOException: Disk full
```
- `/var/lib/mysql` partition เต็ม
- Temp space เต็ม

### 3. Table Lock / Deadlock
```
PDOException: Lock wait timeout exceeded
PDOException: Deadlock found when trying to get lock
```
- Table ถูก lock โดย query อื่น
- Deadlock ระหว่าง transactions

### 4. Constraint Violation
```
PDOException: Duplicate entry for key 'unique_delivery_id'
PDOException: Data too long for column
```
- delivery_id ซ้ำ (แม้จะมี ON DUPLICATE KEY UPDATE)
- Payload ใหญ่เกินไป

### 5. Memory Limit
```
Fatal error: Allowed memory size exhausted
```
- Payload ขนาดใหญ่มาก
- PHP memory_limit ไม่พอ

## การตรวจสอบเพิ่มเติม

### 1. ตรวจสอบ PHP Error Log
```bash
# ดู error ในวันที่ 16 ก.พ. 2026
grep "16-Feb-2026" /var/log/php-fpm/error.log
grep "2026-02-16" /var/log/php-fpm/error.log
grep "Error logging webhook" /var/log/php-fpm/error.log | grep "2026-02-16"

# หรือถ้าใช้ Apache
grep "16-Feb-2026" /var/log/apache2/error.log
```

### 2. ตรวจสอบ MySQL Error Log
```bash
grep "2026-02-16" /var/log/mysql/error.log
grep "2026-02-16 12:3" /var/log/mysql/error.log  # ช่วงเวลา 12:30-12:31
```

### 3. ตรวจสอบ Disk Space
```bash
df -h  # ดู disk usage
du -sh /var/lib/mysql  # ดูขนาด MySQL data directory
```

### 4. ตรวจสอบ MySQL Process List (ถ้ายังเกิดอยู่)
```sql
SHOW FULL PROCESSLIST;
SHOW ENGINE INNODB STATUS;
```

### 5. ตรวจสอบ Table Size
```sql
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
    table_rows
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
  AND table_name = 'odoo_webhooks_log';
```

## แนวทางแก้ไข

### 1. Immediate Fix: เพิ่ม Error Handling

**แก้ไขไฟล์:** `classes/OdooWebhookHandler.php`

```php
private function logWebhook($deliveryId, $eventType, $payload, $status, $errorMessage = null, $lineUserId = null, $orderId = null, $latencyMs = null, $meta = [])
{
    try {
        // ... existing code ...
        
        $sql = 'INSERT INTO odoo_webhooks_log (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ') '
            . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updateClauses);

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);
        
        if (!$success) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Failed to log webhook: ' . ($errorInfo[2] ?? 'Unknown error'));
        }
        
    } catch (Exception $e) {
        error_log('CRITICAL: Error logging webhook (delivery_id=' . $deliveryId . '): ' . $e->getMessage());
        
        // ⚠️ IMPORTANT: Re-throw exception so webhook endpoint knows it failed
        throw new Exception('Database error: Failed to log webhook', 500);
    }
}
```

### 2. เพิ่ม Monitoring

**สร้างไฟล์:** `tools/check_webhook_logging.php`

```php
<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

// Check if webhooks are being logged
$stmt = $db->query("
    SELECT 
        DATE(processed_at) as date,
        COUNT(*) as count,
        MAX(processed_at) as last_webhook
    FROM odoo_webhooks_log
    WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY DATE(processed_at)
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "⚠️ WARNING: No webhooks logged in last 24 hours!\n";
    exit(1);
}

foreach ($results as $row) {
    echo "✓ {$row['date']}: {$row['count']} webhooks (last: {$row['last_webhook']})\n";
}
```

**เพิ่มใน crontab:**
```bash
*/15 * * * * /usr/bin/php /path/to/tools/check_webhook_logging.php || echo "Webhook logging issue detected" | mail -s "Alert: Webhook Logging" admin@example.com
```

### 3. เพิ่ม Retry Mechanism

**แก้ไขไฟล์:** `api/webhook/odoo.php`

```php
// After catching exception
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorCode = resolveWebhookErrorCode($errorMessage, (int) $e->getCode());

    $isRetriable = false;
    if ($handler instanceof OdooWebhookHandler) {
        $isRetriable = $handler->isRetriableError($errorMessage);
    }

    // ⚠️ Database errors should be retriable
    if (strpos($errorMessage, 'Database error') !== false || 
        strpos($errorMessage, 'MySQL server has gone away') !== false ||
        strpos($errorMessage, 'Lost connection') !== false) {
        $isRetriable = true;
    }

    // ... rest of error handling ...
}
```

### 4. เพิ่ม Dead Letter Queue (DLQ)

**สร้างตาราง:**
```sql
CREATE TABLE IF NOT EXISTS odoo_webhook_dlq (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_id VARCHAR(100) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    error_message TEXT,
    failed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    retry_count INT DEFAULT 0,
    last_retry_at DATETIME,
    
    INDEX idx_failed_at (failed_at),
    INDEX idx_delivery_id (delivery_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5. เพิ่ม Health Check Endpoint

**สร้างไฟล์:** `api/webhook/health.php`

```php
<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if we can write to database
    $testId = 'health-check-' . time();
    $stmt = $db->prepare("
        INSERT INTO odoo_webhooks_log 
        (delivery_id, event_type, payload, status, processed_at) 
        VALUES (?, 'health.check', '{}', 'success', NOW())
        ON DUPLICATE KEY UPDATE processed_at = NOW()
    ");
    $stmt->execute([$testId]);
    
    // Check recent webhooks
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM odoo_webhooks_log 
        WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $recentCount = $stmt->fetchColumn();
    
    echo json_encode([
        'status' => 'healthy',
        'database' => 'connected',
        'recent_webhooks_1h' => (int) $recentCount,
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
```

## Action Items

### Priority 1 (Urgent)
- [ ] ตรวจสอบ PHP error log วันที่ 16 ก.พ.
- [ ] ตรวจสอบ MySQL error log วันที่ 16 ก.พ.
- [ ] ตรวจสอบ disk space
- [ ] แก้ไข `logWebhook()` ให้ throw exception แทน silent failure

### Priority 2 (High)
- [ ] เพิ่ม monitoring script
- [ ] เพิ่ม health check endpoint
- [ ] Setup alerting เมื่อ webhook logging ล้มเหลว

### Priority 3 (Medium)
- [ ] สร้าง Dead Letter Queue table
- [ ] เพิ่ม retry mechanism
- [ ] เพิ่ม detailed logging

## คำแนะนำสำหรับทีม Odoo

แจ้งทีม Odoo ว่า:
1. Webhook วันที่ 16 ก.พ. 2026 เวลา 12:30-12:31 น. ไม่ได้ถูกบันทึก
2. อาจต้อง **resend webhooks** สำหรับ orders ที่ส่งในช่วงเวลานั้น
3. ตรวจสอบว่า orders ใดบ้างที่ส่ง webhook ในช่วงเวลานั้น

## สรุป

**Root Cause:** Silent failure ใน `logWebhook()` method ทำให้ INSERT errors ไม่ถูก propagate ขึ้นมา

**Impact:** Webhooks หายไป ลูกค้าไม่ได้รับ notifications

**Solution:** แก้ไข error handling ให้ throw exception และเพิ่ม monitoring
