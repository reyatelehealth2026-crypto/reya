# ทดสอบ Webhook ด้วย Postman

## ขั้นตอนที่ 1: ตั้งค่า Request

1. เปิด Postman
2. สร้าง request ใหม่
3. ตั้งค่าดังนี้:

### Method
```
POST
```

### URL
```
https://cny.re-ya.com/api/webhook/test-signature.php
```

### Headers
```
Content-Type: application/json
X-Odoo-Signature: sha256=<คำนวณด้านล่าง>
X-Odoo-Timestamp: <unix_timestamp>
X-Odoo-Event: order.validated
X-Odoo-Delivery-Id: test-123
```

### Body (raw JSON)
```json
{
  "event": "order.validated",
  "data": {
    "order_id": 9999,
    "order_name": "TEST-001",
    "order_ref": "SO-TEST-001",
    "customer": {
      "partner_id": 100,
      "name": "Test Customer"
    },
    "amount_total": 1500.00
  }
}
```

## ขั้นตอนที่ 2: คำนวณ Signature

### ใช้ Pre-request Script ใน Postman

ไปที่ tab "Pre-request Script" และใส่ code นี้:

```javascript
// Secret key
const secret = 'cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb';

// Get request body
const body = pm.request.body.raw;

// Calculate HMAC-SHA256
const hmac = CryptoJS.HmacSHA256(body, secret).toString();
const signature = 'sha256=' + hmac;

// Get current timestamp
const timestamp = Math.floor(Date.now() / 1000).toString();

// Set headers
pm.request.headers.upsert({
    key: 'X-Odoo-Signature',
    value: signature
});

pm.request.headers.upsert({
    key: 'X-Odoo-Timestamp',
    value: timestamp
});

pm.request.headers.upsert({
    key: 'X-Odoo-Delivery-Id',
    value: 'test-' + timestamp
});

console.log('Signature:', signature);
console.log('Timestamp:', timestamp);
```

## ขั้นตอนที่ 3: ส่ง Request

1. กด "Send"
2. ดู Response

### Response ที่ถูกต้อง

```json
{
  "debug_info": {
    "method": "POST",
    "environment": "production",
    "secret_configured": true,
    "payload_length": 200
  },
  "validation": {
    "payload_only_match": true,
    "timestamp_valid": true
  },
  "result": "SUCCESS - Payload-only signature is correct"
}
```

## ขั้นตอนที่ 4: ทดสอบ Webhook จริง

เปลี่ยน URL เป็น:
```
https://cny.re-ya.com/api/webhook/odoo.php
```

ใช้ Pre-request Script เดิม และส่ง Request

### Response ที่คาดหวัง

```json
{
  "success": true,
  "received_at": "2026-02-13T10:30:00+07:00",
  "delivery_id": "test-1707200000",
  "event": "order.validated",
  "sent_to": []
}
```

## Alternative: ใช้ curl

### Windows (PowerShell)
```powershell
$secret = 'cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
$payload = '{"event":"order.validated","data":{"order_id":9999}}'
$timestamp = [int][double]::Parse((Get-Date -UFormat %s))

$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [Text.Encoding]::UTF8.GetBytes($secret)
$hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload))
$signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-','').ToLower()

curl -X POST "https://cny.re-ya.com/api/webhook/test-signature.php" `
  -H "Content-Type: application/json" `
  -H "X-Odoo-Signature: $signature" `
  -H "X-Odoo-Timestamp: $timestamp" `
  -H "X-Odoo-Event: order.validated" `
  -H "X-Odoo-Delivery-Id: test-$timestamp" `
  -d $payload
```

### Linux/Mac (Bash)
```bash
SECRET='cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
PAYLOAD='{"event":"order.validated","data":{"order_id":9999}}'
TIMESTAMP=$(date +%s)
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

curl -X POST "https://cny.re-ya.com/api/webhook/test-signature.php" \
  -H "Content-Type: application/json" \
  -H "X-Odoo-Signature: sha256=$SIGNATURE" \
  -H "X-Odoo-Timestamp: $TIMESTAMP" \
  -H "X-Odoo-Event: order.validated" \
  -H "X-Odoo-Delivery-Id: test-$TIMESTAMP" \
  -d "$PAYLOAD"
```

## Troubleshooting

### ถ้า signature ไม่ตรง
1. ตรวจสอบ secret key
2. ตรวจสอบ payload ต้องเหมือนกันทุกตัวอักษร
3. ตรวจสอบ encoding (UTF-8)
4. ตรวจสอบไม่มี whitespace เกิน

### ถ้า timestamp expired
1. ตรวจสอบเวลาเครื่อง server
2. Timestamp ต้องอยู่ในช่วง ±5 นาที

---

**สำคัญ**: ต้องใช้ POST request เท่านั้น! GET ไม่สามารถใช้งานได้
