# สรุปและอัปเดตสถานะโปรเจกต์ Re-Ya (ฉบับละเอียด)

> อัปเดต ณ วันที่: **2026-02-26 (หลัง Odoo Production Go-Live)**  
> ขอบเขต: โครงสร้างทั้งระบบ (`api/`, `classes/`, `liff/`, `shop/`, `cron/`, webhook, dashboard, docs)  
> วิธีประเมิน: **อิงโค้ดเป็นหลัก (code-first status)**

---

## 1) Executive Summary (ภาพรวมผู้บริหาร)

โปรเจกต์อยู่ในสถานะ **ใช้งานจริงระดับ production หลายโมดูลหลัก** และล่าสุดฝั่ง Odoo ถูกเปิดใช้ production แล้วตาม config/runtime path ในโค้ด แต่ยังมีงาน “**post-go-live hardening**” ที่ต้องเร่ง เช่น การลด debug logs, ปรับ webhook security defaults, ปรับ lock/concurrency ของ scheduler, และเพิ่ม monitoring/alert ให้เข้มขึ้น

### สรุปสถานะรวม
- **เสร็จแล้ว (Code Complete / Production-capable หลายส่วน):**
  - โครงสร้างระบบหลักและโมดูลหลัก (CRM, Shop, Odoo webhook/API/dashboard, LIFF routing)
  - กลไก scheduled broadcast แบบ background trigger จากหลายจุด
  - Odoo webhook observability (status, retry, duplicate, latency, dashboard API)
  - Odoo environment/config ถูกชี้ไป production และ API client ใช้ active production base URL
- **พร้อมใช้งานแต่ต้อง config/deploy:**
  - Rich Menu Odoo (เอกสารครบ แต่ต้องให้ Admin ตั้งค่าจริง)
- **ยังมีช่องโหว่เชิงความสมบูรณ์:**
  - webhook legacy drift mode ถูกเปิด default ควร tighten หลัง go-live
  - scheduler มีความเสี่ยง concurrency/double-processing ในบางจังหวะ

---

## 2) Snapshot สภาพระบบปัจจุบัน (เชิงโครงสร้าง)

อ้างอิงจากโครงสร้างไฟล์จริง:
- `api/` ประมาณ 89 endpoints
- `classes/` ประมาณ 91 classes/services
- `liff/` ประมาณ 30 ไฟล์ (SPA + pages)
- `cron/` ประมาณ 14 jobs
- `docs/` ประมาณ 65 ไฟล์เอกสาร
- `tests/` มีหลายหมวดย่อย (AIChat, LandingPage, LiffTelepharmacy, Inventory, Inbox, ฯลฯ)

หมายเหตุ: ตัวเลขนี้เป็น snapshot ณ วันที่จัดทำเอกสาร อาจเปลี่ยนตาม commit ใหม่

---

## 3) Module Status Matrix (สถานะรายหมวด)

| หมวด | สถานะรวม | สิ่งที่ทำถึงแล้ว | สิ่งที่ยังค้าง/ยังไม่สมบูรณ์ |
|---|---|---|---|
| CRM / Inbox / LINE Webhook | พร้อมใช้งานสูง | Multi-account webhook, event handling, dedup event id, logging | มี debug เฉพาะ account บางจุด + ควรลด noisy logs |
| Broadcast / Campaign / Scheduler | ใช้งานได้ | ส่ง broadcast หลาย target + scheduled processor + async trigger จาก webhook/dashboard | เสี่ยง race condition ตอน lock แถว broadcast |
| Shop / Orders / Payment / WMS | ใช้งานได้หลาย flow | order/payment/wms flow มีโครงครบ + status mapping | ยังต้องเสริม integration test ข้ามโมดูล |
| Odoo Integration (API + Webhook + Dashboard) | Production Active | endpoint, signature verify, idempotency/retry/dead-letter, monitoring dashboard, LIFF pages | ยังต้อง post-go-live hardening (security/logging/alerting) |
| LIFF Customer App | พร้อมใช้งานฟีเจอร์หลัก | router + Odoo routes + order/detail/tracking/invoice/credit pages | ยังต้อง UAT/UX regression รวม และ config rich menu ฝั่ง admin |
| AI / Telepharmacy / Video Call | มี implementation ต่อเนื่อง | มีโครง class/api/pages พร้อมใช้งานหลายฟังก์ชัน | ควรสรุป coverage test/quality gate ให้ชัดขึ้น |
| Infra / Docs / Ops | มีฐานพร้อม | มี checklist และเอกสารสรุปจำนวนมาก | เอกสารกระจายหลายจุด ต้องจัด archive/เวอร์ชันให้ชัด |

---

## 4) รายละเอียดรายหมวด (ทำถึงไหน / ค้างอะไร / เสี่ยงอะไร)

### 4.1 CRM / Webhook / Event Handling

**ทำแล้ว**
- รองรับ LINE webhook multi-account และ validate signature ตาม account
- มี global error/shutdown logging
- มี dedup ระดับ `webhookEventId` ผ่านตาราง `webhook_events`
- มี fallback send strategy (reply -> push)

**หลักฐานสำคัญ**
- `webhook.php` (โครง event processing ขนาดใหญ่)
- การยิง trigger background ไป scheduled broadcast processor จาก webhook

**ช่องว่าง/เสี่ยง**
2. ยังขาด runbook incident แบบสั้นที่ผูกกับ alert จริง (ปัจจุบันเอกสารมีเยอะ แต่กระจาย)

---

### 4.2 Broadcast / Scheduled Messages

**ทำแล้ว**
- มี helper กลาง (`BroadcastHelper`) สำหรับส่งหลาย target type
- มี `api/process_scheduled_broadcasts.php` สำหรับประมวลผล scheduled queue
- trigger background จากทั้ง `webhook.php` และ `dashboard.php` แล้ว

**หลักฐานสำคัญ**
- Trigger จาก webhook: `webhook.php`
- Trigger จาก dashboard: `dashboard.php`
- Processor endpoint: `api/process_scheduled_broadcasts.php`
- Processing logic: `classes/BroadcastHelper.php`

**ช่องว่าง/เสี่ยง**
1. จุด lock row ยังใช้ `UPDATE ... WHERE id = ?` โดยไม่ guard status เดิม ทำให้มีความเสี่ยงประมวลผลซ้ำเมื่อมี concurrent trigger
2. ยังไม่มี metrics/alert เฉพาะ scheduled broadcast SLA (เช่น delay ต่อ message schedule)

---

### 4.3 Odoo Webhook + Monitoring Dashboard

**ทำแล้ว**
- Odoo environment ถูกตั้งเป็น production และ active URL/API key/secret ถูกเลือกจาก production constants
- Endpoint รับ webhook แยกชัด (`api/webhook/odoo.php`)
- ตรวจ signature + timestamp tolerance + legacy fallback
- receipt log ก่อน parse เพื่อ observability
- idempotency ด้วย delivery_id + status lifecycle (`received/processing/success/failed/retry/dead_letter/duplicate`)
- dashboard API มีหลาย action ทั้ง list/stats/detail/timeline/customer/invoice/notification logs
- Odoo client ฝั่ง LIFF APIs (orders/invoices/credit/user-link) ใช้ `OdooAPIClient` ที่ชี้ `ODOO_API_BASE_URL`

**หลักฐานสำคัญ**
- `config/config.php`
- `api/webhook/odoo.php`
- `classes/OdooWebhookHandler.php`
- `classes/OdooAPIClient.php`
- `api/odoo-orders.php`
- `api/odoo-invoices.php`
- `api/odoo-user-link.php`
- `api/odoo-webhooks-dashboard.php`

**ช่องว่าง/เสี่ยง**
4. หลัง go-live ควรเพิ่ม alert threshold ชัดเจนจากสถานะ `retry/dead_letter/duplicate`

---

### 4.4 LIFF App (Shop + Odoo pages)

**ทำแล้ว**
- Router รองรับ route Odoo (link/orders/detail/tracking/invoices/credit)
- register handlers ครบและมีหน้า render/init หลัก
- ฟีเจอร์ order list/detail/tracking/invoice/credit flow มีโครงพร้อมใช้งาน

**หลักฐานสำคัญ**
- `liff/assets/js/router.js`
- `liff/assets/js/liff-app.js`
- เอกสาร Task 16.x completion

**ช่องว่าง/เสี่ยง**
1. ยังต้อง UAT รวมในอุปกรณ์จริง (LINE in-app browser + mobile matrix)
2. Rich Menu integration ฝั่งปฏิบัติการยัง pending (งาน admin config)

---

### 4.5 Shop / Orders / Payment / WMS

**ทำแล้ว**
- มีโครง order status/payment status/wms status mapping
- มี integration path จาก order/payment ไป WMS pending_pick
- มีหน้าและ API ฝั่ง shop/orders/reports

**ช่องว่าง/เสี่ยง**
1. Cross-module regression ยังไม่ชัดเจน (shop -> payment -> wms -> notification)
2. ควรกำหนด baseline KPI (order lead time, pick/pack latency, failure ratio)

---

### 4.6 Testing / QA / Docs

**ทำแล้ว**
- มีโฟลเดอร์ test แยกเป็นหลายโดเมน
- มี task summary docs ครบหลาย sprint

**ช่องว่าง/เสี่ยง**
2. ยังไม่มี single source QA matrix รวม critical flow ทั้งระบบในไฟล์เดียว
3. เอกสารเก่ามีจำนวนมากและกระจาย ต้องจัด archive แบบมีดัชนีชัดเจน

---

## 5) งานที่เสร็จเชิงโค้ด แต่ยังไม่เสร็จเชิงใช้งานจริง

1. **Rich Menu Odoo Integration**
   - โค้ดและคู่มือเสร็จ แต่ยังต้องทำ admin configuration จริงในระบบ LINE OA
2. **Webhook/Signature Hardening**
   - logic แข็งแรงขึ้นแล้ว แต่ต้องวางแผนกำหนด log policy production

---

## 6) ฟังก์ชัน/หมวดที่ยังขาดหรือยังไม่สมบูรณ์

### A) Functional Gaps
- Dynamic Rich Menu ตามสถานะ linked/unlinked ยังเป็นแนวทางในเอกสาร มากกว่าการใช้งานจริงครบวงจร
- End-user journey บางจุดยังพึ่งพา admin setup ภายนอกโค้ด

### B) Operational Gaps
- ยังไม่มี burn-in checklist หลัง Odoo go-live (24h/72h/7d monitoring gates)

### C) Data/Observability Gaps
- ยังไม่มี dashboard รวม health score ทั้ง LINE + Odoo + Scheduler ในหน้าเดียว

### D) Testing Gaps
- ยังต้องมี regression suite สำหรับ Odoo payload variations/edge cases

---

## 7) Roadmap แนะนำ 30 / 60 / 90 วัน

## 30 วัน (Stabilize)
- ปิด debug logging ที่ไม่จำเป็นใน production path
- ปิด/จำกัด legacy drift verification ตาม policy ที่กำหนด
- แก้ lock strategy ของ scheduled broadcast ให้ atomic มากขึ้น
- ปิดงาน admin config ที่ pending (Rich Menu + LIFF entry points)
- จัดทำ QA smoke checklist ชุดเดียวสำหรับ release ทุกครั้ง

## 60 วัน (Harden)
- ทำ E2E test สำหรับ Odoo webhook -> notification -> dashboard visibility
- เพิ่ม alerting (retry spike, dead letter growth, scheduler lag)
- รวมเอกสาร runbook incident + rollback playbook

## 90 วัน (Scale)
- ทำ unified operations dashboard (Webhooks + Broadcast + Odoo + LIFF health)
- เพิ่ม performance baseline และ SLO ต่อโมดูล
- ปรับกระบวนการ release governance (DoD + test gates + artifact)

---

## 8) Priority Backlog (P0 / P1 / P2)

## P0 (ต้องทำก่อน release ใหญ่)
1. ย้าย Odoo production credentials ออกจาก source code ไปใช้ env/secret manager
2. ปรับค่าควบคุม timestamp/legacy drift ของ webhook ให้ production-safe
3. ปิดงาน pending admin configuration ของ Rich Menu

## P1 (เพิ่มเสถียรภาพ)
1. เพิ่ม alerting + threshold + notification rules
2. สร้าง regression suite สำหรับ webhook payload variants
3. จัดทำ central QA matrix (critical flow checklist)

## P2 (เพิ่ม maturity)
1. Unified operations dashboard 
2. Data quality monitors/reporting automation
3. ปรับเอกสารให้เป็น versioned docs lifecycle เต็มรูปแบบ

---

## 9) ความเสี่ยงและ Dependencies

### ความเสี่ยงหลัก
- **Config Dependency:** โค้ดพร้อมแต่ไม่ activate เพราะยังไม่ config ที่ LINE/OA
- **Concurrency Risk:** scheduler trigger จากหลายทางพร้อมกัน
- **Observability Debt:** log เยอะแต่ alert/actionable signal ยังไม่พอ
- **Doc Drift:** เอกสารเก่าบางไฟล์ไม่สอดคล้อง implementation ล่าสุด

### Dependencies สำคัญ
- LINE Developers Console / LIFF / Rich Menu setup
- Odoo production endpoint readiness + key/secret management
- DB schema consistency ในแต่ละ environment
- Admin/Ops ownership ชัดเจนสำหรับขั้นตอน non-code

---

## 10) Definition of Done (DoD) ที่แนะนำต่อหมวด

### CRM/Webhook
- ผ่าน signature + idempotency tests
- มี alert เมื่อ failed/retry/dead_letter เกิน threshold
- ไม่มี debug logs ที่เปิดค้างแบบ hardcoded

### Broadcast/Scheduler
- ผ่าน test concurrency (ไม่ส่งซ้ำ)
- มี metric schedule delay
- มี rerun/recovery procedure ที่ทดสอบแล้ว

### Odoo Integration
- ผ่าน E2E test จาก webhook ถึง user notification
- dashboard แสดงผลสถานะครบและตรงข้อมูลจริง
- มี versioned API contract ที่ทีมใช้ร่วมกัน

### LIFF
- route + deep-link + back navigation ผ่าน UAT บนอุปกรณ์จริง
- flow linked/unlinked account ถูกต้องทุกเส้นทาง
- error states มี fallback ที่ใช้งานได้จริง

### Shop/WMS/Payment
- cross-module regression ผ่าน (order -> payment -> wms -> notify)
- มี KPI baseline และ monitor trend ต่อ release

### QA/Docs/Ops
- มี release checklist กลาง 1 ชุด
- เอกสารถูกจัด version + archive + owner ชัดเจน
- เอกสาร runbook incident ใช้งานได้จริง

---

## 11) รายการอ้างอิงหลักที่ใช้ประเมิน

### โค้ด (Source of Truth)
- `config/config.php`
- `webhook.php`
- `dashboard.php`
- `api/process_scheduled_broadcasts.php`
- `classes/BroadcastHelper.php`
- `api/webhook/odoo.php`
- `classes/OdooWebhookHandler.php`
- `classes/OdooAPIClient.php`
- `api/odoo-orders.php`
- `api/odoo-invoices.php`
- `api/odoo-user-link.php`
- `api/odoo-webhooks-dashboard.php`
- `liff/assets/js/router.js`
- `liff/assets/js/liff-app.js`
- `composer.json`
- `tests/README.md`

---

## 12) Action Summary (ใช้งานต่อได้ทันที)

**Quick Wins (สัปดาห์นี้):**
1. ปิด debug hardcode + cleanup logs
2. ย้าย production secrets ออกจาก source code
3. ปรับ lock scheduled broadcasts
4. ปิดงาน rich menu config ที่ค้าง

**หลังขึ้น production แล้ว (ช่วง Burn-in 2-4 สัปดาห์):**
1. E2E ทดสอบ Odoo บน production traffic scenarios
2. เตรียม alert rules และ runbook
3. ยืนยัน consistency ของเอกสาร endpoint/flow

**ผลลัพธ์ที่คาดหวัง:**
- ลด incident ซ้ำเรื่อง webhook/scheduler
- ลดความเสี่ยงจาก secret/config gap
- ยกระดับความพร้อม production อย่างวัดผลได้
