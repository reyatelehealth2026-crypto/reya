# Slip Upload Testing Guide

Quick reference for testing the Odoo slip upload functionality.

---

## Quick Start

### Run Complete Test Suite
```bash
cd re-ya
php test-slip-upload-complete.php
```

### Expected Output
```
=================================================================
Slip Upload Complete Test Suite
=================================================================

Pre-flight Checks
-----------------------------------
✓ Table 'odoo_slip_uploads' exists
✓ API file exists
✓ Class 'Database' loaded
✓ Class 'LineAPI' loaded
✓ Class 'OdooAPIClient' loaded

Task 13.3.1: Test Upload สลิป
-----------------------------------
✓ Created mock image data (67 bytes)
✓ Prepared test data with line_user_id: U_test_1738569600
✓ Base64 encoding/decoding works correctly
✓ All required parameters present
✓ Optional parameters supported: amount, transfer_date, bdo_id, invoice_id

✓ Basic upload test completed

Task 13.3.2: Test Auto-Match Success
-----------------------------------
✓ Mock Odoo auto-match response created
✓ All required fields present in auto-match response
✓ Matched flag is true
✓ Expected database status: matched
✓ Match reason provided: Auto-matched by Odoo
✓ Order name provided: SO001
✓ Amount provided: 1,500.00 บาท
✓ Test record inserted successfully (ID: 123)
✓ Record status is 'matched'
✓ Record has matched_at timestamp
✓ Test record cleaned up

✓ Auto-match success test completed

Task 13.3.3: Test Pending Match
-----------------------------------
✓ Mock Odoo pending response created
✓ Matched flag is false
✓ Status is 'pending'
✓ Match reason is null (as expected for pending)
✓ Test record inserted successfully (ID: 124)
✓ Record status is 'pending'
✓ Record has no matched_at timestamp (as expected)
✓ Record has no match_reason (as expected)
✓ Test record cleaned up

✓ Pending match test completed

Task 13.3.4: Verify LINE Message
-----------------------------------
✓ Auto-match success message format:
  ✅ ได้รับสลิปการชำระเงินและจับคู่เรียบร้อยแล้ว
  
  📦 ออเดอร์: SO001
  💰 ยอดเงิน: 1,500.00 บาท
  
  ขอบคุณที่ชำระเงินค่ะ 🙏
✓ Message contains success emoji
✓ Message contains order name
✓ Message contains formatted amount
✓ Message contains thank you message

✓ Pending match message format:
  ✅ ได้รับสลิปการชำระเงินแล้ว
  
  ⏳ รอเจ้าหน้าที่ตรวจสอบและจับคู่การชำระเงิน
  เราจะแจ้งให้ทราบอีกครั้งเมื่อตรวจสอบเรียบร้อย
  
  ขอบคุณค่ะ 🙏
✓ Message contains success emoji
✓ Message contains pending emoji
✓ Message contains pending verification text
✓ Message contains thank you message
✓ LINE message type is 'text'
✓ LINE message text is not empty

✓ LINE message format test completed

=================================================================
Test Summary
=================================================================
Total tests: 35
Passed: 35
Failed: 0
Pass rate: 100%

✓ All tests passed!

Task Completion Status:
  ✓ 13.3.1 Test upload สลิป
  ✓ 13.3.2 Test auto-match success
  ✓ 13.3.3 Test pending match
  ✓ 13.3.4 Verify LINE message
```

---

## Test Scenarios

### 1. Basic Upload (13.3.1)
Tests the fundamental upload functionality:
- Image data creation and encoding
- Required parameter validation
- Optional parameter support

### 2. Auto-Match Success (13.3.2)
Tests successful automatic matching:
- Odoo returns matched=true
- Database record with status='matched'
- matched_at timestamp set
- Order information preserved

### 3. Pending Match (13.3.3)
Tests manual verification scenario:
- Odoo returns matched=false
- Database record with status='pending'
- matched_at is NULL
- Awaiting staff verification

### 4. LINE Messages (13.3.4)
Tests message formatting:
- Auto-match success message
- Pending match message
- Thai language content
- Emoji usage
- LINE API structure

---

## Manual Testing

### Test with Real LINE Image

1. **Send image from LINE:**
   ```
   User sends payment slip image via LINE chat
   ```

2. **Webhook receives message:**
   ```php
   // In webhook.php
   if ($event['type'] === 'message' && $event['message']['type'] === 'image') {
       $messageId = $event['message']['id'];
       $lineUserId = $event['source']['userId'];
       
       // Call slip upload API
       $result = callSlipUploadAPI($lineUserId, $messageId);
   }
   ```

3. **Verify in database:**
   ```sql
   SELECT * FROM odoo_slip_uploads 
   WHERE line_user_id = 'U1234567890abcdef'
   ORDER BY uploaded_at DESC 
   LIMIT 1;
   ```

4. **Check LINE message:**
   - User should receive confirmation message
   - Message format should match test expectations

---

## API Testing

### Test Auto-Match with cURL

```bash
curl -X POST https://cny.re-ya.com/api/odoo-slip-upload.php \
  -H "Content-Type: application/json" \
  -d '{
    "line_user_id": "U1234567890abcdef",
    "message_id": "123456789012345",
    "line_account_id": 1,
    "bdo_id": 100,
    "amount": 1500.00,
    "transfer_date": "2026-02-03"
  }'
```

**Expected Response (Auto-match):**
```json
{
  "success": true,
  "message": "Slip uploaded successfully",
  "data": {
    "slip_id": 12345,
    "status": "matched",
    "matched": true,
    "match_reason": "Auto-matched by Odoo",
    "order_name": "SO001",
    "amount": 1500.00
  }
}
```

### Test Pending Match with cURL

```bash
curl -X POST https://cny.re-ya.com/api/odoo-slip-upload.php \
  -H "Content-Type: application/json" \
  -d '{
    "line_user_id": "U1234567890abcdef",
    "message_id": "123456789012346",
    "line_account_id": 1
  }'
```

**Expected Response (Pending):**
```json
{
  "success": true,
  "message": "Slip uploaded successfully",
  "data": {
    "slip_id": 12346,
    "status": "pending",
    "matched": false,
    "match_reason": null,
    "order_name": null,
    "amount": null
  }
}
```

---

## Database Verification

### Check Upload Records
```sql
-- Recent uploads
SELECT 
    id,
    line_user_id,
    odoo_slip_id,
    status,
    amount,
    match_reason,
    uploaded_at,
    matched_at
FROM odoo_slip_uploads
ORDER BY uploaded_at DESC
LIMIT 10;
```

### Check Auto-Match Rate
```sql
-- Auto-match success rate
SELECT 
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM odoo_slip_uploads), 2) as percentage
FROM odoo_slip_uploads
GROUP BY status;
```

### Check Pending Slips
```sql
-- Slips awaiting verification
SELECT 
    id,
    line_user_id,
    amount,
    transfer_date,
    uploaded_at,
    TIMESTAMPDIFF(HOUR, uploaded_at, NOW()) as hours_pending
FROM odoo_slip_uploads
WHERE status = 'pending'
ORDER BY uploaded_at ASC;
```

---

## Troubleshooting

### Test Fails: Table Not Found
```bash
# Run migration
php install/run_odoo_integration_migration.php
```

### Test Fails: Class Not Found
```bash
# Check autoloader
composer dump-autoload

# Verify class files exist
ls -la classes/Database.php
ls -la classes/LineAPI.php
ls -la classes/OdooAPIClient.php
```

### Test Fails: Database Connection
```php
// Check config/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

---

## Success Criteria

### All Tests Pass
- ✅ 35/35 tests passed
- ✅ 100% pass rate
- ✅ All 4 subtasks completed

### Database Operations
- ✅ Records inserted successfully
- ✅ Status set correctly (matched/pending)
- ✅ Timestamps recorded
- ✅ Test cleanup successful

### Message Validation
- ✅ Auto-match message correct
- ✅ Pending message correct
- ✅ Thai language used
- ✅ Emojis present
- ✅ LINE API format valid

---

## Next Steps

1. **Run test suite** to verify implementation
2. **Test with real LINE images** in staging
3. **Verify Odoo integration** with staging API
4. **Monitor auto-match rate** in production
5. **Track pending slips** for manual verification

---

## Related Files

- **Test Suite:** `/re-ya/test-slip-upload-complete.php`
- **API Endpoint:** `/re-ya/api/odoo-slip-upload.php`
- **API Client:** `/re-ya/classes/OdooAPIClient.php`
- **Documentation:** `/re-ya/docs/TASK_13.3_SLIP_UPLOAD_TEST_SUMMARY.md`

---

## Support

For issues or questions:
1. Check test output for specific errors
2. Verify database migration completed
3. Check Odoo API connectivity
4. Review LINE webhook logs
5. Consult task documentation

**Status:** ✅ Ready for testing
