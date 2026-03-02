# 🚨 Quick Fix Instructions - Webhook Signature

## ปัญหาที่พบ

จาก response ที่ได้:
```json
{
  "method": "GET",           // ❌ ผิด! ต้องเป็น POST
  "environment": "staging",  // ⚠️ ใช้ staging secret
  "payload_length": 0        // ไม่มี payload
}
```

## แก้ไขทันที

### 1. ใช้ POST แทน GET ✅

**ผิด**:
```bash
# เปิด URL ใน browser (GET request)
https://cny.re-ya.com/api/webhook/test-signature.php
```

**ถูก**:
```bash
# ใช้ curl หรือ Postman (POST request)
curl -X POST https://cny.re-ya.com/api/webhook/test-signature.php \
  -H "Content-Type: application/json" \
  -H "X-Odoo-Signature: sha256=..." \
  -H "X-Odoo-Timestamp: 1707200000" \
  -d '{"event":"test"}'
```

### 2. ทดสอบด้วย Script ที่ถูกต้อง

**Windows**:
```bash
test-webhook-post.bat
```

**Linux/Mac**:
```bash
chmod +x test-webhook-post.sh
./test-webhook-post.sh
```

### 3. ตรวจสอบ Environment

ไฟล์ `config/config.php` ได้แก้ไขแล้วให้ใช้ production:
```php
define('ODOO_ENVIRONMENT', 'production');
```

## ทดสอบอีกครั้ง

### ขั้นตอนที่ 1: Deploy Code ใหม่

Upload ไฟล์ที่แก้ไข:
- `config/config.php` (force production environment)
- `classes/OdooWebhookHandler.php` (improved logging)
- `api/webhook/test-signature.php` (test endpoint)

### ขั้นตอนที่ 2: ทดสอบด้วย POST

```bash
# Windows
test-webhook-post.bat

# หรือใช้ curl
curl -X POST "https://cny.re-ya.com/api/webhook/test-signature.php" \
  -H "Content-Type: application/json" \
  -H "X-Odoo-Signature: sha256=abc123..." \
  -H "X-Odoo-Timestamp: 1707200000" \
  -H "X-Odoo-Event: order.validated" \
  -H "X-Odoo-Delivery-Id: test-123" \
  -d '{"event":"order.validated","data":{"order_id":9999}}'
```

### ขั้นตอนที่ 3: ตรวจสอบ Response

Response ที่ถูกต้องควรเป็น:
```json
{
  "debug_info": {
    "method": "POST",              // ✅ ถูกต้อง
    "environment": "production",   // ✅ ถูกต้อง
    "secret_configured": true,
    "payload_length": 50           // ✅ มี payload
  },
  "validation": {
    "payload_only_match": true,    // ✅ signature ถูกต้อง
    "timestamp_valid": true
  },
  "result": "SUCCESS - Payload-only signature is correct"
}
```

## คำนวณ Signature ที่ถูกต้อง

### PowerShell (Windows)
```powershell
$secret = 'cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
$payload = '{"event":"order.validated","data":{"order_id":9999}}'

$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [Text.Encoding]::UTF8.GetBytes($secret)
$hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload))
$signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-','').ToLower()

Write-Host $signature
```

### Python
```python
import hmac
import hashlib

secret = 'cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
payload = '{"event":"order.validated","data":{"order_id":9999}}'

signature = 'sha256=' + hmac.new(
    secret.encode('utf-8'),
    payload.encode('utf-8'),
    hashlib.sha256
).hexdigest()

print(signature)
```

### Bash (Linux/Mac)
```bash
SECRET='cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
PAYLOAD='{"event":"order.validated","data":{"order_id":9999}}'

SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')
echo "sha256=$SIGNATURE"
```

## Checklist

- [ ] Deploy `config/config.php` (force production)
- [ ] Deploy `classes/OdooWebhookHandler.php`
- [ ] Deploy `api/webhook/test-signature.php`
- [ ] ทดสอบด้วย POST request (ไม่ใช่ GET)
- [ ] ตรวจสอบ response ว่า `payload_only_match: true`
- [ ] ทดสอบ webhook endpoint จริง
- [ ] แจ้งทีม CNY ว่าพร้อมทดสอบ E2E

## ติดต่อ

หากยังมีปัญหา ส่งข้อมูลต่อไปนี้:
1. Response จาก test endpoint (POST request)
2. curl command ที่ใช้ทดสอบ
3. Error logs จาก server

---

**สำคัญ**: ต้องใช้ **POST request** เท่านั้น! ❌ GET ไม่ได้
