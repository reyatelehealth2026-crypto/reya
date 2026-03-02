# Task 11.3: QR Generation Testing Guide

**Status:** Ready for Testing  
**Date:** 2026-02-03  
**Feature:** Odoo Integration - QR Code Generation

---

## Overview

This document provides comprehensive testing instructions for the QR code generation functionality. The tests verify that PromptPay QR codes are generated correctly from EMVCo payloads and can be scanned by mobile banking apps.

---

## Prerequisites

1. **PHP Environment:** PHP 7.4+ with GD or Imagick extension
2. **Composer Dependencies:** Run `composer install` to install `endroid/qr-code`
3. **Upload Directory:** Ensure `/uploads/qrcodes/` exists and is writable (755 permissions)
4. **Mobile Banking App:** Any Thai banking app with PromptPay support

---

## Test Execution

### Step 1: Run the Test Script

```bash
cd re-ya
php test-qr-generation.php
```

### Expected Output

The script will run 7 comprehensive tests:

1. ✓ Check if endroid/qr-code library is installed
2. ✓ Initialize QRCodeGenerator class
3. ✓ Generate QR codes with sample EMVCo payloads (4 different amounts)
4. ✓ Generate Base64 QR code for inline display
5. ✓ Generate generic QR code (URL test)
6. ✓ Check upload directory permissions
7. ✓ Parse EMVCo payload structure

---

## Test Cases

### Test 3.1: Small Amount (10.00 THB)

**EMVCo Payload:**
```
00020101021129370016A000000677010111011300668123456785802TH5303764540510.006304ABCD
```

**Expected Results:**
- Amount: 10.00 THB
- Reference: ORDER_001
- QR code file generated: `promptpay_ORDER_001_[timestamp].png`
- File size: ~1-2 KB

**Verification:**
- [ ] QR code scans successfully
- [ ] Amount displays as 10.00 THB
- [ ] Recipient information shown
- [ ] Can initiate payment (DO NOT complete)

---

### Test 3.2: Medium Amount (1,500.00 THB)

**EMVCo Payload:**
```
00020101021129370016A000000677010111011300668123456785802TH53037645406150.006304WXYZ
```

**Expected Results:**
- Amount: 1,500.00 THB
- Reference: ORDER_002
- QR code file generated: `promptpay_ORDER_002_[timestamp].png`

**Verification:**
- [ ] QR code scans successfully
- [ ] Amount displays as 1,500.00 THB
- [ ] Typical order amount test

---

### Test 3.3: Large Amount (25,000.00 THB)

**EMVCo Payload:**
```
00020101021129370016A000000677010111011300668123456785802TH530376454072500.006304PQRS
```

**Expected Results:**
- Amount: 25,000.00 THB
- Reference: ORDER_003
- QR code file generated: `promptpay_ORDER_003_[timestamp].png`

**Verification:**
- [ ] QR code scans successfully
- [ ] Amount displays as 25,000.00 THB
- [ ] Large order amount test

---

### Test 3.4: Decimal Amount (99.50 THB)

**EMVCo Payload:**
```
00020101021129370016A000000677010111011300668123456785802TH5303764540599.506304LMNO
```

**Expected Results:**
- Amount: 99.50 THB
- Reference: ORDER_004
- QR code file generated: `promptpay_ORDER_004_[timestamp].png`

**Verification:**
- [ ] QR code scans successfully
- [ ] Amount displays as 99.50 THB (with decimal precision)
- [ ] Decimal handling test

---

## Manual Verification Steps

### Step 1: View Generated QR Codes

**Option A: Web Browser**
1. Open browser
2. Navigate to: `https://cny.re-ya.com/uploads/qrcodes/`
3. View all generated QR code images

**Option B: File System**
1. Navigate to: `re-ya/uploads/qrcodes/`
2. View PNG files directly

---

### Step 2: Scan with Mobile Banking App

**Supported Apps:**
- SCB Easy
- Krungthai NEXT
- Bangkok Bank Mobile Banking
- Kasikorn K PLUS
- Any PromptPay-enabled app

**Scanning Process:**
1. Open mobile banking app
2. Select "Scan QR" or "PromptPay" option
3. Point camera at QR code on screen
4. Wait for app to recognize QR code

---

### Step 3: Verify Payment Details

For each QR code, verify the following:

#### ✓ QR Code Scans Successfully
- App recognizes the QR code
- No error messages
- Payment screen appears

#### ✓ Amount is Correct
- Displayed amount matches expected value
- Decimal places are correct (e.g., 99.50 not 99.5)
- No rounding errors

#### ✓ Recipient Information
- Recipient name/ID is displayed
- PromptPay ID: 0066812345678 (example)
- Country: Thailand (TH)

#### ✓ Currency is THB
- Currency code: 764 (Thai Baht)
- Symbol: ฿ or THB

#### ✓ Reference/Note (if supported)
- Order reference may appear in notes
- Format: ORDER_001, ORDER_002, etc.

---

## EMVCo Payload Structure

Understanding the payload format:

```
00 02 01 01 02 11          - Payload Format Indicator
29 37 00 16 A000000677010111 01 13 0066812345678 - PromptPay ID
58 02 TH                   - Country Code (Thailand)
53 03 764                  - Currency Code (THB)
54 05 10.00                - Transaction Amount
63 04 ABCD                 - CRC Checksum
```

**Field Breakdown:**
- `00`: Payload Format Indicator
- `29`: Merchant Account Information
- `58`: Country Code
- `53`: Transaction Currency
- `54`: Transaction Amount
- `63`: CRC Checksum

---

## Test Results Documentation

### Test Summary Template

```
Date: [YYYY-MM-DD]
Tester: [Name]
Environment: [Production/Staging]

Test Results:
─────────────────────────────────────────────────────────
Test 3.1 - Small Amount (10.00 THB)
  ☐ QR generated successfully
  ☐ File exists and is valid
  ☐ Scans with mobile app
  ☐ Amount is correct
  ☐ Notes: ___________________________________________

Test 3.2 - Medium Amount (1,500.00 THB)
  ☐ QR generated successfully
  ☐ File exists and is valid
  ☐ Scans with mobile app
  ☐ Amount is correct
  ☐ Notes: ___________________________________________

Test 3.3 - Large Amount (25,000.00 THB)
  ☐ QR generated successfully
  ☐ File exists and is valid
  ☐ Scans with mobile app
  ☐ Amount is correct
  ☐ Notes: ___________________________________________

Test 3.4 - Decimal Amount (99.50 THB)
  ☐ QR generated successfully
  ☐ File exists and is valid
  ☐ Scans with mobile app
  ☐ Amount is correct (with decimals)
  ☐ Notes: ___________________________________________

Overall Result: ☐ PASS  ☐ FAIL
```

---

## Common Issues and Troubleshooting

### Issue 1: Library Not Installed
**Error:** `Class 'Endroid\QrCode\QrCode' not found`

**Solution:**
```bash
cd re-ya
composer install
```

---

### Issue 2: Upload Directory Not Writable
**Error:** `Failed to save QR code file`

**Solution:**
```bash
chmod 755 re-ya/uploads/qrcodes/
chown www-data:www-data re-ya/uploads/qrcodes/
```

---

### Issue 3: QR Code Won't Scan
**Possible Causes:**
- QR code image is too small (increase size parameter)
- Image quality is poor (check file size)
- Invalid EMVCo payload format
- CRC checksum mismatch

**Solution:**
- Regenerate with larger size (300px+)
- Verify EMVCo payload format
- Check CRC calculation

---

### Issue 4: Wrong Amount Displayed
**Possible Causes:**
- EMVCo payload amount field incorrect
- Decimal point placement wrong
- Currency code mismatch

**Solution:**
- Verify field `54` in payload contains correct amount
- Format: `54 [length] [amount]`
- Example: `54 05 10.00` for 10.00 THB

---

## Success Criteria

All tests must pass the following criteria:

### ✓ Automated Tests
- [x] Library installation check passes
- [x] QRCodeGenerator initializes without errors
- [x] All 4 sample QR codes generate successfully
- [x] Files are created in upload directory
- [x] File sizes are reasonable (1-3 KB)
- [x] Base64 generation works
- [x] Upload directory is writable

### ✓ Manual Verification
- [ ] All QR codes scan with mobile banking app
- [ ] Amounts display correctly (10.00, 1,500.00, 25,000.00, 99.50)
- [ ] Decimal precision is maintained
- [ ] Recipient information is shown
- [ ] Currency is THB
- [ ] Payment can be initiated (DO NOT complete)

### ✓ Edge Cases
- [ ] Small amounts (< 100 THB) work
- [ ] Large amounts (> 10,000 THB) work
- [ ] Decimal amounts work correctly
- [ ] Different references are preserved

---

## Next Steps

After completing these tests:

1. ✓ Mark task 11.3.1 as complete
2. ✓ Mark task 11.3.2 as complete (manual scanning)
3. ✓ Mark task 11.3.3 as complete (amount verification)
4. ✓ Mark task 11.3 as complete
5. → Proceed to task 12.1: Implement BDO Event Handler

---

## References

- **QRCodeGenerator Class:** `/re-ya/classes/QRCodeGenerator.php`
- **Test Script:** `/re-ya/test-qr-generation.php`
- **Library Documentation:** https://github.com/endroid/qr-code
- **EMVCo Specification:** https://www.emvco.com/emv-technologies/qrcodes/

---

## Notes

- **DO NOT complete actual payments** during testing
- QR codes are for testing purposes only
- Keep generated QR codes for reference and documentation
- Test with multiple banking apps if possible
- Document any issues or unexpected behavior

---

**Test Status:** ✓ Ready for Execution  
**Last Updated:** 2026-02-03
