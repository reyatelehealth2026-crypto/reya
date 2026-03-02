# คำขอ Resend Webhooks ที่หายไป

## สรุป

Webhooks ที่ส่งมาในวันที่ **16 กุมภาพันธ์ 2026 เวลา 12:30-12:31 น.** ไม่ได้ถูกบันทึกในระบบของเรา เนื่องจากมีปัญหาทางเทคนิค

## รายละเอียด

### Webhooks ที่หายไป
- **วันที่:** 16 กุมภาพันธ์ 2026
- **เวลา:** 12:30:36 - 12:31:19 น. (Asia/Bangkok)
- **Events ที่ตรวจพบ:**
  - `order.to_delivery` (12:30:36)
  - `invoice.created` (12:31:19)

### สาเหตุ
- Webhook endpoint ของเรา return 200 OK แต่ไม่ได้บันทึกลงฐานข้อมูล (silent failure)
- ปัญหาได้รับการแก้ไขแล้วเมื่อ 16 ก.พ. 2026 เวลา 12:46 น.

## คำขอ

กรุณา **resend webhooks** สำหรับ:

### 1. ช่วงเวลาที่ต้องการ
```
Start: 2026-02-16 12:30:00 (Asia/Bangkok)
End:   2026-02-16 12:32:00 (Asia/Bangkok)
```

หรือในรูปแบบ UTC:
```
Start: 2026-02-16 05:30:00 (UTC)
End:   2026-02-16 05:32:00 (UTC)
```

### 2. Event Types ที่ต้องการ
- ทุก event types (ถ้าเป็นไปได้)
- หรืออย่างน้อย: `order.*`, `invoice.*`

### 3. Orders ที่อาจได้รับผลกระทบ
กรุณาตรวจสอบ orders ที่มี state changes ในช่วงเวลาดังกล่าว:

```sql
-- Query สำหรับทีม Odoo (ตัวอย่าง)
SELECT 
    id,
    name,
    state,
    write_date
FROM sale_order
WHERE write_date BETWEEN '2026-02-16 05:30:00' AND '2026-02-16 05:32:00'
ORDER BY write_date;
```

## วิธีการ Resend

### Option 1: Manual Resend (แนะนำ)
ใช้ Odoo webhook management interface เพื่อ resend webhooks ที่เฉพาะเจาะจง

### Option 2: Replay from Log
ถ้า Odoo เก็บ webhook delivery log ไว้ สามารถ replay จาก log ได้

### Option 3: Trigger State Change
Force trigger state change events สำหรับ orders ที่ได้รับผลกระทบ

## ข้อมูลเพิ่มเติม

### Webhook Endpoint
```
URL: https://cny.re-ya.com/api/webhook/odoo.php
Status: ✅ Working (แก้ไขแล้ว)
```

### การยืนยันว่าระบบพร้อม
Webhook ล่าสุดที่ทำงานสำเร็จ:
```
Delivery ID: wh_8f52248570cb4efc
Event: order.picking
Time: 2026-02-16 12:46:07
Status: ✅ Success (บันทึกลงฐานข้อมูลแล้ว)
```

### ผลกระทบ
- ลูกค้าไม่ได้รับ LINE notifications สำหรับ order updates
- ข้อมูล order timeline ไม่สมบูรณ์
- Analytics และ reporting อาจไม่ถูกต้อง

## Timeline

| เวลา | เหตุการณ์ |
|------|-----------|
| 12:30-12:31 | Webhooks เข้ามาแต่ไม่ถูกบันทึก |
| 12:44 | ตรวจพบปัญหา |
| 12:46 | แก้ไขปัญหาเสร็จสิ้น |
| 12:46+ | Webhooks ทำงานปกติ |

## Contact

หากมีคำถามหรือต้องการข้อมูลเพิ่มเติม กรุณาติดต่อ:
- Email: [your-email]
- LINE: [your-line-id]
- Phone: [your-phone]

## Verification

หลังจาก resend webhooks แล้ว เราจะตรวจสอบและยืนยันว่า:
- ✅ Webhooks ถูกรับและบันทึกสำเร็จ
- ✅ ลูกค้าได้รับ notifications
- ✅ Order timeline สมบูรณ์

---

**หมายเหตุ:** ปัญหานี้ได้รับการแก้ไขแล้ว และจะไม่เกิดขึ้นอีก เนื่องจากเราได้ปรับปรุง error handling ให้ webhook endpoint return error (500) แทน success (200) เมื่อไม่สามารถบันทึกลงฐานข้อมูลได้
