# Legacy Document Catalog (2026-02)

เอกสารนี้ทำหน้าที่เป็นดัชนีแยกเอกสารเก่า/เอกสารสรุปงานย้อนหลัง ออกจากเอกสารหลักที่ใช้งานปัจจุบัน

> หมายเหตุ: บางไฟล์ยังคงอยู่ตำแหน่งเดิมเพื่อไม่ให้ลิงก์เดิมแตก แต่ถูกจัดเข้ากลุ่ม legacy แล้วใน catalog นี้

---

## 1) กลุ่ม Legacy ที่ Root Project

ไฟล์ในกลุ่มนี้เป็นเอกสาร incident/fix summary/testing เฉพาะช่วงเวลา:

- `CUSTOM_DISPLAY_NAME_FIX_SUMMARY.md`
- `DEPLOYMENT_CHECKLIST.md`
- `INSTALL_QR_LIBRARY.md`
- `ODOO_API_BUG_REPORT.md`
- `QUICK_FIX_INSTRUCTIONS.md`
- `REQUEST_WEBHOOK_RESEND.md`
- `TASK_12.3_COMPLETE.md`
- `TEST_WEBHOOK_POSTMAN.md`
- `WEBHOOK_FEB16_ROOT_CAUSE_ANALYSIS.md`
- `WEBHOOK_FIX_SUMMARY_TH.md`
- `WEBHOOK_ISSUE_SUMMARY.md`
- `WEBHOOK_RECOVERY_GUIDE_TH.md`
- `WEBHOOK_SIGNATURE_FIX_GUIDE.md`
- `WEBHOOK_SILENT_FAILURE_FIX.md`

---

## 2) กลุ่ม Legacy ใน `docs/` (Task-based Histories)

### 2.1 Task 11.x
- `docs/TASK_11.1_QR_LIBRARY_SUMMARY.md`
- `docs/TASK_11.2_QR_GENERATION_SUMMARY.md`
- `docs/TASK_11.3_COMPLETION_SUMMARY.md`
- `docs/TASK_11.3_QR_GENERATION_TESTING.md`

### 2.2 Task 12.x
- `docs/TASK_12.1_BDO_HANDLER_IMPLEMENTATION.md`
- `docs/TASK_12.1_COMPLETION_SUMMARY.md`
- `docs/TASK_12.2_BDO_FLEX_TEMPLATE_VERIFICATION.md`
- `docs/TASK_12.2_COMPLETION_SUMMARY.md`
- `docs/TASK_12.3_BDO_WEBHOOK_TEST_SUMMARY.md`
- `docs/TASK_12.3_FINAL_SUMMARY.md`

### 2.3 Task 13.x
- `docs/TASK_13.1_COMPLETION_SUMMARY.md`
- `docs/TASK_13.1_SLIP_UPLOAD_METHOD_VERIFICATION.md`
- `docs/TASK_13.2_FINAL_SUMMARY.md`
- `docs/TASK_13.2_SLIP_UPLOAD_API_IMPLEMENTATION.md`
- `docs/TASK_13.3_FINAL_COMPLETION.md`
- `docs/TASK_13.3_SLIP_UPLOAD_TEST_SUMMARY.md`

### 2.4 Task 14.x
- `docs/TASK_14.1_PAYMENT_STATUS_IMPLEMENTATION.md`
- `docs/TASK_14.2_COMPLETION_SUMMARY.md`
- `docs/TASK_14.2_PAYMENT_STATUS_API_IMPLEMENTATION.md`
- `docs/TASK_14.3_PAYMENT_STATUS_TESTING_COMPLETE.md`
- `docs/TASK_14.3_SUMMARY.md`

### 2.5 Task 15.x
- `docs/TASK_15.2_ORDERS_API_COMPLETION.md`
- `docs/TASK_15.3_ORDERS_API_TESTING_COMPLETE.md`

### 2.6 Task 16.x
- `docs/TASK_16.1_ODOO_ORDERS_LIST_COMPLETION.md`
- `docs/TASK_16.2_ORDER_DETAIL_PAGE_COMPLETION.md`
- `docs/TASK_16.3_ORDER_TRACKING_PAGE_COMPLETION.md`
- `docs/TASK_16.4_FINAL_VERIFICATION.md`
- `docs/TASK_16.4_LIFF_ROUTER_INTEGRATION_COMPLETE.md`
- `docs/TASK_16.5_COMPLETION_SUMMARY.md`
- `docs/TASK_16.5_RICH_MENU_INTEGRATION_GUIDE.md`

---

## 3) เอกสารหลักที่ควรใช้แทน (Current Primary Docs)

- `README.md` (ภาพรวมโครงการ)
- `docs/ARCHITECTURE.md` (ภาพรวมสถาปัตยกรรม)
- `docs/COMPLETE_DOCUMENTATION.md` (reference รวม)
- `docs/PROJECT_STATUS_MASTER_2026-02-26_TH.md` (สถานะล่าสุดฉบับละเอียด)

---

## 4) แนวทางการดูแลเอกสารต่อไป (Doc Lifecycle)

1. เอกสาร release ปัจจุบันให้ใช้ชื่อแบบ `PROJECT_STATUS_MASTER_YYYY-MM-DD_TH.md`
2. เอกสาร incident/task summary ให้ลงในกลุ่ม legacy catalog นี้
3. ทุกครั้งที่สร้างสถานะรอบใหม่ ให้:
   - อัปเดตไฟล์ status ล่าสุด
   - อัปเดต catalog สำหรับเอกสารที่ไม่ active แล้ว
   - คงลิงก์ใน `docs/README.md` ให้ชี้ไฟล์ล่าสุดเสมอ
