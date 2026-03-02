# Odoo Slip Upload - Quick Reference

**Task 13.1 Complete** ✅

---

## Quick Usage

```php
// Initialize client
$odooClient = new OdooAPIClient($db, $lineAccountId);

// Upload slip
$result = $odooClient->uploadSlip(
    $lineUserId,
    $slipImageBase64,
    [
        'bdo_id' => 123,
        'invoice_id' => 456,
        'amount' => 1500.00,
        'transfer_date' => '2026-02-03'
    ]
);

// Handle response
if ($result['status'] === 'matched') {
    // Auto-matched successfully
    $slipId = $result['slip_id'];
    $invoiceId = $result['matched_invoice_id'];
    $amount = $result['matched_amount'];
    echo "✅ จับคู่สำเร็จ: {$amount} บาท";
    
} else if ($result['status'] === 'pending') {
    // Pending manual review
    $slipId = $result['slip_id'];
    echo "📋 รอเจ้าหน้าที่ตรวจสอบ";
}
```

---

## Method Signature

```php
public function uploadSlip(
    string $lineUserId,
    string $slipImageBase64,
    array $options = []
): array
```

---

## Parameters

| Parameter | Type | Required | Example |
|-----------|------|----------|---------|
| `$lineUserId` | string | ✅ | `"U1234567890abcdef"` |
| `$slipImageBase64` | string | ✅ | `"iVBORw0KGgoAAAANS..."` |
| `$options['bdo_id']` | int | ❌ | `123` |
| `$options['invoice_id']` | int | ❌ | `456` |
| `$options['amount']` | float | ❌ | `1500.00` |
| `$options['transfer_date']` | string | ❌ | `"2026-02-03"` |

---

## Response Format

### Success - Auto-Matched

```json
{
  "success": true,
  "status": "matched",
  "slip_id": 789,
  "matched_invoice_id": 456,
  "matched_bdo_id": 123,
  "matched_amount": 1500.00,
  "message": "สลิปถูกจับคู่กับใบแจ้งหนี้เรียบร้อย"
}
```

### Success - Pending

```json
{
  "success": true,
  "status": "pending",
  "slip_id": 790,
  "message": "ได้รับสลิปแล้ว รอเจ้าหน้าที่ตรวจสอบ"
}
```

---

## Error Handling

```php
try {
    $result = $odooClient->uploadSlip($lineUserId, $slipImageBase64);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    // Thai error message with code
    // e.g., "กรุณาเชื่อมต่อบัญชี Odoo ก่อนใช้งาน (LINE_USER_NOT_LINKED)"
}
```

### Common Errors

| Error Code | Thai Message | Solution |
|------------|--------------|----------|
| `LINE_USER_NOT_LINKED` | กรุณาเชื่อมต่อบัญชี Odoo ก่อนใช้งาน | Link user first |
| `INVALID_IMAGE` | รูปภาพไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง | Check image format |
| `RATE_LIMIT_EXCEEDED` | มีการเรียกใช้งานมากเกินไป กรุณารอสักครู่ | Wait and retry |
| `NETWORK_ERROR` | เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง | Check connection |

---

## Complete Example

```php
<?php
require_once 'classes/Database.php';
require_once 'classes/OdooAPIClient.php';

// Get database connection
$db = Database::getInstance()->getConnection();

// Initialize Odoo client
$odooClient = new OdooAPIClient($db, 1);

// LINE user ID (from webhook)
$lineUserId = 'U1234567890abcdef';

// Download image from LINE Content API
$messageId = '123456789';
$imageUrl = "https://api-data.line.me/v2/bot/message/{$messageId}/content";

$ch = curl_init($imageUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
]);
$imageData = curl_exec($ch);
curl_close($ch);

// Convert to Base64
$slipImageBase64 = base64_encode($imageData);

// Upload slip
try {
    $result = $odooClient->uploadSlip(
        $lineUserId,
        $slipImageBase64,
        [
            'bdo_id' => 123,
            'amount' => 1500.00,
            'transfer_date' => date('Y-m-d')
        ]
    );
    
    // Save to database
    $stmt = $db->prepare("
        INSERT INTO odoo_slip_uploads 
        (line_account_id, line_user_id, odoo_slip_id, bdo_id, 
         amount, transfer_date, status, uploaded_at, matched_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $stmt->execute([
        1, // line_account_id
        $lineUserId,
        $result['slip_id'],
        $result['matched_bdo_id'] ?? null,
        $result['matched_amount'] ?? null,
        date('Y-m-d'),
        $result['status'],
        $result['status'] === 'matched' ? date('Y-m-d H:i:s') : null
    ]);
    
    // Send LINE confirmation
    $lineAPI = new LineAPI($db, 1);
    
    if ($result['status'] === 'matched') {
        $message = "✅ ได้รับสลิปและจับคู่เรียบร้อย\n";
        $message .= "ยอดเงิน: " . number_format($result['matched_amount'], 2) . " บาท";
    } else {
        $message = "📋 ได้รับสลิปแล้ว\n";
        $message .= "รอเจ้าหน้าที่ตรวจสอบและจับคู่";
    }
    
    $lineAPI->pushMessage($lineUserId, $message);
    
    echo json_encode([
        'success' => true,
        'result' => $result
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

---

## API Endpoint

**Endpoint:** `/reya/slip/upload`  
**Method:** POST (JSON-RPC 2.0)  
**Authentication:** X-Api-Key header

### Request

```json
{
  "jsonrpc": "2.0",
  "params": {
    "line_user_id": "U1234567890abcdef",
    "slip_image": "iVBORw0KGgoAAAANS...",
    "bdo_id": 123,
    "invoice_id": 456,
    "amount": 1500.00,
    "transfer_date": "2026-02-03"
  }
}
```

### Response

```json
{
  "result": {
    "success": true,
    "status": "matched",
    "slip_id": 789,
    "matched_invoice_id": 456,
    "matched_amount": 1500.00,
    "message": "สลิปถูกจับคู่กับใบแจ้งหนี้เรียบร้อย"
  }
}
```

---

## Features

✅ **Automatic retry** - 3 attempts for network errors  
✅ **Rate limiting** - 60 requests/minute  
✅ **Error handling** - Thai error messages  
✅ **Logging** - All calls logged to `odoo_api_logs`  
✅ **Timeout** - 30 seconds default  
✅ **JSON-RPC 2.0** - Standard format  

---

## Performance

- **Timeout:** 30 seconds
- **Rate Limit:** 60 requests/minute
- **Max Image Size:** 5MB recommended
- **Retry Delay:** 500ms between attempts

---

## Testing

```bash
# Run test script
php test-slip-upload-method.php

# Expected output:
# {
#   "success": true,
#   "message": "All tests passed!",
#   "tests": [...]
# }
```

---

## Next Steps

1. ✅ Task 13.1: uploadSlip() method - **COMPLETE**
2. ⏭️ Task 13.2: Create `/re-ya/api/odoo-slip-upload.php`
3. ⏭️ Task 13.3: Test slip upload

---

## Related Files

- **Implementation:** `/re-ya/classes/OdooAPIClient.php`
- **Test Script:** `/re-ya/test-slip-upload-method.php`
- **Documentation:** `/re-ya/docs/TASK_13.1_SLIP_UPLOAD_METHOD_VERIFICATION.md`
- **Flow Diagram:** `/re-ya/docs/ODOO_SLIP_UPLOAD_FLOW.md`

---

**Status:** ✅ COMPLETE  
**Date:** 2026-02-03  
**Version:** 1.0.0
