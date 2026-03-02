# สรุปการแก้ไข Webhook Signature Validation

## 🎯 ปัญหา
Webhook จาก Odoo ไป Re-Ya ไม่ผ่าน signature validation  
Error: `HTTP 400 - Invalid webhook signature`

## ✅ การแก้ไขที่ทำแล้ว

### 1. ปรับปรุง Code
- **ไฟล์**: `classes/OdooWebhookHandler.php`
- เพิ่ม debug logging
- ปรับปรุง error messages
- รองรับทั้ง 2 รูปแบบ signature

### 2. สร้าง Test Tools
- **Test Endpoint**: `api/webhook/test-signature.php`
- **Test Script**: `test-signature-curl.bat`
- **Documentation**: `WEBHOOK_SIGNATURE_FIX_GUIDE.md`

## 📋 ขั้นตอนต่อไป

### สำหรับทีม Re-Ya

1. **Deploy Code ขึ้น Production**
   ```bash
   # Upload files ที่แก้ไขไปยัง server
   - classes/OdooWebhookHandler.php
   - api/webhook/test-signature.php
   - api/webhook/odoo.php
   ```

2. **ตรวจสอบ Config**
   ```bash
   # SSH เข้า server
   ssh user@cny.re-ya.com
   
   # ตรวจสอบ secret
   grep ODOO_WEBHOOK_SECRET config/config.php
   
   # ต้องได้: cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb
   ```

3. **ทดสอบ Signature**
   ```bash
   # ใช้ test endpoint
   curl -X POST "https://cny.re-ya.com/api/webhook/test-signature.php" \
     -H "Content-Type: application/json" \
     -H "X-Odoo-Signature: sha256=..." \
     -H "X-Odoo-Timestamp: 1707200000" \
     -d '{"event":"test"}'
   ```

4. **แจ้งกลับเมื่อ Deploy เสร็จ**
   - ทีม CNY จะทดสอบ E2E ทันที

### สำหรับทีม CNY (Odoo)

1. **รอการ Deploy จากทีม Re-Ya**
2. **เมื่อได้รับแจ้งว่า deploy เสร็จ**:
   - ทดสอบส่ง webhook จาก Odoo
   - ตรวจสอบ response
   - ตรวจสอบ logs

## 🔍 วิธีทดสอบ

### Test 1: ทดสอบ Signature Calculation
```bash
# Windows
test-signature-curl.bat

# Linux/Mac
curl -X POST "https://cny.re-ya.com/api/webhook/test-signature.php" \
  -H "Content-Type: application/json" \
  -H "X-Odoo-Signature: sha256=<signature>" \
  -H "X-Odoo-Timestamp: <timestamp>" \
  -d '{"event":"order.validated","data":{"order_id":9999}}'
```

### Test 2: ทดสอบ Webhook จริง
```bash
curl -X POST "https://cny.re-ya.com/api/webhook/odoo.php" \
  -H "Content-Type: application/json" \
  -H "X-Odoo-Signature: sha256=<signature>" \
  -H "X-Odoo-Timestamp: <timestamp>" \
  -H "X-Odoo-Event: order.validated" \
  -H "X-Odoo-Delivery-Id: test-123" \
  -d '{"event":"order.validated","data":{...}}'
```

## 📊 ข้อมูล Production

| รายการ | ค่า |
|--------|-----|
| **Webhook URL** | `https://cny.re-ya.com/api/webhook/odoo.php` |
| **Test URL** | `https://cny.re-ya.com/api/webhook/test-signature.php` |
| **Secret** | `cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb` |
| **Signature Format** | `sha256=HMAC-SHA256(payload, secret)` |

## 🐛 Debug Tips

### ตรวจสอบ Logs
```bash
# Apache/Nginx error log
tail -f /var/log/apache2/error.log | grep webhook

# PHP error log
tail -f /var/log/php-fpm/error.log | grep signature

# Database logs
mysql> SELECT * FROM odoo_webhooks_log ORDER BY created_at DESC LIMIT 10;
```

### Response จาก Test Endpoint
Test endpoint จะแสดง:
- ✅ Secret ที่ config ไว้
- ✅ Signature ที่คำนวณได้
- ✅ Signature ที่ได้รับ
- ✅ ผลการเปรียบเทียบ
- ✅ คำแนะนำการแก้ไข

## 📞 ติดต่อ

หากมีปัญหา กรุณาส่ง:
1. Response จาก test endpoint
2. Error logs จาก server
3. ตัวอย่าง curl command ที่ใช้

---

**สถานะ**: ✅ Code พร้อม Deploy  
**รอ**: Deploy จากทีม Re-Ya  
**วันที่**: 13 ก.พ. 2026
