# Odoo BDO Flex Template - Quick Reference

**Quick guide for using the BDO payment request Flex message template**

---

## Basic Usage

```php
require_once 'classes/OdooFlexTemplates.php';

// 1. Prepare data from webhook
$data = [
    'bdo_ref' => 'BDO-2026-001',
    'order_ref' => 'SO-2026-001',
    'amount_total' => 15000.00,
    'due_date' => '2026-02-10',
    'invoice' => [
        'pdf_url' => 'https://erp.cnyrxapp.com/invoices/INV-001.pdf'
    ],
    'bank_account' => [
        'bank_name' => 'ธนาคารกสิกรไทย',
        'account_number' => '123-4-56789-0',
        'account_name' => 'บริษัท ซีเอ็นวาย จำกัด'
    ]
];

// 2. Generate QR code
$qrGenerator = new QRCodeGenerator();
$qrCodeUrl = $qrGenerator->generatePromptPayQR($emvcoPayload);

// 3. Create Flex message
$flexMessage = OdooFlexTemplates::bdoPaymentRequest($data, $qrCodeUrl);

// 4. Send to customer
$lineAPI->pushMessage($lineUserId, [
    'type' => 'flex',
    'altText' => 'แจ้งชำระเงิน ฿' . number_format($data['amount_total'], 2),
    'contents' => $flexMessage
]);
```

---

## Required Data Fields

### Mandatory
- `bdo_ref` - BDO reference number
- `order_ref` - Order reference number
- `amount_total` - Payment amount (float)
- `due_date` - Payment due date (string)

### Optional
- `invoice.pdf_url` - Invoice PDF URL (if available, shows button)
- `bank_account.bank_name` - Bank name
- `bank_account.account_number` - Account number
- `bank_account.account_name` - Account holder name

---

## Components Included

### ✅ Header
- Orange background (#F59E0B)
- Title: "💳 แจ้งชำระเงิน"
- Subtitle: "กรุณาชำระเงินภายในกำหนด"

### ✅ Order Information
- Order reference number
- BDO reference number

### ✅ Amount Display
- Large, bold amount with ฿ symbol
- Yellow background box for emphasis
- 2 decimal places formatting

### ✅ Due Date
- Red color for urgency
- Clock emoji (⏰)

### ✅ QR Code
- Full-width display
- 1:1 aspect ratio
- Instructional text

### ✅ Bank Account Info
- Bank name
- Account number (bold)
- Account name
- Gray background box

### ✅ Action Buttons
- Invoice PDF button (conditional)
- Slip upload button (always shown)

---

## Testing

### Visual Preview
```bash
# Open in browser
start re-ya/test-bdo-flex-preview.html
```

### PHP Test
```bash
# Run test script (requires PHP in PATH)
php re-ya/test-bdo-flex-complete.php
```

### LINE Flex Simulator
1. Run test to generate JSON
2. Copy from `test-bdo-flex-output.json`
3. Paste into https://developers.line.biz/flex-simulator/

---

## Common Issues

### QR Code Not Showing
- Verify QR code URL is accessible
- Check URL format (must be HTTPS)
- Ensure QR image is generated before template

### Invoice Button Missing
- Check if `invoice.pdf_url` is provided
- Button only shows if URL exists

### Amount Not Formatted
- Ensure `amount_total` is numeric
- Use `number_format($amount, 2)` for display

---

## Integration Example

```php
// In OdooWebhookHandler::handleBdoConfirmed()
public function handleBdoConfirmed($data, $notify, $template)
{
    // 1. Extract QR payment data
    $emvcoPayload = $data['qr_payment']['emvco_payload'] ?? '';
    
    // 2. Generate QR Code
    $qrGenerator = new QRCodeGenerator();
    $qrResult = $qrGenerator->generatePromptPayQR($emvcoPayload);
    $qrCodeUrl = 'https://cny.re-ya.com' . $qrResult['url'];
    
    // 3. Create Flex Message
    require_once __DIR__ . '/OdooFlexTemplates.php';
    $flexBubble = OdooFlexTemplates::bdoPaymentRequest($data, $qrCodeUrl);
    
    // 4. Send to customer
    if ($notify['customer'] ?? false) {
        $this->sendLineMessage($data['customer']['line_user_id'], [
            'type' => 'flex',
            'altText' => 'แจ้งชำระเงิน ฿' . number_format($data['amount_total'], 2),
            'contents' => $flexBubble
        ]);
    }
}
```

---

## File Locations

- **Template Class:** `/re-ya/classes/OdooFlexTemplates.php`
- **Webhook Handler:** `/re-ya/classes/OdooWebhookHandler.php`
- **QR Generator:** `/re-ya/classes/QRCodeGenerator.php`
- **Test Files:** `/re-ya/test-bdo-*.php`
- **Documentation:** `/re-ya/docs/TASK_12.2_*.md`

---

## Related Tasks

- **Task 11.2:** QR Code Generation
- **Task 12.1:** BDO Event Handler
- **Task 12.3:** BDO Webhook Testing
- **Task 13:** Slip Upload Implementation

---

## Support

For issues or questions:
1. Check `/re-ya/docs/TASK_12.2_BDO_FLEX_TEMPLATE_VERIFICATION.md`
2. Review test files for examples
3. Test with LINE Flex Simulator
4. Verify webhook data structure

---

**Last Updated:** 2026-02-03  
**Status:** Production Ready ✅
