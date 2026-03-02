# Odoo Slip Upload - Webhook Integration Guide

**Purpose:** Integrate slip upload API with existing LINE webhook  
**Date:** 2026-02-03

---

## Overview

This guide shows how to integrate the Odoo slip upload API with the existing LINE webhook to automatically process payment slips when users send images.

---

## Integration Strategy

### Option 1: User State-Based (Recommended)

When a user is waiting to upload a slip (e.g., after receiving a BDO payment request), set their state to `awaiting_slip`. When they send an image, automatically process it as a payment slip.

### Option 2: Command-Based

Users can send a command like "อัพโหลดสลิป" followed by an image to trigger slip upload.

### Option 3: Always Process

Automatically treat all image messages as potential payment slips (not recommended - may cause false positives).

---

## Implementation: Option 1 (User State-Based)

### Step 1: Set User State After BDO Payment Request

In `OdooWebhookHandler->handleBdoConfirmed()`:

```php
// After sending BDO payment request with QR code
// Set user state to awaiting_slip
$stmt = $this->db->prepare("
    INSERT INTO user_states (user_id, state, state_data, expires_at)
    VALUES (?, 'awaiting_slip', ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
    ON DUPLICATE KEY UPDATE 
        state = 'awaiting_slip',
        state_data = VALUES(state_data),
        expires_at = VALUES(expires_at)
");

$stateData = json_encode([
    'bdo_id' => $data['bdo_id'],
    'invoice_id' => $data['invoice_id'] ?? null,
    'amount' => $data['amount'] ?? null,
    'order_id' => $data['order_id'] ?? null
]);

$stmt->execute([$internalUserId, $stateData]);
```

### Step 2: Handle Image Messages in Webhook

In `webhook.php`, add this code in the message handling section:

```php
// Handle image messages
if ($messageType === 'image') {
    // Check if user is in awaiting_slip state
    $stmt = $db->prepare("
        SELECT state, state_data 
        FROM user_states 
        WHERE user_id = ? 
        AND state = 'awaiting_slip'
        AND expires_at > NOW()
    ");
    $stmt->execute([$userId]);
    $userState = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userState) {
        // User is waiting to upload slip - process it
        $stateData = json_decode($userState['state_data'], true);
        
        // Call slip upload API
        $ch = curl_init('https://cny.re-ya.com/api/odoo-slip-upload.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'line_user_id' => $lineUserId,
            'message_id' => $messageId,
            'line_account_id' => $lineAccountId,
            'bdo_id' => $stateData['bdo_id'] ?? null,
            'invoice_id' => $stateData['invoice_id'] ?? null,
            'amount' => $stateData['amount'] ?? null
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Clear user state
        $stmt = $db->prepare("DELETE FROM user_states WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Log the result
        if ($httpCode === 200) {
            error_log("Slip upload successful for user {$lineUserId}");
        } else {
            error_log("Slip upload failed for user {$lineUserId}: {$response}");
        }
        
        // Don't process this image further
        exit;
    }
    
    // If not in awaiting_slip state, handle as normal image message
    // ... existing image handling code ...
}
```

---

## Implementation: Option 2 (Command-Based)

### Step 1: Add Command Handler

In `webhook.php`, add command detection:

```php
// Handle text messages
if ($messageType === 'text') {
    $text = trim($message['text']);
    
    // Check for slip upload command
    if (preg_match('/^(อัพโหลดสลิป|upload slip|ส่งสลิป)/i', $text)) {
        // Set user state to awaiting_slip
        $stmt = $db->prepare("
            INSERT INTO user_states (user_id, state, state_data, expires_at)
            VALUES (?, 'awaiting_slip', '{}', DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ON DUPLICATE KEY UPDATE 
                state = 'awaiting_slip',
                state_data = '{}',
                expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId]);
        
        // Send instruction message
        $line->replyMessage($replyToken, [
            [
                'type' => 'text',
                'text' => "กรุณาส่งรูปสลิปการชำระเงินค่ะ 📷\n\nระบบจะจับคู่การชำระเงินอัตโนมัติ"
            ]
        ]);
        
        exit;
    }
}

// Handle image messages (same as Option 1)
if ($messageType === 'image') {
    // ... same code as Option 1 ...
}
```

---

## User State Table Schema

If `user_states` table doesn't exist, create it:

```sql
CREATE TABLE IF NOT EXISTS user_states (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    state VARCHAR(50) NOT NULL,
    state_data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    UNIQUE KEY (user_id),
    INDEX (state),
    INDEX (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Complete Flow Example

### Scenario: User receives BDO payment request

```
1. Odoo sends BDO confirmed webhook
   ↓
2. OdooWebhookHandler processes webhook
   ↓
3. Generate QR code and send to user
   ↓
4. Set user state to 'awaiting_slip' with BDO data
   ↓
5. User scans QR and pays
   ↓
6. User sends slip image to LINE
   ↓
7. Webhook detects user is in 'awaiting_slip' state
   ↓
8. Call odoo-slip-upload.php API
   ↓
9. API downloads image from LINE
   ↓
10. API converts to Base64
   ↓
11. API uploads to Odoo
   ↓
12. Odoo auto-matches slip
   ↓
13. API saves to database
   ↓
14. API sends confirmation to user
   ↓
15. Clear user state
```

---

## Error Handling

### Handle API Errors

```php
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    // API call failed
    $errorData = json_decode($response, true);
    $errorMessage = $errorData['error'] ?? 'เกิดข้อผิดพลาดในการอัพโหลดสลิป';
    
    // Send error message to user
    $line->pushMessage($lineUserId, [
        [
            'type' => 'text',
            'text' => "❌ {$errorMessage}\n\nกรุณาลองใหม่อีกครั้งหรือติดต่อเจ้าหน้าที่"
        ]
    ]);
    
    // Log error
    error_log("Slip upload API error: {$errorMessage}");
}
```

### Handle Expired State

```php
// Check if state is expired
$stmt = $db->prepare("
    SELECT state, state_data 
    FROM user_states 
    WHERE user_id = ? 
    AND state = 'awaiting_slip'
    AND expires_at > NOW()
");
$stmt->execute([$userId]);
$userState = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userState) {
    // State expired or doesn't exist
    // Handle as normal image message
    // ... existing code ...
}
```

---

## Testing

### Test Scenario 1: Complete Flow

1. Trigger BDO webhook (use test-bdo-webhook-complete.php)
2. Verify user receives QR code
3. Verify user state is set to 'awaiting_slip'
4. Send test image from LINE
5. Verify slip upload API is called
6. Verify confirmation message received
7. Verify user state is cleared

### Test Scenario 2: Expired State

1. Set user state with past expiry
2. Send image from LINE
3. Verify image is handled as normal (not as slip)

### Test Scenario 3: API Error

1. Set invalid Odoo credentials
2. Send image from LINE
3. Verify error message sent to user
4. Verify error logged

---

## Monitoring

### Track Slip Uploads

```sql
-- Daily slip uploads
SELECT 
    DATE(uploaded_at) as date,
    COUNT(*) as total_uploads,
    SUM(CASE WHEN status = 'matched' THEN 1 ELSE 0 END) as auto_matched,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
FROM odoo_slip_uploads
WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(uploaded_at)
ORDER BY date DESC;
```

### Track User States

```sql
-- Current awaiting_slip users
SELECT 
    u.line_user_id,
    u.display_name,
    us.state_data,
    us.created_at,
    us.expires_at
FROM user_states us
JOIN users u ON us.user_id = u.id
WHERE us.state = 'awaiting_slip'
AND us.expires_at > NOW()
ORDER BY us.created_at DESC;
```

---

## Best Practices

1. **Always set expiry time** for user states (24 hours recommended)
2. **Clear state after processing** to prevent duplicate uploads
3. **Log all API calls** for debugging
4. **Handle errors gracefully** and inform users
5. **Monitor auto-match rate** to improve accuracy
6. **Clean up expired states** regularly (cron job)

---

## Cleanup Cron Job

Create `/re-ya/cron/cleanup_expired_states.php`:

```php
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

// Delete expired states
$stmt = $db->prepare("DELETE FROM user_states WHERE expires_at < NOW()");
$stmt->execute();

$deleted = $stmt->rowCount();
echo "Cleaned up {$deleted} expired user states\n";
```

Add to crontab:
```
0 * * * * php /path/to/re-ya/cron/cleanup_expired_states.php
```

---

## Related Files

- `/re-ya/api/odoo-slip-upload.php` - Slip upload API
- `/re-ya/webhook.php` - LINE webhook handler
- `/re-ya/classes/OdooWebhookHandler.php` - Odoo webhook handler
- `/re-ya/docs/TASK_13.2_SLIP_UPLOAD_API_IMPLEMENTATION.md` - API documentation

---

**Integration complete! Users can now upload payment slips seamlessly through LINE. 🎉**
