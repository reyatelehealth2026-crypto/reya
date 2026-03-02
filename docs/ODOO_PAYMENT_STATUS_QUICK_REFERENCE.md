# Odoo Payment Status - Quick Reference

**Feature:** Payment Status Check  
**Status:** ✅ API Method Complete  
**Last Updated:** 2026-02-03

---

## Quick Start

```php
require_once 'classes/OdooAPIClient.php';

$odooClient = new OdooAPIClient($db, $lineAccountId);

// Check payment status by order ID
$status = $odooClient->getPaymentStatus('U1234567890abcdef', 100);
```

---

## Method Signature

```php
public function getPaymentStatus(
    string $lineUserId,      // Required: LINE user ID
    ?int $orderId = null,    // Optional: Order ID
    ?int $bdoId = null,      // Optional: BDO ID
    ?int $invoiceId = null   // Optional: Invoice ID
): array
```

---

## Common Use Cases

### 1. Check Order Payment Status

```php
$status = $odooClient->getPaymentStatus($lineUserId, $orderId);

if ($status['payment_state'] === 'paid') {
    echo "ชำระเงินเรียบร้อยแล้ว";
} else {
    echo "ยังไม่ได้ชำระเงิน ยอดค้างชำระ: " . $status['amount_due'];
}
```

### 2. Check BDO Payment Status

```php
$status = $odooClient->getPaymentStatus($lineUserId, null, $bdoId);

echo "สถานะ BDO: " . $status['bdo_state'];
echo "วิธีชำระเงิน: " . $status['payment_method'];
```

### 3. Check Invoice Payment Status

```php
$status = $odooClient->getPaymentStatus($lineUserId, null, null, $invoiceId);

echo "สถานะใบแจ้งหนี้: " . $status['invoice_state'];
echo "ยอดคงเหลือ: " . $status['amount_residual'];
```

### 4. Get All Pending Payments

```php
$status = $odooClient->getPaymentStatus($lineUserId);

foreach ($status['pending_payments'] as $payment) {
    echo "ออเดอร์: {$payment['order_name']} - ค้างชำระ: {$payment['amount_due']} บาท\n";
}
```

---

## Response Format

### Paid Order

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

### Unpaid Order

```json
{
  "success": true,
  "payment_status": {
    "order_id": 100,
    "order_name": "SO001",
    "payment_state": "not_paid",
    "amount_total": 1500.00,
    "amount_paid": 0.00,
    "amount_due": 1500.00,
    "payment_method": null,
    "due_date": "2026-02-10"
  }
}
```

### Partial Payment

```json
{
  "success": true,
  "payment_status": {
    "order_id": 100,
    "order_name": "SO001",
    "payment_state": "partial",
    "amount_total": 1500.00,
    "amount_paid": 500.00,
    "amount_due": 1000.00,
    "payment_method": "bank_transfer",
    "last_payment_date": "2026-02-01"
  }
}
```

---

## Payment States

| State | Description | Thai |
|-------|-------------|------|
| `not_paid` | No payment received | ยังไม่ได้ชำระ |
| `partial` | Partially paid | ชำระบางส่วน |
| `paid` | Fully paid | ชำระครบแล้ว |
| `in_payment` | Payment processing | กำลังประมวลผล |
| `reversed` | Payment reversed | ยกเลิกการชำระ |

---

## Error Handling

```php
try {
    $status = $odooClient->getPaymentStatus($lineUserId, $orderId);
    
    // Process payment status
    
} catch (Exception $e) {
    // Handle errors
    $errorMessage = $e->getMessage();
    
    if (strpos($errorMessage, 'LINE_USER_NOT_LINKED') !== false) {
        // Redirect to account linking page
    } elseif (strpos($errorMessage, 'ORDER_NOT_FOUND') !== false) {
        // Show order not found message
    } else {
        // Show generic error
    }
}
```

---

## Common Errors

| Error Code | Message | Solution |
|------------|---------|----------|
| `LINE_USER_NOT_LINKED` | กรุณาเชื่อมต่อบัญชี Odoo ก่อนใช้งาน | Link user account first |
| `ORDER_NOT_FOUND` | ไม่พบออเดอร์ | Verify order ID |
| `CUSTOMER_MISMATCH` | ออเดอร์นี้ไม่ใช่ของคุณ | Check ownership |
| `RATE_LIMIT_EXCEEDED` | มีการเรียกใช้งานมากเกินไป | Wait and retry |

---

## Integration Examples

### In LIFF Order Detail Page

```javascript
// liff/assets/js/order-detail.js
async function checkPaymentStatus(orderId) {
    try {
        const response = await fetch('/api/odoo-payment-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'check',
                order_id: orderId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            updatePaymentBadge(data.payment_status);
        }
    } catch (error) {
        console.error('Failed to check payment status:', error);
    }
}
```

### In PHP API Endpoint

```php
// api/odoo-payment-status.php
$action = $_POST['action'] ?? '';

if ($action === 'check') {
    $orderId = $_POST['order_id'] ?? null;
    
    try {
        $odooClient = new OdooAPIClient($db, $lineAccountId);
        $status = $odooClient->getPaymentStatus($lineUserId, $orderId);
        
        echo json_encode([
            'success' => true,
            'payment_status' => $status
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
```

---

## Testing

```bash
# Run test script
cd re-ya
php test-payment-status.php
```

---

## Files

| File | Purpose |
|------|---------|
| `classes/OdooAPIClient.php` | Main implementation |
| `test-payment-status.php` | Test script |
| `docs/TASK_14.1_PAYMENT_STATUS_IMPLEMENTATION.md` | Full documentation |

---

## Next Steps

1. ✅ Task 14.1 - API method implemented
2. ⏳ Task 14.2 - Create API endpoint `/api/odoo-payment-status.php`
3. ⏳ Task 14.3 - Test payment status with real data

---

## Support

For issues or questions:
- Check full documentation: `docs/TASK_14.1_PAYMENT_STATUS_IMPLEMENTATION.md`
- Review design spec: `.kiro/specs/odoo-integration/design.md`
- Test with: `php test-payment-status.php`
