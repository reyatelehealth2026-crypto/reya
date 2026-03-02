# Odoo Webhook Signature Validation - Fix Guide

## สรุปปัญหา

จากการทดสอบ webhook ระหว่าง Odoo (CNY) และ Re-Ya พบว่า:
- ทั้ง 2 รูปแบบ signature ถูก reject: `HTTP 400 - Invalid webhook signature`
- Webhook Secret ที่ใช้: `cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb`
- URL ที่ทดสอบ: `https://cny.re-ya.com/api/webhook/odoo.php`

## สาเหตุที่เป็นไปได้

1. **Code ยังไม่ได้ deploy** (มีแนวโน้มสูงสุด)
2. **Environment variable ไม่ถูกต้อง**
3. **Payload encoding มีปัญหา**
4. **Secret key ไม่ตรงกัน**

## การแก้ไขที่ทำแล้ว

### 1. ปรับปรุง `classes/OdooWebhookHandler.php`

เพิ่มความสามารถ:
- Normalize signature (trim whitespace)
- Debug logging แบบละเอียด (เฉพาะ non-production)
- Error logging ที่ชัดเจนขึ้น
- รองรับทั้ง 2 รูปแบบ signature:
  - **Primary**: `sha256=HMAC-SHA256(payload, secret)`
  - **Legacy**: `sha256=HMAC-SHA256(timestamp.payload, secret)`

### 2. สร้าง Test Endpoint

**ไฟล์**: `api/webhook/test-signature.php`

Endpoint นี้จะแสดงข้อมูลละเอียดเกี่ยวกับ:
- Secret ที่ config ไว้
- Signature ที่คำนวณได้
- Signature ที่ได้รับ
- ผลการเปรียบเทียบ
- คำแนะนำในการแก้ไข

**URL**: `https://cny.re-ya.com/api/webhook/test-signature.php`

### 3. สร้าง Test Scripts

- `test-signature-curl.bat` - ทดสอบด้วย curl และ PowerShell
- `test_webhook_signature.py` - ทดสอบด้วย Python (ต้องติดตั้ง requests)

## ขั้นตอนการทดสอบ

### ขั้นตอนที่ 1: ทดสอบ Signature Calculation

```bash
# Windows
test-signature-curl.bat

# หรือใช้ curl โดยตรง
curl -X POST "https://cny.re-ya.com/api/webhook/test-signature.php" \
  -H "Content-Type: application/json" \
  -H "X-Odoo-Signature: sha256=<calculated_signature>" \
  -H "X-Odoo-Timestamp: <unix_timestamp>" \
  -H "X-Odoo-Event: order.validated" \
  -H "X-Odoo-Delivery-Id: test-123" \
  -d '{"event":"order.validated","data":{"order_id":9999}}'
```

### ขั้นตอนที่ 2: ตรวจสอบ Response

Response จาก test endpoint จะบอกว่า:
- Secret ถูกต้องหรือไม่
- Signature ตรงกันหรือไม่
- Timestamp valid หรือไม่
- รูปแบบไหนที่ใช้ได้

### ขั้นตอนที่ 3: ทดสอบ Webhook จริง

หลังจากแน่ใจว่า signature ถูกต้อง ให้ทดสอบกับ endpoint จริง:

```bash
curl -X POST "https://cny.re-ya.com/api/webhook/odoo.php" \
  -H "Content-Type: application/json" \
  -H "X-Odoo-Signature: sha256=<calculated_signature>" \
  -H "X-Odoo-Timestamp: <unix_timestamp>" \
  -H "X-Odoo-Event: order.validated" \
  -H "X-Odoo-Delivery-Id: test-123" \
  -d '{"event":"order.validated","data":{"order_id":9999,"order_name":"TEST-001","customer":{"partner_id":100,"name":"Test"}}}'
```

## การคำนวณ Signature ที่ถูกต้อง

### PHP
```php
$secret = 'cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb';
$payload = file_get_contents('php://input'); // Raw body
$signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
```

### Python
```python
import hmac
import hashlib

secret = 'cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
payload = '{"event":"order.validated",...}'  # JSON string
signature = 'sha256=' + hmac.new(
    secret.encode('utf-8'),
    payload.encode('utf-8'),
    hashlib.sha256
).hexdigest()
```

### PowerShell
```powershell
$secret = 'cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
$payload = '{"event":"order.validated",...}'
$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [Text.Encoding]::UTF8.GetBytes($secret)
$hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload))
$signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-','').ToLower()
```

### Bash
```bash
SECRET='cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
PAYLOAD='{"event":"order.validated",...}'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')
echo "sha256=$SIGNATURE"
```

## Checklist การ Deploy

- [ ] Deploy code ใหม่ขึ้น production server
- [ ] ตรวจสอบ `config/config.php` มี `ODOO_WEBHOOK_SECRET` ถูกต้อง
- [ ] ตรวจสอบ `ODOO_ENVIRONMENT` ตั้งเป็น `'production'`
- [ ] ทดสอบด้วย `test-signature.php` endpoint
- [ ] ตรวจสอบ error logs: `/var/log/apache2/error.log` หรือ `/var/log/php-fpm/error.log`
- [ ] ทดสอบ webhook จริงจาก Odoo
- [ ] ตรวจสอบ database table `odoo_webhooks_log` มี records

## การตรวจสอบ Logs

### Apache/Nginx Error Log
```bash
tail -f /var/log/apache2/error.log | grep -i webhook
```

### PHP Error Log
```bash
tail -f /var/log/php-fpm/error.log | grep -i signature
```

### Database Logs
```sql
SELECT * FROM odoo_webhooks_log 
ORDER BY created_at DESC 
LIMIT 10;
```

## ข้อมูล Production Credentials

| รายการ | ค่า |
|--------|-----|
| **Odoo Base URL** | `https://erp.cnyrxapp.com` |
| **API Key** | `5pG-doAH1EEqHXurM3t466WAgKRYemmK7FANAB74o5A` |
| **Webhook Secret** | `cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb` |
| **Webhook URL** | `https://cny.re-ya.com/api/webhook/odoo.php` |
| **Module Version** | v11.0.1.2.3 |
| **Signature Format** | `sha256=HMAC-SHA256(payload, secret)` |

## คำแนะนำเพิ่มเติม

### ถ้า Signature ยังไม่ผ่าน

1. **ตรวจสอบ Secret**
   ```bash
   # SSH เข้า server
   ssh user@cny.re-ya.com
   
   # ตรวจสอบ config
   grep ODOO_WEBHOOK_SECRET /path/to/config/config.php
   ```

2. **ตรวจสอบ Payload Encoding**
   - ต้องเป็น UTF-8
   - ไม่มี BOM (Byte Order Mark)
   - ไม่มี trailing newline
   - JSON compact (ไม่มี whitespace เกิน)

3. **ตรวจสอบ Timestamp**
   - ต้องเป็น Unix timestamp (seconds)
   - ต้องอยู่ในช่วง ±5 นาที

4. **ลอง Disable Signature Check ชั่วคระ**
   ```php
   // ใน api/webhook/odoo.php (เฉพาะการทดสอบ!)
   // Comment out signature verification
   // if (!$handler->verifySignature($payload, $signature, (int) $timestamp)) {
   //     throw new Exception('Invalid webhook signature');
   // }
   ```

### ถ้า Deploy แล้วยังไม่ทำงาน

1. ตรวจสอบ file permissions
2. Clear PHP opcache: `service php-fpm reload`
3. ตรวจสอบ .htaccess rules
4. ตรวจสอบ firewall/security groups

## ติดต่อ Support

หากยังมีปัญหา กรุณาส่งข้อมูลต่อไปนี้:
- Response จาก `test-signature.php`
- Error logs จาก server
- ตัวอย่าง curl command ที่ใช้ทดสอบ
- Screenshot ของ Odoo webhook configuration

---

**สถานะ**: Code พร้อม Deploy ✅  
**วันที่อัพเดท**: 13 ก.พ. 2026  
**Version**: 1.0.0
