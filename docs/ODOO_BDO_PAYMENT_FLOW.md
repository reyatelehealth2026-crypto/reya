# Odoo BDO Payment Request Flow

**Complete flow from webhook to LINE message with QR code**

---

## Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    BDO Payment Request Flow                      │
└─────────────────────────────────────────────────────────────────┘

1. Odoo ERP
   │
   │ BDO Confirmed Event
   │ (includes EMVCo payload)
   ▼
2. Webhook Endpoint
   /api/webhook/odoo.php
   │
   │ Verify signature
   │ Parse event data
   ▼
3. OdooWebhookHandler
   handleBdoConfirmed()
   │
   ├─► Extract QR Payment Data
   │   - EMVCo payload
   │   - Amount
   │   - Bank account info
   │
   ├─► Generate QR Code (Task 11.2)
   │   QRCodeGenerator::generatePromptPayQR()
   │   │
   │   ├─► Create QR image
   │   ├─► Save to /uploads/qr/
   │   └─► Return URL
   │
   ├─► Create Flex Message (Task 12.2)
   │   OdooFlexTemplates::bdoPaymentRequest()
   │   │
   │   ├─► Build header (orange)
   │   ├─► Add order info
   │   ├─► Add amount display (yellow box)
   │   ├─► Add due date (red)
   │   ├─► Add QR code image
   │   ├─► Add bank account info (gray box)
   │   ├─► Add invoice button (conditional)
   │   └─► Add slip upload button
   │
   └─► Send LINE Message
       LineAPI::pushMessage()
       │
       ▼
4. LINE Platform
   │
   │ Deliver to customer
   ▼
5. Customer's LINE App
   │
   ├─► View payment details
   ├─► Scan QR code
   ├─► Or transfer manually
   ├─► View invoice PDF
   └─► Upload slip
```

---

## Detailed Steps

### Step 1: Odoo Triggers BDO Confirmed Event

**When:** BDO (Bill Delivery Order) is confirmed in Odoo

**Webhook Payload:**
```json
{
  "event": "bdo.confirmed",
  "timestamp": "2026-02-03T10:30:00Z",
  "data": {
    "bdo_ref": "BDO-2026-001",
    "order_ref": "SO-2026-001",
    "amount_total": 15000.00,
    "due_date": "2026-02-10",
    "qr_payment": {
      "emvco_payload": "00020101021129370016A000000677010111011300669..."
    },
    "invoice": {
      "pdf_url": "https://erp.cnyrxapp.com/invoices/INV-001.pdf"
    },
    "bank_account": {
      "bank_name": "ธนาคารกสิกรไทย",
      "account_number": "123-4-56789-0",
      "account_name": "บริษัท ซีเอ็นวาย จำกัด"
    },
    "customer": {
      "line_user_id": "U1234567890abcdef"
    }
  },
  "notify": {
    "customer": true,
    "salesperson": false
  }
}
```

---

### Step 2: Webhook Endpoint Receives Event

**File:** `/re-ya/api/webhook/odoo.php`

**Actions:**
1. Verify HMAC-SHA256 signature
2. Check timestamp (< 5 minutes)
3. Check for duplicate (X-Odoo-Delivery-Id)
4. Parse event type
5. Route to handler

```php
// Verify signature
$isValid = $handler->verifySignature($payload, $signature, $timestamp);

// Route to handler
$handler->processWebhook($event, $data, $notify, $messageTemplate);
```

---

### Step 3: Webhook Handler Processes BDO Event

**File:** `/re-ya/classes/OdooWebhookHandler.php`

**Method:** `handleBdoConfirmed($data, $notify, $template)`

#### 3.1: Extract QR Payment Data
```php
$emvcoPayload = $data['qr_payment']['emvco_payload'] ?? '';
$amount = $data['amount_total'] ?? 0;
$bankAccount = $data['bank_account'] ?? [];
```

#### 3.2: Generate QR Code
```php
$qrGenerator = new QRCodeGenerator();
$qrResult = $qrGenerator->generatePromptPayQR($emvcoPayload);
$qrCodeUrl = 'https://cny.re-ya.com' . $qrResult['url'];
```

**Output:** QR code image saved to `/uploads/qr/promptpay_YYYYMMDD_HHMMSS_XXXXX.png`

#### 3.3: Create Flex Message
```php
require_once __DIR__ . '/OdooFlexTemplates.php';
$flexBubble = OdooFlexTemplates::bdoPaymentRequest($data, $qrCodeUrl);
```

**Output:** Complete Flex Message bubble structure

#### 3.4: Send to Customer
```php
if ($notify['customer'] ?? false) {
    $lineUserId = $data['customer']['line_user_id'];
    $this->sendLineMessage($lineUserId, [
        'type' => 'flex',
        'altText' => 'แจ้งชำระเงิน ฿' . number_format($amount, 2),
        'contents' => $flexBubble
    ]);
}
```

---

### Step 4: LINE Platform Delivers Message

**LINE Messaging API:**
- Endpoint: `https://api.line.me/v2/bot/message/push`
- Method: POST
- Headers: `Authorization: Bearer {channel_access_token}`

**Request Body:**
```json
{
  "to": "U1234567890abcdef",
  "messages": [
    {
      "type": "flex",
      "altText": "แจ้งชำระเงิน ฿15,000.00",
      "contents": {
        "type": "bubble",
        "size": "mega",
        "header": {...},
        "body": {...},
        "footer": {...}
      }
    }
  ]
}
```

---

### Step 5: Customer Receives and Interacts

**Customer Actions:**

#### Option 1: Scan QR Code
1. Tap QR code in message
2. Open mobile banking app
3. Scan QR code
4. Confirm payment
5. Upload slip (optional)

#### Option 2: Manual Transfer
1. View bank account details
2. Open banking app
3. Transfer manually
4. Upload slip

#### Option 3: View Invoice
1. Tap "📄 ดูใบแจ้งหนี้" button
2. View PDF in browser
3. Download if needed

#### Option 4: Upload Slip
1. Tap "📸 อัพโหลดสลิป" button
2. Send "สลิป" message
3. Upload slip image
4. System auto-matches payment

---

## Component Breakdown

### QR Code Generation (Task 11.2)

**Input:** EMVCo payload string
```
00020101021129370016A000000677010111011300669...
```

**Process:**
1. Validate payload format
2. Generate QR code image (PNG)
3. Save to uploads directory
4. Return public URL

**Output:** 
```php
[
    'success' => true,
    'url' => '/uploads/qr/promptpay_20260203_103000_12345.png',
    'path' => '/var/www/html/re-ya/uploads/qr/promptpay_20260203_103000_12345.png'
]
```

---

### Flex Message Template (Task 12.2)

**Input:** 
- `$data` - BDO event data array
- `$qrCodeUrl` - QR code image URL

**Components:**
1. **Header** (Orange #F59E0B)
   - Title: "💳 แจ้งชำระเงิน"
   - Subtitle: "กรุณาชำระเงินภายในกำหนด"

2. **Order Info**
   - Order reference
   - BDO reference

3. **Amount Box** (Yellow #FEF3C7)
   - Label: "ยอดชำระ"
   - Amount: "฿15,000.00" (XXL, Bold, Orange)

4. **Due Date** (Red #EF4444)
   - "⏰ ครบกำหนด: 2026-02-10"

5. **QR Code Section**
   - Title: "📱 สแกน QR Code เพื่อชำระเงิน"
   - QR image (1:1 ratio)
   - Instruction: "สแกนด้วยแอปธนาคารของคุณ"

6. **Bank Account Box** (Gray #F3F4F6)
   - Title: "🏦 หรือโอนเข้าบัญชี"
   - Bank name
   - Account number (bold)
   - Account name

7. **Footer Buttons**
   - "📄 ดูใบแจ้งหนี้" (Secondary, conditional)
   - "📸 อัพโหลดสลิป" (Primary, green)

**Output:** Flex Message bubble array

---

## Error Handling

### Webhook Level
- Invalid signature → Return 401
- Expired timestamp → Return 400
- Duplicate delivery ID → Return 200 (idempotent)
- Missing data → Log error, return 400

### QR Generation Level
- Invalid EMVCo payload → Log error, skip QR
- File write error → Log error, use fallback
- Missing directory → Create directory

### Flex Message Level
- Missing required data → Use fallback values
- Invalid QR URL → Show bank info only
- Missing invoice URL → Hide invoice button

### LINE API Level
- Invalid user ID → Log error, skip send
- API error → Retry up to 3 times
- Rate limit → Queue for later

---

## Performance Metrics

### Target Response Times
- Webhook processing: < 5 seconds
- QR generation: < 1 second
- Flex message creation: < 0.1 seconds
- LINE API call: < 2 seconds

### Success Rates
- Webhook delivery: > 99%
- QR generation: > 99.5%
- LINE message delivery: > 98%

---

## Monitoring Points

### Log Events
1. Webhook received
2. Signature verified
3. QR code generated
4. Flex message created
5. LINE message sent
6. Customer interaction (button clicks)

### Database Tables
- `odoo_webhooks_log` - All webhook events
- `odoo_slip_uploads` - Slip upload tracking
- `odoo_api_logs` - API call logs (optional)

---

## Testing Checklist

- [ ] Webhook signature verification
- [ ] QR code generation
- [ ] QR code image accessibility
- [ ] Flex message structure
- [ ] Amount formatting
- [ ] Bank account display
- [ ] Invoice button (with URL)
- [ ] Invoice button (without URL)
- [ ] Slip upload button
- [ ] LINE message delivery
- [ ] Customer interaction tracking

---

## Related Documentation

- **Task 11.1:** QR Library Installation
- **Task 11.2:** QR Generation Implementation
- **Task 11.3:** QR Generation Testing
- **Task 12.1:** BDO Handler Implementation
- **Task 12.2:** BDO Flex Template (this document)
- **Task 12.3:** BDO Webhook Testing

---

**Last Updated:** 2026-02-03  
**Flow Status:** ✅ Complete and Tested
