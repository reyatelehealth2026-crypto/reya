# Payment Status Testing - Quick Guide

Quick reference for testing payment status functionality.

---

## Test Files

| File | Purpose | Usage |
|------|---------|-------|
| `test-payment-status-scenarios.php` | PHP test script | `php test-payment-status-scenarios.php` |
| `test-payment-status-scenarios-report.html` | Visual report | Open in browser |
| `test-payment-status-api.php` | API endpoint tests | `php test-payment-status-api.php` |

---

## Test Scenarios

### 1. Paid Order ✅
```json
{
  "order_id": 100,
  "amount_total": 5000.00,
  "amount_paid": 5000.00,
  "amount_due": 0.00,
  "payment_state": "paid"
}
```

### 2. Unpaid Order ❌
```json
{
  "order_id": 101,
  "amount_total": 3000.00,
  "amount_paid": 0.00,
  "amount_due": 3000.00,
  "payment_state": "not_paid"
}
```

### 3. Partial Payment ⚠️
```json
{
  "order_id": 102,
  "amount_total": 10000.00,
  "amount_paid": 6000.00,
  "amount_due": 4000.00,
  "payment_state": "partial"
}
```

---

## API Testing

### Check Payment Status
```bash
curl -X POST http://localhost/re-ya/api/odoo-payment-status.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "check",
    "line_user_id": "U1234567890abcdef",
    "order_id": 100
  }'
```

### Query Parameters
- `order_id` - Check by order
- `bdo_id` - Check by BDO
- `invoice_id` - Check by invoice
- Multiple parameters supported

---

## Validation Checks

### Paid Order
- ✅ `payment_state === 'paid'`
- ✅ `amount_due === 0`
- ✅ `amount_paid === amount_total`
- ✅ `payments.length > 0`

### Unpaid Order
- ✅ `payment_state === 'not_paid'`
- ✅ `amount_paid === 0`
- ✅ `amount_due === amount_total`
- ✅ `payments.length === 0`

### Partial Payment
- ✅ `payment_state === 'partial'`
- ✅ `0 < amount_paid < amount_total`
- ✅ `0 < amount_due < amount_total`
- ✅ `payments.length > 0`

### All Scenarios
- ✅ `amount_total === amount_paid + amount_due`

---

## Quick Test Commands

```bash
# Run all tests
php test-payment-status-scenarios.php

# Test API endpoint
php test-payment-status-api.php

# Open visual report
start test-payment-status-scenarios-report.html
```

---

## Expected Results

| Test | Status | Validation |
|------|--------|------------|
| Paid Order | ✅ Pass | All checks passed |
| Unpaid Order | ✅ Pass | All checks passed |
| Partial Payment | ✅ Pass | All checks passed |

**Success Rate:** 100% (3/3 tests passed)

---

## Next Steps

1. Test with Odoo staging
2. Integrate with LIFF pages
3. Add LINE notifications
4. Deploy to production
