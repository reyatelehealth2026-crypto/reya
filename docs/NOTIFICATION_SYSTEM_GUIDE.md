# Odoo Notification System - Complete Guide

## 📋 Overview

ระบบแจ้งเตือน Odoo webhook แบบครบวงจร พร้อมฟีเจอร์:
- ✅ Roadmap batching - รวมหลายสถานะเป็น timeline เดียว
- ✅ User preferences - ผู้ใช้ปรับแต่งได้เอง
- ✅ Queue & retry - ส่งซ้ำอัตโนมัติเมื่อล้มเหลว
- ✅ Quiet hours - ไม่รบกวนในช่วงเวลาที่กำหนด
- ✅ Complete logging - บันทึกครบถ้วนสำหรับ audit

## 🚀 Quick Start

### 1. Run Database Migration

```bash
# เข้าผ่าน browser
https://your-domain.com/install/run_odoo_notification_system_migration.php
```

หรือ upload ไฟล์ไปยัง server แล้วเข้าผ่าน web browser

### 2. Seed Templates

```bash
https://your-domain.com/install/seed_notification_templates.php
```

### 3. Start Worker

```bash
# Add to crontab
* * * * * cd /path/to/re-ya && php worker/notification-worker.php >> logs/notification-worker.log 2>&1 &
```

### 4. Test System

```bash
https://your-domain.com/tests/test-notification-system.php
```

## 📊 Database Schema

### Tables Created

1. **odoo_notification_preferences** - การตั้งค่าของผู้ใช้
2. **odoo_notification_queue** - Queue สำหรับส่งแจ้งเตือน
3. **odoo_notification_log** - บันทึกการส่งทั้งหมด
4. **odoo_notification_batch_groups** - จัดกลุ่ม events สำหรับ roadmap
5. **odoo_notification_templates** - จัดการ templates

## 🎯 How It Works

### Notification Flow

```
Odoo Webhook
    ↓
OdooWebhookHandler::processWebhook()
    ↓
NotificationRouter::route()
    ↓
Check Preferences (enabled? quiet hours?)
    ↓
Should Batch? → NotificationBatcher
    ↓
Milestone Reached? → Create Roadmap
    ↓
NotificationQueue::enqueue()
    ↓
Worker picks up & sends
    ↓
NotificationLogger::log()
```

### Roadmap Batching

1. **Event arrives** (e.g., order.picking)
2. **Check if should batch** (batch_enabled = true)
3. **Add to batch group** for that order
4. **Wait for milestone** (order.packed)
5. **When milestone reached**:
   - Collect all events in batch
   - Build roadmap Flex message
   - Send combined notification
6. **Mark batch as sent**

### Example Timeline

```
16:04 - order.picker_assigned  ─┐
16:06 - order.picking           │
16:17 - order.picked            ├─ Batched
16:17 - order.packing           │
17:17 - order.packed            ─┘ → Send Roadmap!

User receives 1 message showing all 5 events
```

## ⚙️ Configuration

### Default Preferences

**Customer (ลูกค้า)**:
- ✅ Enabled: order.validated, order.packed, order.delivered, bdo.confirmed
- 📦 Batched: order.picker_assigned → order.packed
- 🎯 Milestone: order.packed (ส่ง roadmap เมื่อถึงสถานะนี้)

**Salesperson (เซลล์)**:
- ✅ Enabled: order.validated, order.paid, order.delivered, bdo.confirmed
- ❌ No batching (ส่งทันที)

### Quiet Hours

Default: 22:00 - 08:00
- Action: queue (เก็บไว้ส่งตอนเช้า)
- Options: skip, queue, silent

### Batch Window

Default: 300 seconds (5 minutes)
- Events ที่เกิดภายใน 5 นาทีจะถูกรวมกัน
- ถ้าถึง milestone ก่อน 5 นาที จะส่งทันที

## 🔧 API Endpoints

### Get Preferences

```bash
GET /api/notification-preferences.php?line_user_id=U123456

Response:
{
  "success": true,
  "line_user_id": "U123456",
  "preferences": [
    {
      "event_type": "order.packed",
      "enabled": true,
      "batch_enabled": true,
      "notification_method": "flex",
      "priority": "medium"
    }
  ]
}
```

### Update Preferences

```bash
POST /api/notification-preferences.php
Content-Type: application/json

{
  "line_user_id": "U123456",
  "preferences": [
    {
      "event_type": "order.packed",
      "enabled": true,
      "batch_enabled": true
    }
  ]
}
```

### Queue Status

```bash
GET /api/notification-queue-status.php

Response:
{
  "success": true,
  "queue": {
    "pending": 5,
    "failed": 0
  },
  "batches": [
    {"status": "collecting", "count": 3}
  ],
  "health": {
    "status": "ok"
  }
}
```

## 🎨 LIFF Interface

### Notification Settings Page

URL: `/liff/notification-settings.php`

Features:
- ✅ Toggle notifications per event type
- ✅ View batch/milestone badges
- ✅ Set quiet hours
- ✅ Reset to defaults

## 📈 Monitoring

### Check Queue

```sql
SELECT status, COUNT(*) 
FROM odoo_notification_queue 
GROUP BY status;
```

### Check Batch Groups

```sql
SELECT status, COUNT(*) 
FROM odoo_notification_batch_groups 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY status;
```

### Check Success Rate

```sql
SELECT 
  status,
  COUNT(*) as count,
  ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM odoo_notification_log
WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY status;
```

### View Recent Failures

```sql
SELECT 
  line_user_id,
  event_type,
  error_message,
  sent_at
FROM odoo_notification_log
WHERE status = 'failed'
  AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY sent_at DESC
LIMIT 20;
```

## 🐛 Troubleshooting

### Notifications Not Sending

1. **Check worker is running**:
   ```bash
   ps aux | grep notification-worker
   ```

2. **Check queue**:
   ```sql
   SELECT * FROM odoo_notification_queue WHERE status = 'pending' LIMIT 10;
   ```

3. **Check logs**:
   ```bash
   tail -f logs/notification-worker.log
   ```

### Roadmap Not Batching

1. **Check preferences**:
   ```sql
   SELECT * FROM odoo_notification_preferences 
   WHERE line_user_id = 'U123456' 
   AND event_type = 'order.packed';
   ```

2. **Check batch groups**:
   ```sql
   SELECT * FROM odoo_notification_batch_groups 
   WHERE status = 'collecting' 
   ORDER BY created_at DESC;
   ```

3. **Verify milestone configuration**:
   ```sql
   SELECT event_type, batch_milestone_events 
   FROM odoo_notification_preferences 
   WHERE line_user_id = '_default_customer';
   ```

### High Failure Rate

1. **Check LINE API credentials**:
   ```sql
   SELECT id, is_active FROM line_accounts;
   ```

2. **Test LINE API manually**:
   ```bash
   curl -X POST https://api.line.me/v2/bot/message/push \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"to":"U123","messages":[{"type":"text","text":"test"}]}'
   ```

3. **Review error patterns**:
   ```sql
   SELECT error_message, COUNT(*) 
   FROM odoo_notification_log 
   WHERE status = 'failed' 
   GROUP BY error_message 
   ORDER BY COUNT(*) DESC;
   ```

## 🔐 Security

### API Authentication

Add authentication to API endpoints:

```php
// In api/notification-preferences.php
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!validateToken($token)) {
    http_response_code(401);
    exit;
}
```

### Rate Limiting

Implement rate limiting in NotificationRouter:

```php
if ($this->rateLimiter->isExceeded($lineUserId)) {
    return ['skipped' => ['rate_limit']];
}
```

## 📊 Performance

### Optimization Tips

1. **Index optimization**:
   ```sql
   SHOW INDEX FROM odoo_notification_queue;
   ```

2. **Query optimization**:
   - Use EXPLAIN on slow queries
   - Add composite indexes for common filters

3. **Worker tuning**:
   - Adjust batch size: `getPending(10)` → `getPending(20)`
   - Adjust poll interval: `sleep(5)` → `sleep(3)`

4. **Cleanup old data**:
   ```sql
   DELETE FROM odoo_notification_queue 
   WHERE status IN ('sent', 'expired') 
   AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
   ```

## 📝 Customization

### Add New Event Type

1. **Add to preferences**:
   ```sql
   INSERT INTO odoo_notification_preferences 
   (line_user_id, event_type, enabled, notification_method)
   VALUES ('_default_customer', 'order.new_status', TRUE, 'flex');
   ```

2. **Add label in LIFF**:
   ```javascript
   const eventLabels = {
     'order.new_status': { label: 'สถานะใหม่', icon: '🆕' }
   };
   ```

3. **Add handler in OdooWebhookHandler** (if needed):
   ```php
   public function handleOrderNewStatus($data, $notify, $template) {
     // Custom logic
   }
   ```

### Customize Roadmap Template

Edit `classes/RoadmapMessageBuilder.php`:

```php
private $eventLabels = [
    'order.custom' => ['icon' => '🎯', 'label' => 'Custom', 'color' => '#FF5722']
];
```

## 🎯 Best Practices

1. **Always test in staging first**
2. **Monitor queue size** - alert if > 1000
3. **Review logs daily** - check for patterns
4. **Backup database** before major changes
5. **Use transactions** for batch operations
6. **Set up monitoring alerts** for high failure rates
7. **Document custom changes** in this file

## 📚 Additional Resources

- [Worker README](../worker/README.md)
- [API Documentation](API_DOCS.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)
- [Odoo System Manual](ODOO_SYSTEM_MANUAL_TH.html)

## 🆘 Support

For issues or questions:
1. Check this documentation
2. Review logs: `logs/notification-worker.log`
3. Check database: Run diagnostic queries above
4. Test system: `/tests/test-notification-system.php`

---

**Version**: 2.0.0  
**Last Updated**: 2026-02-17  
**Status**: Production Ready ✅
