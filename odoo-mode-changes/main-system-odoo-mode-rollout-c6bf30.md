# แผนขยายโหมด Odoo สู่ระบบหลักทั้งหมด

แผนนี้จะขยายการสลับโหมดจากระดับหน้า Shop ไปสู่ Dashboard/เมนูหลัก/คลังสินค้า โดยยึดโหมด Odoo เป็นตัวกำหนดข้อมูลภาพรวม สินค้า ลูกค้า ใบแจ้งหนี้ และสต็อกแบบค่อยเป็นค่อยไปและย้อนกลับได้.

## สถานะปัจจุบัน (สำคัญ)
- มีโหมด `shop|odoo` แล้ว แต่ผูกกับ `shop_settings.order_data_source` และใช้กับหน้า Shop เป็นหลัก
- เมนูหลักเริ่มรองรับ Odoo บางส่วนแล้ว (เช่น label ออเดอร์ + Odoo Dashboard/Webhooks)
- `/dashboard` ยังเป็น executive/crm เดิม ไม่มีแท็บภาพรวม Odoo
- ระบบ inventory ยังใช้ local DB เป็นหลัก (products/stock_movements)
- มี endpoint เรียกสินค้า Odoo ได้ แต่เป็นไฟล์ standalone ที่ hardcode credential (ต้องปรับก่อนใช้เป็นแกนหลัก)

## เป้าหมายงานรอบนี้
1. หลังล็อกอินและเข้า `/dashboard` ให้เนื้อหา “เปลี่ยนตามโหมด Odoo” ชัดเจน
2. เมนูหลักกลุ่มภาพรวม/คลังสินค้า/ขาย รองรับบริบท Odoo ครบขึ้น
3. เชื่อม API สินค้า Odoo ได้จริงผ่านชั้น service ที่ปลอดภัย (ไม่ใช้ credential แบบ hardcode)
4. ทำให้เปลี่ยนโหมดราย LINE Account ได้เหมือนเดิม และ fallback กลับ Shop ได้ทันที

## แนวทางออกแบบ

### 1) ยกระดับตัวกำหนดโหมดให้ใช้ทั้งระบบ (ไม่ใช่เฉพาะ orders)
- คงการอ่านจาก `order_data_source` เดิมไว้เพื่อไม่กระทบของเก่า
- สร้าง helper กลางสำหรับ “system mode context” (ต่อ bot/account) เพื่อให้ dashboard, menu, inventory, reports ใช้ source เดียวกัน
- เพิ่ม utility สำหรับส่งสถานะ mode ไปหน้า frontend ที่ต้องแสดงผลต่างกัน

### 2) Dashboard กลาง (`/dashboard`) ให้มี Odoo Overview
- เพิ่มแท็บใหม่เช่น `tab=odoo-overview` และ map เป็น default เมื่ออยู่ Odoo mode
- ข้อมูลการ์ดหลักที่ต้องมี:
  - สินค้า (active/stock signal)
  - ลูกค้า/ผู้ใช้ (จาก users + link กับ Odoo หากมี)
  - ใบแจ้งหนี้ (open/overdue/paid)
  - ออเดอร์/ยอดขาย (วันนี้/เดือน)
- ถ้า endpoint ใดไม่พร้อม: แสดงค่า 0 + คำอธิบาย fallback (ไม่พังทั้งหน้า)

### 3) เมนูหลักตามโหมด Odoo
- ปรับกลุ่ม “ภาพรวมและสถิติ” ให้แสดงทางเข้า Odoo Overview ชัดเจนเมื่อ `isOdooMode=true`
- คงเมนูเดิมไว้ในโหมด Shop เพื่อ backward compatibility
- กลุ่มคลัง/ขาย:
  - เปลี่ยนชื่อเมนูตามโหมด (ทำบางส่วนแล้ว)
  - เพิ่มทางลัด Invoice/Credit/Odoo Product Sync (เฉพาะ owner/admin)

### 4) Inventory + Product Integration กับ Odoo API
- ทำ `OdooProductService` (หรือขยาย `OdooAPIClient`) สำหรับดึงสินค้าตามรูปแบบ JSON ที่ส่งมา (รองรับ `product_price_ids`, `saleable_qty`, vendor)
- วาง “proxy endpoint ภายใน” สำหรับ inventory/shop ใช้ร่วมกัน (ตรวจสิทธิ์ + sanitize + timeout + retry)
- กลยุทธ์ข้อมูลสินค้า (เสนอ 2 ชั้น):
  1) **Read-through cache**: เรียก Odoo แล้ว cache local ชั่วคราว (เร็ว + ลดโหลด)
  2) **Scheduled sync**: cron sync รายชั่วโมง/รายวันสำหรับหน้ารายงานหนัก
- โหมดแรกให้เป็น read-only ต่อ stock ฝั่ง Odoo จนกว่าจะยืนยัน write-back

### 5) ความปลอดภัย/API Governance
- ย้าย credential Odoo ไป config/env ทันที
- ปิดการพึ่งพา endpoint standalone ที่ hardcode token สำหรับเส้นทาง production
- เพิ่ม error mapping ที่ user-friendly และ logging ต่อ account/bot

## ลำดับลงมือ (Implementation Order)
1. สร้าง/ปรับ Mode Context Helper ให้ dashboard/menu/inventory เรียกใช้ร่วมกัน
2. เพิ่ม Odoo Overview tab ใน `/dashboard` + default tab ตามโหมด
3. ปรับเมนูหลักให้สลับตามโหมดครบกลุ่มภาพรวม/ขาย/คลัง
4. สร้าง Odoo Product Service + proxy endpoint สำหรับสินค้า
5. ผูก inventory products tab ให้รองรับ Odoo source (read-only + fallback)
6. เพิ่ม monitoring/logging และ test regression (shop mode ต้องไม่พัง)

## Test Plan (ย่อ)
- Shop mode regression: `/dashboard`, `/shop/*`, `/inventory` ต้อง behavior เดิม
- Odoo mode:
  - `/dashboard` เข้า Odoo Overview อัตโนมัติ
  - summary cards แสดงข้อมูล Odoo/fallback ถูกต้อง
  - inventory products ดึงจาก Odoo API ได้ และไม่เขียนทับ local โดยไม่ตั้งใจ
- Multi-account: สลับ bot แล้ว mode/per-account ไม่ปนกัน

## คำถามยืนยันก่อนเริ่มแก้โค้ด
1. ใน Odoo mode ต้องการให้หน้าแรกหลังล็อกอินเป็น `/dashboard?tab=odoo-overview` สำหรับทุก role หรือเฉพาะ owner/admin?
2. Inventory Odoo รอบแรกยืนยันเป็น **read-only 100%** ก่อนใช่ไหม (ยังไม่อัปเดตสต็อกกลับ Odoo)?
3. ต้องการดึง “ใบแจ้งหนี้” จาก endpoint ที่มีอยู่ตอนนี้ก่อน (`/api/odoo-invoices.php`) หรือมี endpoint ใหม่ที่อยากให้ใช้แทน?
4. ข้อมูล “ผู้ใช้” ใน Odoo Overview ให้ใช้ฐาน `users` เดิม + สถานะเชื่อม Odoo ก่อน ถูกต้องไหม?
5. ยืนยันให้ผมปิดการใช้ credential hardcoded ในเส้นทาง production ทันทีได้เลยหรือไม่?
