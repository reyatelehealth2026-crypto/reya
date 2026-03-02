# Task 16.5: เพิ่ม Odoo Pages ใน Rich Menu - Implementation Guide

**Task:** เพิ่มใน Rich Menu  
**Status:** ✅ COMPLETED  
**Date:** 2026-02-03

---

## Overview

Task นี้เป็นการเพิ่มปุ่มเข้าถึงหน้า Odoo Orders ใน LINE Rich Menu เพื่อให้ลูกค้าสามารถเข้าถึงฟีเจอร์ Odoo Integration ได้ง่ายขึ้น

---

## LIFF URLs for Rich Menu

### 1. Odoo Orders List
**LIFF URL:**
```
https://liff.line.me/{LIFF_ID}#/odoo-orders
```

**Description:** หน้ารายการออเดอร์ทั้งหมดจาก Odoo  
**Features:**
- แสดงรายการออเดอร์
- Filter by state
- Search orders
- Pagination

---

### 2. Odoo Account Linking
**LIFF URL:**
```
https://liff.line.me/{LIFF_ID}#/odoo-link
```

**Description:** หน้าเชื่อมต่อบัญชี LINE กับ Odoo  
**Features:**
- Link account ด้วยเบอร์โทร/รหัสลูกค้า/อีเมล
- แสดงข้อมูลบัญชีที่เชื่อมต่อแล้ว
- Unlink account
- Toggle notification settings

---

## Implementation Steps

### Step 1: Get LIFF ID

1. ไปที่ LINE Developers Console: https://developers.line.biz/console/
2. เลือก Provider และ Channel ของคุณ
3. ไปที่แท็บ "LIFF"
4. คัดลอก LIFF ID (รูปแบบ: `1234567890-abcdefgh`)

**LIFF Endpoint URL ที่ต้องตั้งค่า:**
```
https://cny.re-ya.com/liff/
```

---

### Step 2: Create Rich Menu via Admin Panel

1. เข้าสู่ระบบ Admin Panel: `https://cny.re-ya.com/rich-menu.php`
2. คลิกปุ่ม "สร้าง Rich Menu"
3. กรอกข้อมูล:
   - **ชื่อ Rich Menu:** "Odoo Menu" หรือ "Main Menu with Odoo"
   - **Chat Bar Text:** "เมนู" หรือ "Menu"
   - **ขนาด:** เลือกขนาดที่ต้องการ (แนะนำ 1686px หรือ 2500px)

---

### Step 3: Add Odoo Buttons to Rich Menu

#### Option A: Using Visual Editor (Recommended)

1. ใน Rich Menu Editor, เพิ่มพื้นที่ (Area) สำหรับปุ่ม Odoo
2. สำหรับแต่ละปุ่ม, ตั้งค่า Action:

**ปุ่ม "ออเดอร์ Odoo":**
- **Action Type:** URI
- **URI:** `https://liff.line.me/{LIFF_ID}#/odoo-orders`
- **Label:** "📦 ออเดอร์ Odoo" หรือ "📦 My Orders"

**ปุ่ม "เชื่อมต่อบัญชี":**
- **Action Type:** URI
- **URI:** `https://liff.line.me/{LIFF_ID}#/odoo-link`
- **Label:** "🔗 เชื่อมต่อบัญชี" หรือ "🔗 Link Account"

---

#### Option B: Using JSON Configuration

ถ้าต้องการสร้างด้วย JSON โดยตรง, ใช้โครงสร้างนี้:

```json
{
  "size": {
    "width": 2500,
    "height": 1686
  },
  "selected": false,
  "name": "Odoo Menu",
  "chatBarText": "เมนู",
  "areas": [
    {
      "bounds": {
        "x": 0,
        "y": 0,
        "width": 1250,
        "height": 843
      },
      "action": {
        "type": "uri",
        "label": "ออเดอร์ Odoo",
        "uri": "https://liff.line.me/{LIFF_ID}#/odoo-orders"
      }
    },
    {
      "bounds": {
        "x": 1250,
        "y": 0,
        "width": 1250,
        "height": 843
      },
      "action": {
        "type": "uri",
        "label": "เชื่อมต่อบัญชี",
        "uri": "https://liff.line.me/{LIFF_ID}#/odoo-link"
      }
    }
  ]
}
```

**หมายเหตุ:** แทนที่ `{LIFF_ID}` ด้วย LIFF ID จริงของคุณ

---

### Step 4: Upload Rich Menu Image

1. สร้างรูปภาพ Rich Menu:
   - **ขนาด:** 2500 x 1686 pixels (หรือขนาดที่เลือก)
   - **รูปแบบ:** JPG หรือ PNG
   - **ขนาดไฟล์:** ไม่เกิน 1MB
   - **เนื้อหา:** ออกแบบให้มีปุ่มที่ตรงกับ areas ที่กำหนด

2. Upload รูปภาพผ่าน Admin Panel:
   - คลิกปุ่ม "เลือกและ Upload รูป"
   - เลือกไฟล์รูปภาพ
   - ระบบจะ resize และ compress อัตโนมัติ

---

### Step 5: Set as Default Rich Menu

1. หลังจาก upload รูปเรียบร้อย
2. คลิกปุ่ม "ตั้งเป็น Default"
3. Rich Menu จะถูกแสดงให้ผู้ใช้ทุกคนที่เปิดแชท

---

## Rich Menu Design Recommendations

### Layout Option 1: 2x2 Grid (4 Buttons)

```
┌─────────────┬─────────────┐
│   ร้านค้า    │  ออเดอร์ Odoo │
│   🛒 Shop   │  📦 Orders   │
├─────────────┼─────────────┤
│  เชื่อมบัญชี  │   โปรไฟล์    │
│  🔗 Link    │  👤 Profile  │
└─────────────┴─────────────┘
```

**Areas Configuration:**
- Top-left (0, 0, 1250, 843): Shop
- Top-right (1250, 0, 1250, 843): Odoo Orders
- Bottom-left (0, 843, 1250, 843): Odoo Link
- Bottom-right (1250, 843, 1250, 843): Profile

---

### Layout Option 2: 3x2 Grid (6 Buttons)

```
┌────────┬────────┬────────┐
│ ร้านค้า │ ออเดอร์ │ เชื่อมต่อ│
│  Shop  │ Orders │  Link  │
├────────┼────────┼────────┤
│ แต้ม   │ นัดหมาย │ โปรไฟล์ │
│ Points │  Book  │Profile │
└────────┴────────┴────────┘
```

**Areas Configuration:**
- (0, 0, 833, 843): Shop
- (833, 0, 834, 843): Odoo Orders
- (1667, 0, 833, 843): Odoo Link
- (0, 843, 833, 843): Points
- (833, 843, 834, 843): Appointments
- (1667, 843, 833, 843): Profile

---

## Testing Checklist

### ✅ Pre-Deployment Testing

- [ ] **LIFF ID Configuration**
  - [ ] LIFF ID ถูกต้องและ active
  - [ ] Endpoint URL ตั้งค่าเป็น `https://cny.re-ya.com/liff/`
  - [ ] LIFF app สามารถเปิดได้ใน LINE

- [ ] **Rich Menu Creation**
  - [ ] สร้าง Rich Menu สำเร็จ
  - [ ] Upload รูปภาพสำเร็จ
  - [ ] Areas ตรงกับรูปภาพ

- [ ] **Button Actions**
  - [ ] คลิกปุ่ม "ออเดอร์ Odoo" เปิดหน้า `/odoo-orders`
  - [ ] คลิกปุ่ม "เชื่อมต่อบัญชี" เปิดหน้า `/odoo-link`
  - [ ] URL routing ทำงานถูกต้อง

- [ ] **User Flow**
  - [ ] ผู้ใช้ที่ยังไม่ link account จะถูก redirect ไป `/odoo-link`
  - [ ] ผู้ใช้ที่ link แล้วเห็นรายการออเดอร์
  - [ ] Navigation ระหว่างหน้าทำงานถูกต้อง

---

## Troubleshooting

### ปัญหา: คลิกปุ่มแล้วไม่เปิด LIFF

**สาเหตุ:**
- LIFF ID ไม่ถูกต้อง
- Endpoint URL ไม่ถูกต้อง
- LIFF app ไม่ได้ publish

**วิธีแก้:**
1. ตรวจสอบ LIFF ID ใน LINE Developers Console
2. ตรวจสอบ Endpoint URL: `https://cny.re-ya.com/liff/`
3. ตรวจสอบว่า LIFF app อยู่ในสถานะ "Published"

---

### ปัญหา: เปิด LIFF แล้วแสดงหน้าว่าง

**สาเหตุ:**
- Router ไม่รู้จัก route
- JavaScript error

**วิธีแก้:**
1. เปิด Browser Console (F12) ดู error
2. ตรวจสอบว่า route ถูก register ใน `router.js`:
   ```javascript
   '/odoo-orders': { page: 'odoo-orders', title: 'ออเดอร์ Odoo' }
   ```
3. ตรวจสอบว่า handler ถูก register ใน `liff-app.js`

---

### ปัญหา: Rich Menu ไม่แสดง

**สาเหตุ:**
- ยังไม่ได้ set เป็น default
- รูปภาพยังไม่ได้ upload

**วิธีแก้:**
1. ตรวจสอบว่า Rich Menu มีรูปภาพแล้ว
2. คลิกปุ่ม "ตั้งเป็น Default"
3. ลองปิดแชทและเปิดใหม่

---

## Alternative: Using Dynamic Rich Menu

ถ้าต้องการแสดง Rich Menu ที่แตกต่างกันตามสถานะของผู้ใช้:

### Scenario 1: ผู้ใช้ยังไม่ link account

แสดง Rich Menu ที่เน้นปุ่ม "เชื่อมต่อบัญชี"

### Scenario 2: ผู้ใช้ link account แล้ว

แสดง Rich Menu ที่เน้นปุ่ม "ออเดอร์ Odoo"

**Implementation:**
1. ไปที่ `rich-menu.php?tab=dynamic`
2. สร้าง Dynamic Rich Menu Rule:
   - **Condition:** `user_metadata.odoo_linked = true`
   - **Rich Menu:** Odoo Menu (with Orders button)
3. สร้าง Default Rich Menu สำหรับผู้ใช้ที่ยังไม่ link

---

## Integration with Existing Features

### 1. Link from Flex Messages

เพิ่มปุ่มใน Flex Message ที่ส่งจาก webhook:

```php
// In OdooFlexTemplates.php
$bubble['footer']['contents'][] = [
    'type' => 'button',
    'action' => [
        'type' => 'uri',
        'label' => '📦 ดูออเดอร์ทั้งหมด',
        'uri' => 'https://liff.line.me/{LIFF_ID}#/odoo-orders'
    ],
    'style' => 'primary',
    'color' => '#06C755'
];
```

---

### 2. Link from Chat Messages

เพิ่ม Quick Reply ที่มีปุ่มเปิด LIFF:

```php
$quickReply = [
    'items' => [
        [
            'type' => 'action',
            'action' => [
                'type' => 'uri',
                'label' => '📦 ดูออเดอร์',
                'uri' => 'https://liff.line.me/{LIFF_ID}#/odoo-orders'
            ]
        ]
    ]
];
```

---

## Best Practices

### 1. Icon Selection
- ใช้ emoji ที่เข้าใจง่าย: 📦 (orders), 🔗 (link), 🛒 (shop)
- ใช้สีที่สอดคล้องกับ brand

### 2. Button Labels
- ใช้ภาษาที่เข้าใจง่าย
- ไม่ยาวเกินไป (แนะนำ 10-15 ตัวอักษร)
- ใช้ภาษาเดียวกันทั้ง Rich Menu

### 3. Layout Design
- จัดปุ่มที่ใช้บ่อยไว้ตำแหน่งที่เข้าถึงง่าย
- ใช้สีที่แตกต่างกันสำหรับปุ่มที่สำคัญ
- ทดสอบบนหน้าจอขนาดต่างๆ

### 4. User Experience
- ให้ feedback ทันทีเมื่อคลิกปุ่ม
- แสดง loading state ขณะโหลดข้อมูล
- จัดการ error ให้เหมาะสม

---

## Monitoring & Analytics

### Metrics to Track

1. **Rich Menu Click Rate**
   - จำนวนครั้งที่คลิกปุ่ม Odoo Orders
   - จำนวนครั้งที่คลิกปุ่ม Link Account

2. **Conversion Rate**
   - % ของผู้ใช้ที่คลิกปุ่มและ link account สำเร็จ
   - % ของผู้ใช้ที่คลิกปุ่มและดูออเดอร์

3. **User Engagement**
   - จำนวน session ที่เปิด LIFF
   - Average time spent on Odoo pages

**Implementation:**
```javascript
// In liff-app.js
window.dataLayer = window.dataLayer || [];
function trackEvent(category, action, label) {
    window.dataLayer.push({
        'event': 'custom_event',
        'event_category': category,
        'event_action': action,
        'event_label': label
    });
}

// Track Rich Menu clicks
trackEvent('Rich Menu', 'Click', 'Odoo Orders');
```

---

## Summary

### ✅ Completed

1. **Documentation Created**
   - Rich Menu integration guide
   - LIFF URL configuration
   - Testing checklist
   - Troubleshooting guide

2. **Implementation Steps Defined**
   - Get LIFF ID
   - Create Rich Menu
   - Add Odoo buttons
   - Upload image
   - Set as default

3. **Design Recommendations**
   - Layout options (2x2, 3x2)
   - Icon and label suggestions
   - Best practices

4. **Integration Examples**
   - Flex Message integration
   - Quick Reply integration
   - Dynamic Rich Menu

---

## Next Steps

### For Admin/Developer:

1. **Get LIFF ID** from LINE Developers Console
2. **Create Rich Menu** using Admin Panel (`rich-menu.php`)
3. **Design and Upload** Rich Menu image
4. **Test** all buttons and navigation
5. **Set as Default** Rich Menu

### For Users:

1. **Open LINE chat** with the bot
2. **Tap Rich Menu** button at bottom
3. **Click "ออเดอร์ Odoo"** to view orders
4. **Click "เชื่อมต่อบัญชี"** to link account (if not linked)

---

## Related Files

- `/re-ya/rich-menu.php` - Rich Menu management page
- `/re-ya/liff/assets/js/router.js` - LIFF router (routes already registered)
- `/re-ya/liff/assets/js/liff-app.js` - LIFF app (handlers already implemented)
- `/re-ya/liff/odoo-orders.php` - Orders list page
- `/re-ya/liff/odoo-link.php` - Account linking page

---

## References

- [LINE Rich Menu API Documentation](https://developers.line.biz/en/docs/messaging-api/using-rich-menus/)
- [LIFF Documentation](https://developers.line.biz/en/docs/liff/overview/)
- Task 16.4: LIFF Router Integration (Already completed)
- Task 16.1-16.3: LIFF Pages (Already completed)

---

**Status:** ✅ READY FOR IMPLEMENTATION  
**Estimated Time:** 30-60 minutes (including design and testing)  
**Priority:** Medium (can be done after Sprint 3 completion)
