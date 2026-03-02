# Odoo API Bug Report - Date Comparison Error

## 🐛 ปัญหา

API endpoints ต่อไปนี้มี TypeError เมื่อเปรียบเทียบ date:

1. `/reya/credit-status`
2. `/reya/invoices`

## ❌ Error Message

```
TypeError: '<' not supported between instances of 'str' and 'datetime.date'
```

## 📍 ตำแหน่งที่เกิด Error

### File: `ineco_retail_moduls/cny_reya_connector/controllers/line_api.py`

#### 1. Line 327 (get_credit_status)
```python
lambda i: i.date_due and i.date_due < datetime.now().date()
```

#### 2. Line 254 (get_invoices)
```python
is_overdue = inv.state == 'open' and inv.date_due and inv.date_due < datetime.now().date()
```

## 🔍 สาเหตุ

`date_due` เป็น **string** แต่ถูกเปรียบเทียบกับ `datetime.date` object โดยตรง

Python ไม่สามารถเปรียบเทียบ string กับ date object ได้

## ✅ วิธีแก้ไข

### Option 1: Convert string to date before comparison

```python
from datetime import datetime

# Helper function
def parse_date(date_value):
    """Convert string or date to date object"""
    if isinstance(date_value, str):
        return datetime.strptime(date_value, '%Y-%m-%d').date()
    return date_value

# ใช้งาน
date_due_obj = parse_date(i.date_due)
is_overdue = date_due_obj and date_due_obj < datetime.now().date()
```

### Option 2: Ensure date_due is always date object

ตรวจสอบว่า `date_due` field ใน Odoo model เป็น `fields.Date` ไม่ใช่ `fields.Char`

## 🔧 Code Fix

### Fix for get_credit_status (Line 327)

```python
# Before (❌ ผิด)
overdue_invoices = invoices.filtered(
    lambda i: i.date_due and i.date_due < datetime.now().date())

# After (✅ ถูก)
def is_overdue(invoice):
    if not invoice.date_due:
        return False
    due_date = invoice.date_due
    if isinstance(due_date, str):
        due_date = datetime.strptime(due_date, '%Y-%m-%d').date()
    return due_date < datetime.now().date()

overdue_invoices = invoices.filtered(is_overdue)
```

### Fix for get_invoices (Line 254)

```python
# Before (❌ ผิด)
is_overdue = inv.state == 'open' and inv.date_due and inv.date_due < datetime.now().date()

# After (✅ ถูก)
is_overdue = False
if inv.state == 'open' and inv.date_due:
    due_date = inv.date_due
    if isinstance(due_date, str):
        due_date = datetime.strptime(due_date, '%Y-%m-%d').date()
    is_overdue = due_date < datetime.now().date()
```

## 📊 Impact

### Affected Endpoints
- ❌ `/reya/credit-status` - ไม่สามารถดูสถานะเครดิตได้
- ❌ `/reya/invoices` - ไม่สามารถดูรายการใบแจ้งหนี้ได้

### Working Endpoints
- ✅ `/reya/user/profile` - ทำงานปกติ
- ✅ `/reya/orders` - ทำงานปกติ

## 🧪 Testing

หลังแก้ไขแล้ว ทดสอบด้วย:

```bash
# Test credit status
curl -X POST "https://erp.cnyrxapp.com/reya/credit-status" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 5pG-doAH1EEqHXurM3t466WAgKRYemmK7FANAB74o5A" \
  -d '{"line_user_id":"U1234567890"}'

# Test invoices
curl -X POST "https://erp.cnyrxapp.com/reya/invoices" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 5pG-doAH1EEqHXurM3t466WAgKRYemmK7FANAB74o5A" \
  -d '{"line_user_id":"U1234567890","page":1,"limit":10}'
```

## 📋 Checklist

- [ ] แก้ไข `get_credit_status` method (line 327)
- [ ] แก้ไข `get_invoices` method (line 254)
- [ ] ทดสอบ `/reya/credit-status` endpoint
- [ ] ทดสอบ `/reya/invoices` endpoint
- [ ] ตรวจสอบ date fields อื่นๆ ที่อาจมีปัญหาเดียวกัน
- [ ] Deploy ขึ้น production
- [ ] แจ้งทีม Re-Ya ว่าแก้ไขเสร็จแล้ว

## 🔗 Related

- Module: `cny_reya_connector`
- File: `controllers/line_api.py`
- Odoo Version: 11
- Python Version: 3.x

---

**Priority**: High  
**Status**: Pending Fix  
**Reported**: 16 Feb 2026  
**Reporter**: Re-Ya Team
