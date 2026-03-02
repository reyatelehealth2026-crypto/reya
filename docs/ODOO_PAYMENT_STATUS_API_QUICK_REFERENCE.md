# Odoo Payment Status API - Quick Reference

**Endpoint:** `/api/odoo-payment-status.php`  
**Status:** ✅ Complete  
**Last Updated:** 2026-02-03

---

## Quick Start

```javascript
// Check order payment status
const response = await fetch('/api/odoo-payment-status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        action: 'check',
        line_user_id: 'U1234567890abcdef',
        order_id: 100
    })
});

const data = await response.json();
console.log(data.payment_status);
```

---

## Request Format

### Required Parameters
```json
{
  "action": "check",
  "line_user_id": "U1234567890abcdef"
}
```

### Optional Parameters
```json
{
  "action": "check",
  "line_user_id": "U1234567890abcdef",
  "order_id": 100,        // Optional: Check specific order
  "bdo_id": 50,           // Optional: Check specific BDO
  "invoice_id": 200       // Optional: Check specific invoice
}
```

---

## Response Format

### Success (HTTP 200)
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

### Error (HTTP 400)
```json
{
  "success": false,
  "error": "LINE_USER_NOT_LINKED: กรุณาเชื่อมต่อบัญชี Odoo ก่อนใช้งาน"
}
```

---

## Common Use Cases

### 1. Check Order Payment Status

```javascript
async function checkOrderPayment(orderId) {
    const response = await fetch('/api/odoo-payment-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'check',
            line_user_id: liff.getContext().userId,
            order_id: orderId
        })
    });
    
    const data = await response.json();
    
    if (data.success) {
        const status = data.payment_status;
        
        if (status.payment_state === 'paid') {
            showMessage('ชำระเงินเรียบร้อยแล้ว');
        } else {
            showMessage(`ยังไม่ได้ชำระเงิน ยอดค้างชำระ: ${status.amount_due} บาท`);
        }
    }
}
```

### 2. Check BDO Payment Status

```javascript
async function checkBdoPayment(bdoId) {
    const response = await fetch('/api/odoo-payment-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'check',
            line_user_id: liff.getContext().userId,
            bdo_id: bdoId
        })
    });
    
    const data = await response.json();
    
    if (data.success) {
        console.log('BDO Status:', data.payment_status.bdo_state);
    }
}
```

### 3. Check Invoice Payment Status

```javascript
async function checkInvoicePayment(invoiceId) {
    const response = await fetch('/api/odoo-payment-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'check',
            line_user_id: liff.getContext().userId,
            invoice_id: invoiceId
        })
    });
    
    const data = await response.json();
    
    if (data.success) {
        const status = data.payment_status;
        updateInvoiceBadge(status.invoice_state);
        showAmountDue(status.amount_residual);
    }
}
```

### 4. Get All Pending Payments

```javascript
async function getAllPendingPayments() {
    const response = await fetch('/api/odoo-payment-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'check',
            line_user_id: liff.getContext().userId
        })
    });
    
    const data = await response.json();
    
    if (data.success && data.payment_status.pending_payments) {
        data.payment_status.pending_payments.forEach(payment => {
            console.log(`Order: ${payment.order_name}, Due: ${payment.amount_due}`);
        });
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

```javascript
async function checkPaymentWithErrorHandling(orderId) {
    try {
        const response = await fetch('/api/odoo-payment-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'check',
                line_user_id: liff.getContext().userId,
                order_id: orderId
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            if (data.error.includes('LINE_USER_NOT_LINKED')) {
                // Redirect to account linking page
                window.location.href = '/liff/odoo-link.php';
            } else if (data.error.includes('ORDER_NOT_FOUND')) {
                showError('ไม่พบออเดอร์');
            } else {
                showError(data.error);
            }
            return null;
        }
        
        return data.payment_status;
        
    } catch (error) {
        console.error('Failed to check payment status:', error);
        showError('เกิดข้อผิดพลาดในการตรวจสอบสถานะการชำระเงิน');
        return null;
    }
}
```

---

## Common Errors

| Error Code | Message | Solution |
|------------|---------|----------|
| `LINE_USER_NOT_LINKED` | กรุณาเชื่อมต่อบัญชี Odoo ก่อนใช้งาน | Link user account first |
| `ORDER_NOT_FOUND` | ไม่พบออเดอร์ | Verify order ID |
| `BDO_NOT_FOUND` | ไม่พบข้อมูล BDO | Verify BDO ID |
| `INVOICE_NOT_FOUND` | ไม่พบใบแจ้งหนี้ | Verify invoice ID |
| `CUSTOMER_MISMATCH` | ออเดอร์นี้ไม่ใช่ของคุณ | Check ownership |
| `RATE_LIMIT_EXCEEDED` | มีการเรียกใช้งานมากเกินไป | Wait and retry |

---

## Testing

```bash
# Run test script
cd re-ya
php test-payment-status-api.php
```

---

## Files

| File | Purpose |
|------|---------|
| `api/odoo-payment-status.php` | Main endpoint |
| `test-payment-status-api.php` | Test script |
| `docs/TASK_14.2_PAYMENT_STATUS_API_IMPLEMENTATION.md` | Full documentation |

---

## Integration Checklist

- [ ] Add to LIFF order detail page
- [ ] Add to LIFF invoice detail page
- [ ] Add to order tracking page
- [ ] Add to payment confirmation flow
- [ ] Test with real Odoo data
- [ ] Monitor API performance
- [ ] Set up error alerts

---

## Support

For issues or questions:
- Check full documentation: `docs/TASK_14.2_PAYMENT_STATUS_API_IMPLEMENTATION.md`
- Review design spec: `.kiro/specs/odoo-integration/design.md`
- Test with: `php test-payment-status-api.php`
