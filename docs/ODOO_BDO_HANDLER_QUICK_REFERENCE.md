# Odoo BDO Handler - Quick Reference Guide

**Quick access guide for developers working with BDO payment requests**

---

## Overview

The BDO (Bill Delivery Order) handler processes payment requests from Odoo and sends LINE messages with PromptPay QR codes to customers.

---

## Key Files

| File | Purpose |
|------|---------|
| `classes/OdooWebhookHandler.php` | Main handler with `handleBdoConfirmed()` |
| `classes/OdooFlexTemplates.php` | Flex message templates |
| `classes/QRCodeGenerator.php` | QR code generation |
| `test-bdo-handler.php` | Test script |
| `test-bdo-flex-preview.html` | Visual preview |

---

## Usage

### 1. Webhook Receives BDO Event

```php
// In /api/webhook/odoo.php
$handler = new OdooWebhookHandler($db);
$result = $handler->handleBdoConfirmed($data, $notify, $template);
```

### 2. Handler Processes Event

```php
// Automatically:
// 1. Extracts EMVCo payload
// 2. Generates QR code
// 3. Creates Flex message
// 4. Sends to customer
// 5. Notifies salesperson
```

### 3. Customer Receives Message

```
LINE Message:
- Payment amount
- QR code
- Bank details
- Invoice button
- Upload slip button
```

---

## Expected Webhook Data

```json
{
  "event": "bdo.confirmed",
  "data": {
    "bdo_ref": "BDO-2026-001",
    "order_ref": "SO-2026-001",
    "amount_total": 15000.00,
    "due_date": "2026-02-10",
    "customer": {
      "partner_id": 100,
      "name": "คุณสมชาย"
    },
    "payment": {
      "promptpay": {
        "qr_data": {
          "raw_payload": "00020101021129370016A000000677..."
        }
      }
    },
    "invoice": {
      "pdf_url": "https://erp.cnyrxapp.com/invoices/INV-001.pdf"
    },
    "bank_account": {
      "bank_name": "ธนาคารกสิกรไทย",
      "account_number": "123-4-56789-0",
      "account_name": "บริษัท ซีเอ็นวาย จำกัด"
    }
  },
  "notify": {
    "customer": true,
    "salesperson": true
  }
}
```

---

## Method Signature

```php
public function handleBdoConfirmed($data, $notify, $template)
```

**Parameters:**
- `$data` (array) - BDO event data from Odoo
- `$notify` (array) - Notification rules (customer, salesperson)
- `$template` (array) - Message templates (not used, we use Flex)

**Returns:**
- `array` - List of recipients ['customer', 'salesperson']

---

## Flex Message Template

```php
// Create payment request Flex message
$flexBubble = OdooFlexTemplates::bdoPaymentRequest($data, $qrCodeUrl);

// Send to LINE
$this->sendLineFlexMessage(
    $lineUserId,
    $channelAccessToken,
    $flexBubble,
    '💳 แจ้งชำระเงิน - SO-2026-001'
);
```

---

## QR Code Generation

```php
// Generate QR code from EMVCo payload
$qrGenerator = new QRCodeGenerator();
$result = $qrGenerator->generatePromptPayQR($emvcoPayload, $bdoRef);

// Result:
// [
//   'success' => true,
//   'url' => '/uploads/qrcodes/promptpay_BDO-001_1234567890.png',
//   'path' => '/full/path/to/file.png',
//   'filename' => 'promptpay_BDO-001_1234567890.png'
// ]
```

---

## Error Handling

### Missing EMVCo Payload
```php
if (empty($emvcoPayload)) {
    error_log('BDO Confirmed: No EMVCo payload found');
    return [];
}
```

### QR Generation Failed
```php
if (!$qrResult['success']) {
    error_log('BDO Confirmed: QR generation failed - ' . $qrResult['error']);
    return [];
}
```

### Exception Handling
```php
try {
    // Handler logic
} catch (Exception $e) {
    error_log('Error in handleBdoConfirmed: ' . $e->getMessage());
    return [];
}
```

---

## Testing

### Run Automated Tests
```bash
php re-ya/test-bdo-handler.php
```

### View Visual Preview
```bash
open re-ya/test-bdo-flex-preview.html
```

### Test with Mock Data
```php
$mockData = [
    'bdo_ref' => 'BDO-TEST-001',
    'order_ref' => 'SO-TEST-001',
    'amount_total' => 1000.00,
    'payment' => [
        'promptpay' => [
            'qr_data' => [
                'raw_payload' => '00020101021129370016A000000677...'
            ]
        ]
    ]
];

$handler = new OdooWebhookHandler($db);
$result = $handler->handleBdoConfirmed($mockData, $notify, []);
```

---

## Debugging

### Check Logs
```bash
# Check error log
tail -f /path/to/error.log | grep "BDO Confirmed"

# Check webhook log
SELECT * FROM odoo_webhooks_log 
WHERE event_type = 'bdo.confirmed' 
ORDER BY processed_at DESC 
LIMIT 10;
```

### Verify QR Code
```bash
# Check if QR code was generated
ls -lh re-ya/uploads/qrcodes/

# View QR code
open re-ya/uploads/qrcodes/promptpay_BDO-001_*.png
```

### Test LINE Message
```php
// Check if message was sent
// Look for LINE API response in logs
```

---

## Common Issues

### Issue 1: QR Code Not Generated
**Cause:** Missing EMVCo payload or invalid format  
**Solution:** Check webhook data structure

### Issue 2: LINE Message Not Sent
**Cause:** User not linked or notifications disabled  
**Solution:** Check `odoo_line_users` table

### Issue 3: QR Code Not Scannable
**Cause:** Invalid EMVCo payload  
**Solution:** Verify payload format with Odoo team

---

## Configuration

### Required Constants
```php
define('BASE_URL', 'https://cny.re-ya.com');
define('ODOO_WEBHOOK_SECRET', 'your_webhook_secret');
```

### Required Directories
```bash
mkdir -p re-ya/uploads/qrcodes
chmod 755 re-ya/uploads/qrcodes
```

### Required Libraries
```bash
composer require endroid/qr-code ^4.8
```

---

## Performance

| Operation | Time | Notes |
|-----------|------|-------|
| QR Generation | 50-100ms | Per QR code |
| Flex Creation | 10ms | In memory |
| LINE API Call | 100-300ms | Network dependent |
| **Total** | **200-500ms** | Well within 5s limit |

---

## Security Checklist

- [x] Verify webhook signature
- [x] Check timestamp (< 5 minutes)
- [x] Validate EMVCo payload exists
- [x] Check user is linked to Odoo
- [x] Verify notification preferences
- [x] Use secure LINE API calls
- [x] Log all operations

---

## Monitoring

### Key Metrics
- BDO events received
- QR codes generated
- LINE messages sent
- Success rate
- Average response time

### Alerts
- QR generation failures > 5%
- LINE API errors > 10%
- Response time > 3 seconds

---

## Related Documentation

- [Full Implementation Guide](TASK_12.1_BDO_HANDLER_IMPLEMENTATION.md)
- [Completion Summary](TASK_12.1_COMPLETION_SUMMARY.md)
- [QR Generation Guide](TASK_11.2_QR_GENERATION_SUMMARY.md)
- [Odoo Integration Design](../.kiro/specs/odoo-integration/design.md)

---

## Support

### For Issues
1. Check error logs
2. Verify webhook data
3. Test QR generation
4. Check LINE API credentials
5. Review database records

### For Questions
- Review full documentation
- Check test files
- Inspect Flex message JSON
- Contact Odoo team for payload issues

---

**Last Updated:** 2026-02-03  
**Version:** 1.0.0  
**Status:** Production Ready
