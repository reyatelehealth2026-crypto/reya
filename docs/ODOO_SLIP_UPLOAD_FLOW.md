# Odoo Slip Upload Flow - Technical Documentation

**Feature:** Payment Slip Upload  
**Task:** 13.1 - uploadSlip() Method  
**Date:** 2026-02-03

---

## Overview

This document describes the complete flow of payment slip uploads from LINE users to Odoo ERP, including the role of the `uploadSlip()` method.

---

## Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                    PAYMENT SLIP UPLOAD FLOW                          │
└─────────────────────────────────────────────────────────────────────┘

1. Customer receives BDO Payment Request (Task 12)
   ┌──────────────┐
   │ LINE Message │  QR Code + Payment Details
   │   (Flex)     │  "กรุณาชำระเงินและส่งสลิป"
   └──────┬───────┘
          │
          ▼
2. Customer pays and sends slip image
   ┌──────────────┐
   │   Customer   │  Sends image via LINE chat
   │  (LINE App)  │  📷 [Slip Image]
   └──────┬───────┘
          │
          ▼
3. LINE webhook receives image message
   ┌──────────────────────┐
   │  /webhook.php        │  Receives image message event
   │  (LINE Webhook)      │  message.type = "image"
   └──────┬───────────────┘
          │
          ▼
4. Download image from LINE Content API
   ┌──────────────────────┐
   │  LINE Content API    │  GET /v2/bot/message/{messageId}/content
   │                      │  Returns: Binary image data
   └──────┬───────────────┘
          │
          ▼
5. Convert to Base64
   ┌──────────────────────┐
   │  base64_encode()     │  Convert binary → Base64 string
   │                      │  
   └──────┬───────────────┘
          │
          ▼
6. Call uploadSlip() method (Task 13.1) ⭐
   ┌──────────────────────────────────────────────────────────┐
   │  OdooAPIClient::uploadSlip()                             │
   │  ────────────────────────────────────────────────────    │
   │  Parameters:                                             │
   │    - lineUserId: "U1234567890abcdef"                    │
   │    - slipImageBase64: "iVBORw0KGgoAAAANS..."           │
   │    - options: {                                          │
   │        bdo_id: 123,                                      │
   │        invoice_id: 456,                                  │
   │        amount: 1500.00,                                  │
   │        transfer_date: "2026-02-03"                       │
   │      }                                                   │
   │                                                          │
   │  Merges parameters:                                      │
   │    {                                                     │
   │      "line_user_id": "U1234567890abcdef",               │
   │      "slip_image": "iVBORw0KGgoAAAANS...",             │
   │      "bdo_id": 123,                                      │
   │      "invoice_id": 456,                                  │
   │      "amount": 1500.00,                                  │
   │      "transfer_date": "2026-02-03"                       │
   │    }                                                     │
   │                                                          │
   │  Calls: $this->call('/reya/slip/upload', $params)       │
   └──────┬───────────────────────────────────────────────────┘
          │
          ▼
7. JSON-RPC 2.0 Request to Odoo
   ┌──────────────────────────────────────────────────────────┐
   │  POST https://stg-erp.cnyrxapp.com/reya/slip/upload     │
   │  ────────────────────────────────────────────────────    │
   │  Headers:                                                │
   │    Content-Type: application/json                        │
   │    X-Api-Key: iUeWnsQe-SDb1qHS_v9W1-tll__5XK0S...       │
   │                                                          │
   │  Body:                                                   │
   │  {                                                       │
   │    "jsonrpc": "2.0",                                     │
   │    "params": {                                           │
   │      "line_user_id": "U1234567890abcdef",               │
   │      "slip_image": "iVBORw0KGgoAAAANS...",             │
   │      "bdo_id": 123,                                      │
   │      "invoice_id": 456,                                  │
   │      "amount": 1500.00,                                  │
   │      "transfer_date": "2026-02-03"                       │
   │    }                                                     │
   │  }                                                       │
   └──────┬───────────────────────────────────────────────────┘
          │
          ▼
8. Odoo processes slip
   ┌──────────────────────────────────────────────────────────┐
   │  Odoo ERP - Slip Processing                             │
   │  ────────────────────────────────────────────────────    │
   │  1. Verify LINE user is linked                           │
   │  2. Decode Base64 image                                  │
   │  3. OCR/Parse slip data                                  │
   │  4. Extract: amount, date, bank, reference               │
   │  5. Attempt auto-match with invoices/BDOs                │
   │                                                          │
   │  Match Logic:                                            │
   │  ┌────────────────────────────────────────────┐         │
   │  │ If amount + date match invoice/BDO         │         │
   │  │   → Auto-match successful                  │         │
   │  │   → Update payment status                  │         │
   │  │   → Return: status = "matched"             │         │
   │  │                                            │         │
   │  │ Else                                       │         │
   │  │   → Save for manual review                 │         │
   │  │   → Return: status = "pending"             │         │
   │  └────────────────────────────────────────────┘         │
   └──────┬───────────────────────────────────────────────────┘
          │
          ▼
9. Odoo returns response
   ┌──────────────────────────────────────────────────────────┐
   │  Response Type A: Auto-Match Success                     │
   │  ────────────────────────────────────────────────────    │
   │  {                                                       │
   │    "result": {                                           │
   │      "success": true,                                    │
   │      "status": "matched",                                │
   │      "slip_id": 789,                                     │
   │      "matched_invoice_id": 456,                          │
   │      "matched_bdo_id": 123,                              │
   │      "matched_amount": 1500.00,                          │
   │      "message": "สลิปถูกจับคู่กับใบแจ้งหนี้เรียบร้อย"   │
   │    }                                                     │
   │  }                                                       │
   └──────────────────────────────────────────────────────────┘
                          OR
   ┌──────────────────────────────────────────────────────────┐
   │  Response Type B: Pending Manual Review                  │
   │  ────────────────────────────────────────────────────    │
   │  {                                                       │
   │    "result": {                                           │
   │      "success": true,                                    │
   │      "status": "pending",                                │
   │      "slip_id": 790,                                     │
   │      "message": "ได้รับสลิปแล้ว รอเจ้าหน้าที่ตรวจสอบ"  │
   │    }                                                     │
   │  }                                                       │
   └──────┬───────────────────────────────────────────────────┘
          │
          ▼
10. uploadSlip() returns result
   ┌──────────────────────────────────────────────────────────┐
   │  OdooAPIClient::uploadSlip() - Return                    │
   │  ────────────────────────────────────────────────────    │
   │  Returns: $data['result']                                │
   │                                                          │
   │  The result contains:                                    │
   │  - success: bool                                         │
   │  - status: "matched" | "pending"                         │
   │  - slip_id: int                                          │
   │  - matched_invoice_id: int (if matched)                  │
   │  - matched_amount: float (if matched)                    │
   │  - message: string (Thai)                                │
   └──────┬───────────────────────────────────────────────────┘
          │
          ▼
11. Save to database (Task 13.2)
   ┌──────────────────────────────────────────────────────────┐
   │  INSERT INTO odoo_slip_uploads                           │
   │  ────────────────────────────────────────────────────    │
   │  (line_account_id, line_user_id, odoo_slip_id,          │
   │   odoo_partner_id, bdo_id, invoice_id, order_id,        │
   │   amount, transfer_date, status, match_reason,          │
   │   uploaded_at, matched_at)                              │
   │  VALUES (...)                                            │
   └──────┬───────────────────────────────────────────────────┘
          │
          ▼
12. Send LINE confirmation message
   ┌──────────────────────────────────────────────────────────┐
   │  LINE Messaging API - Push Message                       │
   │  ────────────────────────────────────────────────────    │
   │  If status = "matched":                                  │
   │    "✅ ได้รับสลิปและจับคู่เรียบร้อย"                    │
   │    "ยอดเงิน: 1,500.00 บาท"                              │
   │    "ใบแจ้งหนี้: INV/2026/00456"                          │
   │                                                          │
   │  If status = "pending":                                  │
   │    "📋 ได้รับสลิปแล้ว"                                  │
   │    "รอเจ้าหน้าที่ตรวจสอบและจับคู่"                       │
   │    "เราจะแจ้งให้ทราบเมื่อดำเนินการเสร็จสิ้น"            │
   └──────┬───────────────────────────────────────────────────┘
          │
          ▼
13. Customer receives confirmation
   ┌──────────────┐
   │   Customer   │  Sees confirmation message
   │  (LINE App)  │  ✅ or 📋
   └──────────────┘
```

---

## Method Implementation Details

### uploadSlip() Method Signature

```php
public function uploadSlip($lineUserId, $slipImageBase64, $options = [])
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$lineUserId` | string | Yes | LINE user ID (e.g., "U1234567890abcdef") |
| `$slipImageBase64` | string | Yes | Base64 encoded slip image |
| `$options` | array | No | Additional parameters |

### Options Array

| Key | Type | Description |
|-----|------|-------------|
| `bdo_id` | int | BDO ID to match against |
| `invoice_id` | int | Invoice ID to match against |
| `amount` | float | Expected payment amount |
| `transfer_date` | string | Transfer date (YYYY-MM-DD) |

### Return Value

```php
[
    'success' => true,
    'status' => 'matched', // or 'pending'
    'slip_id' => 789,
    'matched_invoice_id' => 456, // if matched
    'matched_amount' => 1500.00, // if matched
    'message' => 'สลิปถูกจับคู่กับใบแจ้งหนี้เรียบร้อย'
]
```

---

## Error Scenarios

### 1. LINE User Not Linked

```php
// Odoo returns error
{
    "error": {
        "code": "LINE_USER_NOT_LINKED",
        "message": "LINE user is not linked to any partner"
    }
}

// uploadSlip() throws exception
throw new Exception('กรุณาเชื่อมต่อบัญชี Odoo ก่อนใช้งาน (LINE_USER_NOT_LINKED)');
```

### 2. Invalid Image

```php
// Odoo returns error
{
    "error": {
        "code": "INVALID_IMAGE",
        "message": "Invalid or corrupted image data"
    }
}

// uploadSlip() throws exception
throw new Exception('รูปภาพไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง (INVALID_IMAGE)');
```

### 3. Rate Limit Exceeded

```php
// Before calling Odoo
if (!$this->checkRateLimit()) {
    throw new Exception('มีการเรียกใช้งานมากเกินไป กรุณารอสักครู่ (RATE_LIMIT_EXCEEDED)');
}
```

### 4. Network Error

```php
// cURL fails
if ($response === false) {
    if ($retryCount > 0) {
        usleep(500000); // Wait 500ms
        return $this->call($endpoint, $params, $retryCount - 1);
    }
    throw new Exception('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง (NETWORK_ERROR)');
}
```

---

## Auto-Match Logic (Odoo Side)

### Match Criteria

Odoo attempts to match the slip with invoices/BDOs using:

1. **Amount Match** (±1% tolerance)
   - Slip amount ≈ Invoice/BDO amount

2. **Date Match** (±3 days)
   - Transfer date ≈ Invoice/BDO date

3. **Reference Match** (if available)
   - Slip reference = Invoice/BDO reference

4. **Partner Match**
   - LINE user's partner = Invoice/BDO partner

### Match Success Rate

Target: **90% auto-match success rate**

Factors affecting success:
- ✅ Clear slip image
- ✅ Standard bank format
- ✅ Correct amount
- ✅ Recent transfer date
- ❌ Blurry image
- ❌ Non-standard format
- ❌ Multiple possible matches

---

## Database Schema

### odoo_slip_uploads Table

```sql
CREATE TABLE odoo_slip_uploads (
  id INT PRIMARY KEY AUTO_INCREMENT,
  line_account_id INT NOT NULL,
  line_user_id VARCHAR(100) NOT NULL,
  odoo_slip_id INT,                    -- From Odoo response
  odoo_partner_id INT,
  bdo_id INT,                           -- If matched to BDO
  invoice_id INT,                       -- If matched to invoice
  order_id INT,
  amount DECIMAL(10,2),                 -- From slip
  transfer_date DATE,                   -- From slip
  status ENUM('pending', 'matched', 'failed') DEFAULT 'pending',
  match_reason TEXT,                    -- Why matched/failed
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  matched_at DATETIME,                  -- When auto-matched
  INDEX (line_user_id),
  INDEX (status),
  INDEX (uploaded_at),
  FOREIGN KEY (line_account_id) REFERENCES line_accounts(id) ON DELETE CASCADE
);
```

---

## Performance Considerations

### 1. Image Size

- Recommended: < 5MB
- Format: JPEG, PNG
- Resolution: 1024x768 or higher

### 2. API Timeout

- Default: 30 seconds
- Includes: Upload + OCR + Match

### 3. Rate Limiting

- Limit: 60 requests/minute
- Per: LINE account
- Enforced by: `checkRateLimit()`

### 4. Retry Logic

- Max retries: 3
- Delay: 500ms between retries
- Only for: Network errors

---

## Testing Checklist

### Unit Tests
- ✅ Method signature
- ✅ Parameter merging
- ✅ Endpoint call
- ✅ Response handling

### Integration Tests
- [ ] Upload valid slip → auto-match
- [ ] Upload valid slip → pending
- [ ] Upload with BDO ID
- [ ] Upload with invoice ID
- [ ] Upload invalid image → error
- [ ] Upload without linking → error
- [ ] Rate limit test

### Manual Tests
- [ ] Real slip from SCB
- [ ] Real slip from Kbank
- [ ] Real slip from BBL
- [ ] Blurry slip
- [ ] Wrong amount
- [ ] Old transfer date

---

## Next Steps

1. ✅ Task 13.1: uploadSlip() method - **COMPLETED**
2. ⏭️ Task 13.2: Create API endpoint `/re-ya/api/odoo-slip-upload.php`
3. ⏭️ Task 13.3: Test slip upload end-to-end

---

## Related Documentation

- [Task 12.1: BDO Handler Implementation](TASK_12.1_BDO_HANDLER_IMPLEMENTATION.md)
- [Task 12.2: BDO Flex Template](TASK_12.2_BDO_FLEX_TEMPLATE_VERIFICATION.md)
- [Task 12.3: BDO Webhook Testing](TASK_12.3_BDO_WEBHOOK_TEST_SUMMARY.md)
- [Odoo BDO Payment Flow](ODOO_BDO_PAYMENT_FLOW.md)

---

**Last Updated:** 2026-02-03  
**Status:** ✅ Task 13.1 Complete  
**Next Task:** 13.2 - API Endpoint Implementation
