# คู่มือกู้คืน Webhooks ที่หายไป

## สถานการณ์

Webhooks วันที่ 16 ก.พ. 2026 เวลา 12:30-12:31 น. หายไป เพราะ:
1. Webhook endpoint return 200 OK (Odoo คิดว่าสำเร็จ)
2. แต่ไม่ได้บันทึกลงฐานข้อมูล (silent failure)
3. Odoo ไม่ retry เพราะคิดว่าส่งสำเร็จแล้ว

## วิธีกู้คืนข้อมูล

### วิธีที่ 1: ขอให้ Odoo Resend (แนะนำ) ⭐

**ขั้นตอน:**
1. ส่งเอกสาร `REQUEST_WEBHOOK_RESEND.md` ให้ทีม Odoo
2. ระบุช่วงเวลา: 16 ก.พ. 2026, 12:30-12:31 น.
3. ขอ resend ทุก events ในช่วงเวลานั้น

**ข้อดี:**
- ✅ ได้ข้อมูลครบถ้วน
- ✅ ไม่ต้องทำอะไรเอง
- ✅ Webhooks จะถูกส่งมาใหม่อัตโนมัติ

**ข้อเสีย:**
- ⏳ ต้องรอทีม Odoo ดำเนินการ
- ⏳ อาจใช้เวลา 1-2 วัน

### วิธีที่ 2: ดึงข้อมูลจาก Odoo API

**ขั้นตอน:**
1. ดึงรายการ orders ที่ update ในช่วงเวลานั้น
2. ดึง order details จาก Odoo API
3. สร้าง webhook events เองและบันทึกลงฐานข้อมูล

**ตัวอย่าง Script:**

```php
<?php
// tools/recover_missing_webhooks.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/OdooAPIClient.php';

$db = Database::getInstance()->getConnection();
$odooClient = new OdooAPIClient();

// ช่วงเวลาที่ webhooks หายไป
$startTime = '2026-02-16 12:30:00';
$endTime = '2026-02-16 12:32:00';

echo "กำลังค้นหา orders ที่ update ในช่วง $startTime - $endTime...\n";

// ดึง orders จาก Odoo
$orders = $odooClient->searchOrders([
    'write_date' => ['>=', $startTime, '<=', $endTime]
]);

echo "พบ " . count($orders) . " orders\n\n";

foreach ($orders as $order) {
    echo "Processing Order: {$order['name']}\n";
    
    // ดึง order details
    $orderDetails = $odooClient->getOrderDetails($order['id']);
    
    // สร้าง webhook payload
    $payload = [
        'event' => 'order.state_change',
        'timestamp' => $order['write_date'],
        'data' => [
            'order_id' => $order['id'],
            'order_name' => $order['name'],
            'state' => $order['state'],
            'customer' => [
                'id' => $order['partner_id'][0],
                'name' => $order['partner_id'][1]
            ],
            'amount_total' => $order['amount_total']
        ]
    ];
    
    // บันทึกลงฐานข้อมูล
    $stmt = $db->prepare("
        INSERT INTO odoo_webhooks_log 
        (delivery_id, event_type, payload, status, processed_at, order_id)
        VALUES (?, ?, ?, 'success', ?, ?)
        ON DUPLICATE KEY UPDATE processed_at = VALUES(processed_at)
    ");
    
    $deliveryId = 'recovered_' . $order['id'] . '_' . time();
    $stmt->execute([
        $deliveryId,
        'order.state_change',
        json_encode($payload),
        $order['write_date'],
        $order['id']
    ]);
    
    echo "  ✓ บันทึกแล้ว (delivery_id: $deliveryId)\n";
}

echo "\nเสร็จสิ้น!\n";
```

**ข้อดี:**
- ✅ ทำได้เองทันที
- ✅ ไม่ต้องรอทีม Odoo

**ข้อเสีย:**
- ⚠️ ต้องเขียน script เอง
- ⚠️ อาจไม่ได้ข้อมูลครบถ้วน 100%
- ⚠️ ต้องรู้ว่า orders ไหนบ้างที่ได้รับผลกระทบ

### วิธีที่ 3: ยอมรับว่าข้อมูลหายไป

**เมื่อไหร่ควรใช้:**
- Webhooks ที่หายไปไม่สำคัญมาก
- ข้อมูลเก่าเกินไป (เกิน 1 เดือน)
- ไม่มีผลกระทบต่อลูกค้า

**ข้อดี:**
- ✅ ไม่ต้องทำอะไร
- ✅ ประหยัดเวลา

**ข้อเสีย:**
- ❌ ข้อมูลไม่สมบูรณ์
- ❌ Analytics อาจไม่ถูกต้อง

## คำแนะนำ

### สำหรับกรณีนี้ (16 ก.พ. 2026, 12:30-12:31)

**แนะนำ: วิธีที่ 1 (ขอให้ Odoo Resend)**

เพราะ:
1. ✅ ช่วงเวลาสั้น (แค่ 1-2 นาที)
2. ✅ เกิดเมื่อไม่นานมานี้ (วันเดียวกัน)
3. ✅ Odoo น่าจะยังมี log อยู่
4. ✅ ได้ข้อมูลครบถ้วนที่สุด

### ขั้นตอนที่แนะนำ

1. **ส่งคำขอไปยัง Odoo ทันที**
   - ใช้เอกสาร `REQUEST_WEBHOOK_RESEND.md`
   - ระบุช่วงเวลาชัดเจน
   - แนบ log ที่แสดงว่า webhooks เข้ามา

2. **ตรวจสอบว่าระบบพร้อมรับ**
   ```sql
   -- ตรวจสอบว่า webhooks ใหม่ถูกบันทึก
   SELECT COUNT(*) FROM odoo_webhooks_log 
   WHERE processed_at >= '2026-02-16 12:46:00';
   ```

3. **รอ Odoo resend**
   - ติดตามสถานะกับทีม Odoo
   - ตรวจสอบ logs เมื่อ webhooks เข้ามา

4. **ยืนยันว่าได้รับครบ**
   ```sql
   -- ตรวจสอบ webhooks ที่ได้รับ
   SELECT 
       delivery_id,
       event_type,
       processed_at,
       status
   FROM odoo_webhooks_log
   WHERE processed_at BETWEEN '2026-02-16 12:30:00' AND '2026-02-16 12:32:00'
   ORDER BY processed_at;
   ```

## ป้องกันไม่ให้เกิดอีก

การแก้ไขที่ทำไปแล้ว:
- ✅ แก้ silent failure ใน `logWebhook()`
- ✅ Webhook endpoint จะ return 500 ถ้าบันทึกไม่ได้
- ✅ Odoo จะ retry อัตโนมัติ
- ✅ เพิ่ม detailed error logging

**ปัญหานี้จะไม่เกิดขึ้นอีก!** 🎉

## สรุป

| วิธี | เวลา | ความยาก | ความสมบูรณ์ | แนะนำ |
|------|------|---------|--------------|-------|
| 1. Odoo Resend | 1-2 วัน | ง่าย | 100% | ⭐⭐⭐⭐⭐ |
| 2. API Recovery | 2-4 ชม. | ยาก | 80-90% | ⭐⭐⭐ |
| 3. ยอมรับ | 0 | ง่าย | 0% | ⭐ |

**คำแนะนำสุดท้าย:** ใช้วิธีที่ 1 และส่งคำขอไปยัง Odoo ทันที!
