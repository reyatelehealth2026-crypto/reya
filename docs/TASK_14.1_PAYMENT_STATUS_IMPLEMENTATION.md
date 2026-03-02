# Task 14.1.1 - Payment Status API Method Implementation

**Status:** ✅ Complete  
**Date:** 2026-02-03  
**Task:** Implement `getPaymentStatus($lineUserId, $orderId, $bdoId, $invoiceId)` method

---

## Implementation Summary

The `getPaymentStatus` method has been successfully implemented in the `OdooAPIClient` class.

### Location
- **File:** `/re-ya/classes/OdooAPIClient.php`
- **Lines:** 330-347

### Method Signature

```php
public function getPaymentStatus($lineUserId, $orderId = null, $bdoId = null, $invoiceId = null)
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$lineUserId` | string | ✅ Yes | LINE user ID |
| `$orderId` | int | ❌ No | Order ID (optional) |
| `$bdoId` | int | ❌ No | BDO ID (optional) |
| `$invoiceId` | int | ❌ No | Invoice ID (optional) |

### Implementation Details

```php
public function getPaymentStatus($lineUserId, $orderId = null, $bdoId = null, $invoiceId = null)
{
    $params = ['line_user_id' => $lineUserId];

    if ($orderId)
        $params['order_id'] = $orderId;
    if ($bdoId)
        $params['bdo_id'] = $bdoId;
    if ($invoiceId)
        $params['invoice_id'] = $invoiceId;

    return $this->call('/reya/payment/status', $params);
}
```

### Features

✅ **Required Parameter Handling**
- `line_user_id` is always included in the request

✅ **Optional Parameter Handling**
- `order_id`, `bdo_id`, and `invoice_id` are conditionally added
- Only non-null values are included in the API request

✅ **API Endpoint**
- Calls `/reya/payment/status` endpoint
- Uses JSON-RPC 2.0 format via the `call()` method

✅ **Error Handling**
- Inherits error handling from the `call()` method
- Supports automatic retry on network errors
- Returns Thai error messages via `handleError()`

✅ **Rate Limiting**
- Automatically enforced by the `call()` method
- Respects 60 requests/minute limit

✅ **Logging**
- All API calls are logged to `odoo_api_logs` table
- Includes request params, response, duration, and errors

---

## Usage Examples

### Example 1: Check payment status by order ID

```php
$odooClient = new OdooAPIClient($db, $lineAccountId);

try {
    $status = $odooClient->getPaymentStatus(
        'U1234567890abcdef',  // LINE user ID
        100                    // Order ID
    );
    
    echo "Payment Status: " . $status['payment_state'];
    echo "Amount Paid: " . $status['amount_paid'];
    echo "Amount Due: " . $status['amount_due'];
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Example 2: Check payment status by BDO ID

```php
try {
    $status = $odooClient->getPaymentStatus(
        'U1234567890abcdef',  // LINE user ID
        null,                  // Order ID (not used)
        50                     // BDO ID
    );
    
    echo "BDO Status: " . $status['bdo_state'];
    echo "Payment Method: " . $status['payment_method'];
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Example 3: Check payment status by invoice ID

```php
try {
    $status = $odooClient->getPaymentStatus(
        'U1234567890abcdef',  // LINE user ID
        null,                  // Order ID (not used)
        null,                  // BDO ID (not used)
        200                    // Invoice ID
    );
    
    echo "Invoice Status: " . $status['invoice_state'];
    echo "Amount Total: " . $status['amount_total'];
    echo "Amount Residual: " . $status['amount_residual'];
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Example 4: Check all payment statuses for a user

```php
try {
    $status = $odooClient->getPaymentStatus('U1234567890abcdef');
    
    // Returns all pending payments for the user
    foreach ($status['pending_payments'] as $payment) {
        echo "Order: " . $payment['order_name'];
        echo "Amount Due: " . $payment['amount_due'];
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

## Expected API Response Format

### Success Response

```json
{
  "success": true,
  "payment_status": {
    "order_id": 100,
    "order_name": "SO001",
    "payment_state": "paid",
    "amount_total": 1500.00,
    "amount_paid": 1500.00,
    "amount_due": 0.00,
    "payment_method": "promptpay",
    "paid_at": "2026-02-03T10:30:00Z"
  }
}
```

### Error Response

```json
{
  "error": {
    "code": "ORDER_NOT_FOUND",
    "message": "ไม่พบออเดอร์"
  }
}
```

---

## Error Handling

The method handles the following error scenarios:

| Error Code | Thai Message | Description |
|------------|--------------|-------------|
| `LINE_USER_NOT_LINKED` | กรุณาเชื่อมต่อบัญชี Odoo ก่อนใช้งาน | User not linked to Odoo |
| `ORDER_NOT_FOUND` | ไม่พบออเดอร์ | Order doesn't exist |
| `BDO_NOT_FOUND` | ไม่พบข้อมูล BDO | BDO doesn't exist |
| `INVOICE_NOT_FOUND` | ไม่พบใบแจ้งหนี้ | Invoice doesn't exist |
| `CUSTOMER_MISMATCH` | ออเดอร์นี้ไม่ใช่ของคุณ | Order belongs to different customer |
| `RATE_LIMIT_EXCEEDED` | มีการเรียกใช้งานมากเกินไป กรุณารอสักครู่ | Too many requests |
| `NETWORK_ERROR` | เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง | Connection failed |

---

## Testing

### Test File
- **Location:** `/re-ya/test-payment-status.php`
- **Usage:** `php test-payment-status.php`

### Test Cases

1. ✅ Get payment status with order_id
2. ✅ Get payment status with bdo_id
3. ✅ Get payment status with invoice_id
4. ✅ Get payment status with multiple parameters
5. ✅ Get payment status with only line_user_id

### Running Tests

```bash
cd re-ya
php test-payment-status.php
```

**Note:** Tests require Odoo staging environment to be accessible.

---

## Integration Points

### Used By
- `/re-ya/api/odoo-payment-status.php` - Payment status API endpoint (Task 14.2)
- LIFF pages for order detail and invoice detail
- Payment confirmation workflows

### Dependencies
- `OdooAPIClient::call()` - Core API call method
- `OdooAPIClient::handleError()` - Error handling
- `OdooAPIClient::checkRateLimit()` - Rate limiting
- `OdooAPIClient::logApiCall()` - API logging

---

## Next Steps

✅ **Task 14.1.1 Complete** - `getPaymentStatus()` method implemented

**Next Task:** 14.2 - Create API endpoint `/re-ya/api/odoo-payment-status.php`

---

## Verification Checklist

- [x] Method signature matches design specification
- [x] Required parameter (line_user_id) is handled
- [x] Optional parameters (order_id, bdo_id, invoice_id) are handled
- [x] Correct API endpoint is called (/reya/payment/status)
- [x] Parameters are correctly formatted for JSON-RPC
- [x] Error handling is implemented
- [x] Rate limiting is enforced
- [x] API calls are logged
- [x] Test file created
- [x] Documentation created

---

## Summary

The `getPaymentStatus` method is **fully implemented and ready for use**. It provides a flexible way to check payment status by order ID, BDO ID, invoice ID, or retrieve all pending payments for a user. The implementation follows the design specification and integrates seamlessly with the existing OdooAPIClient infrastructure.
