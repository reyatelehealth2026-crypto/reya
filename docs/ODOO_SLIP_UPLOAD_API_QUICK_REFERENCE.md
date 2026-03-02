# Odoo Slip Upload API - Quick Reference

**Endpoint:** `/api/odoo-slip-upload.php`  
**Method:** POST  
**Purpose:** Upload payment slip from LINE image message

---

## Quick Start

### 1. Basic Request
```bash
curl -X POST https://cny.re-ya.com/api/odoo-slip-upload.php \
  -H "Content-Type: application/json" \
  -d '{
    "line_user_id": "U1234567890abcdef",
    "message_id": "123456789012345"
  }'
```

### 2. Request with BDO
```bash
curl -X POST https://cny.re-ya.com/api/odoo-slip-upload.php \
  -H "Content-Type: application/json" \
  -d '{
    "line_user_id": "U1234567890abcdef",
    "message_id": "123456789012345",
    "bdo_id": 100,
    "amount": 1500.00
  }'
```

### 3. Request with Invoice
```bash
curl -X POST https://cny.re-ya.com/api/odoo-slip-upload.php \
  -H "Content-Type: application/json" \
  -d '{
    "line_user_id": "U1234567890abcdef",
    "message_id": "123456789012345",
    "invoice_id": 200,
    "amount": 1500.00,
    "transfer_date": "2026-02-03"
  }'
```

---

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `line_user_id` | string | ✅ Yes | LINE user ID |
| `message_id` | string | ✅ Yes | LINE message ID (image) |
| `line_account_id` | int | ❌ No | LINE account ID (auto-detected) |
| `bdo_id` | int | ❌ No | BDO ID for matching |
| `invoice_id` | int | ❌ No | Invoice ID for matching |
| `amount` | float | ❌ No | Payment amount |
| `transfer_date` | string | ❌ No | Transfer date (YYYY-MM-DD) |

---

## Response Examples

### Auto-matched Success ✅
```json
{
  "success": true,
  "message": "Slip uploaded successfully",
  "data": {
    "slip_id": 123,
    "status": "matched",
    "matched": true,
    "match_reason": "Auto-matched by Odoo",
    "order_name": "SO001",
    "amount": 1500.00
  }
}
```

**LINE Message:**
```
✅ ได้รับสลิปการชำระเงินและจับคู่เรียบร้อยแล้ว

📦 ออเดอร์: SO001
💰 ยอดเงิน: 1,500.00 บาท

ขอบคุณที่ชำระเงินค่ะ 🙏
```

### Pending Verification ⏳
```json
{
  "success": true,
  "message": "Slip uploaded successfully",
  "data": {
    "slip_id": 124,
    "status": "pending",
    "matched": false,
    "match_reason": null,
    "order_name": null,
    "amount": null
  }
}
```

**LINE Message:**
```
✅ ได้รับสลิปการชำระเงินแล้ว

⏳ รอเจ้าหน้าที่ตรวจสอบและจับคู่การชำระเงิน
เราจะแจ้งให้ทราบอีกครั้งเมื่อตรวจสอบเรียบร้อย

ขอบคุณค่ะ 🙏
```

### Error ❌
```json
{
  "success": false,
  "error": "Missing line_user_id"
}
```

---

## Flow

```
User sends image → LINE webhook → API endpoint
                                      ↓
                            Download from LINE
                                      ↓
                            Convert to Base64
                                      ↓
                            Upload to Odoo
                                      ↓
                            Save to database
                                      ↓
                            Send confirmation
```

---

## Database Record

```sql
-- Check recent uploads
SELECT * FROM odoo_slip_uploads 
ORDER BY uploaded_at DESC 
LIMIT 10;

-- Check matched slips
SELECT * FROM odoo_slip_uploads 
WHERE status = 'matched' 
ORDER BY matched_at DESC;

-- Check pending slips
SELECT * FROM odoo_slip_uploads 
WHERE status = 'pending' 
ORDER BY uploaded_at DESC;
```

---

## Integration with Webhook

```php
// In webhook.php
if ($messageType === 'image' && $userState === 'awaiting_slip') {
    $ch = curl_init('https://cny.re-ya.com/api/odoo-slip-upload.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'line_user_id' => $lineUserId,
        'message_id' => $messageId,
        'bdo_id' => $stateData['bdo_id'] ?? null
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
}
```

---

## Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| Missing line_user_id | Required parameter not provided | Include line_user_id in request |
| User not found | LINE user not in database | Ensure user exists in users table |
| LINE account not found | Invalid line_account_id | Check line_accounts table |
| Failed to download image | Invalid message_id or expired | Use valid message_id from recent webhook |
| Odoo API error | Odoo connection issue | Check Odoo API status and credentials |

---

## Testing Checklist

- [ ] Send test image from LINE
- [ ] Get message_id from webhook logs
- [ ] Call API with test data
- [ ] Verify database record created
- [ ] Check LINE confirmation message received
- [ ] Test auto-match scenario
- [ ] Test pending scenario
- [ ] Test error handling

---

## Related Documentation

- [Task 13.2 Implementation](TASK_13.2_SLIP_UPLOAD_API_IMPLEMENTATION.md)
- [Task 13.1 uploadSlip Method](TASK_13.1_COMPLETION_SUMMARY.md)
- [Slip Upload Flow](ODOO_SLIP_UPLOAD_FLOW.md)
- [Odoo API Client](../classes/OdooAPIClient.php)

---

**Quick tip:** Always test with real LINE image messages to ensure the complete flow works end-to-end!
