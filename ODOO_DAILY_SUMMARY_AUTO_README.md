# ระบบส่งสรุปออเดอร์อัตโนมัติ (Odoo Daily Summary Auto-Send)

## 📋 สรุปฟีเจอร์

ระบบส่งสรุปออเดอร์ประจำวันให้ลูกค้าอัตโนมัติทาง LINE Messaging API โดยแอดมินสามารถตั้งค่าเวลาส่งได้ (เช่น 9 โมงเช้า) และระบบจะส่งสรุปกิจกรรมออเดอร์ของวันที่ผ่านมาให้ลูกค้าที่มีการเคลื่อนไหวโดยอัตโนมัติ

## ✨ ฟีเจอร์หลัก

- ✅ **ตั้งเวลาส่งอัตโนมัติ** - กำหนดเวลาส่งได้ตามต้องการ (รูปแบบ 24 ชั่วโมง)
- ✅ **เปิด/ปิดการส่งอัตโนมัติ** - สวิตช์เปิด/ปิดใช้งานง่ายๆ
- ✅ **ป้องกันส่งซ้ำ** - ส่งได้ 1 ครั้ง/วัน/คน
- ✅ **บันทึก Log ครบถ้วน** - เก็บประวัติการส่งทุกครั้งพร้อมสถิติ
- ✅ **UI สำหรับแอดมิน** - หน้าจอจัดการการตั้งค่าและดูประวัติ
- ✅ **รายงานแบบ Real-time** - แสดงผลการส่งล่าสุดและสถิติ

## 🚀 Quick Start

### 1. ติดตั้งระบบ

```bash
# รัน migration script
php install/run_odoo_daily_summary_migration.php
```

### 2. ตั้งค่า Cron Job

**Linux/Unix:**
```bash
# เพิ่มใน crontab
* * * * * /usr/bin/php /path/to/re-ya/cron/odoo_daily_summary_auto.php >> /path/to/logs/daily_summary_auto.log 2>&1
```

**Windows (Task Scheduler):**
- สร้าง Task ให้รันทุกนาที
- Program: `php.exe`
- Arguments: `C:\path\to\re-ya\cron\odoo_daily_summary_auto.php`

### 3. เปิดใช้งานผ่าน UI

1. เข้า `odoo-dashboard.php`
2. คลิกเมนู **"สรุปประจำวัน"**
3. เปิดสวิตช์ **"เปิดใช้งานส่งอัตโนมัติ"**
4. ตั้งเวลาที่ต้องการ (เช่น `09:00`)
5. บันทึกอัตโนมัติ ✓

## 📁 ไฟล์ที่เกี่ยวข้อง

```
re-ya/
├── api/
│   ├── odoo-daily-summary-settings.php       # API จัดการการตั้งค่า
│   └── odoo-webhooks-dashboard.php           # API ดึงข้อมูลและส่ง (ใช้ฟังก์ชันเดิม)
├── cron/
│   └── odoo_daily_summary_auto.php           # Cron job หลัก (รันทุกนาที)
├── database/
│   └── migration_odoo_daily_summary_settings.sql  # SQL migration
├── install/
│   ├── run_odoo_daily_summary_migration.php  # Installation script
│   └── INSTALL_ODOO_DAILY_SUMMARY_AUTO.md    # คู่มือติดตั้งฉบับเต็ม
├── odoo-dashboard.php                        # UI (เพิ่ม section ใหม่)
└── odoo-dashboard.js                         # JavaScript (เพิ่ม functions)
```

## 🗄️ Database Schema

### ตาราง `odoo_daily_summary_settings`
เก็บการตั้งค่าระบบ:
- `auto_send_enabled` - เปิด/ปิดการส่งอัตโนมัติ (0/1)
- `send_time` - เวลาส่ง (เช่น "09:00")
- `send_timezone` - Timezone (เช่น "Asia/Bangkok")
- `lookback_days` - ย้อนหลังกี่วัน (1-7)
- `last_sent_date` - วันที่ส่งครั้งล่าสุด

### ตาราง `odoo_daily_summary_auto_log`
เก็บประวัติการส่งอัตโนมัติ:
- `execution_date` - วันที่ส่ง
- `execution_time` - เวลาที่ส่งจริง
- `scheduled_time` - เวลาที่ตั้งไว้
- `total_recipients` - จำนวนผู้รับทั้งหมด
- `sent_count` - ส่งสำเร็จ
- `failed_count` - ส่งล้มเหลว
- `skipped_count` - ข้าม (ส่งไปแล้ว)
- `execution_duration_ms` - เวลาประมวลผล (ms)
- `status` - สถานะ (success/partial/failed)

## 🔄 Flow การทำงาน

```
1. Cron รันทุกนาที
   ↓
2. ตรวจสอบว่าเปิดใช้งานหรือไม่
   ↓
3. ตรวจสอบว่าถึงเวลาที่กำหนดหรือยัง (เช่น 09:00)
   ↓
4. ตรวจสอบว่าส่งไปแล้ววันนี้หรือยัง
   ↓
5. ดึงรายการลูกค้าที่มีกิจกรรมออเดอร์เมื่อวาน
   ↓
6. กรองเฉพาะคนที่ยังไม่ได้รับสรุปวันนี้
   ↓
7. ส่ง Flex Message ทาง LINE API
   ↓
8. บันทึก Log ลงฐานข้อมูล
   ↓
9. อัพเดท last_sent_date
```

## 🎯 การใช้งาน UI

### หน้าตั้งค่า

**ส่วนที่ 1: ตั้งค่าส่งอัตโนมัติ**
- สวิตช์เปิด/ปิด
- ช่องกำหนดเวลา (time picker)
- ช่องกำหนดย้อนหลัง (1-7 วัน)
- แสดงสถิติการส่งครั้งล่าสุด

**ส่วนที่ 2: ประวัติการส่งอัตโนมัติ**
- ตารางแสดง 10 ครั้งล่าสุด
- คอลัมน์: วันที่, เวลา, ผู้รับ, สำเร็จ, ล้มเหลว, ข้าม, เวลาประมวลผล, สถานะ

**ส่วนที่ 3: รายการลูกค้า (เดิม)**
- ดูรายการลูกค้าที่มีกิจกรรม
- ส่งแบบ Manual ได้

## 🔧 API Endpoints

### `POST /api/odoo-daily-summary-settings.php`

**1. ดึงการตั้งค่า**
```json
{
  "action": "get_settings"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "available": true,
    "settings": {
      "auto_send_enabled": {"value": "1", "enabled": true},
      "send_time": {"value": "09:00", "enabled": true},
      "lookback_days": {"value": "1", "enabled": true}
    },
    "last_execution": {
      "execution_date": "2026-02-26",
      "total_recipients": 15,
      "sent_count": 14,
      "failed_count": 1
    }
  }
}
```

**2. บันทึกการตั้งค่า**
```json
{
  "action": "save_settings",
  "auto_send_enabled": 1,
  "send_time": "09:00",
  "lookback_days": 1
}
```

**3. ดึงประวัติการส่ง**
```json
{
  "action": "get_logs",
  "limit": 10
}
```

## 🐛 Troubleshooting

### ไม่มีการส่งอัตโนมัติ

**ตรวจสอบ:**
1. Cron job ทำงานหรือไม่?
   ```bash
   tail -f /path/to/logs/daily_summary_auto.log
   ```

2. การตั้งค่าถูกต้องหรือไม่?
   ```sql
   SELECT * FROM odoo_daily_summary_settings;
   ```

3. เวลาตรงกันหรือไม่? (ระวัง timezone)

### ส่งไม่สำเร็จ

**ตรวจสอบ:**
1. LINE Access Token ยังใช้งานได้หรือไม่?
2. ลูกค้ามี `line_user_id` หรือไม่?
3. ดู error log:
   ```sql
   SELECT * FROM odoo_notification_log 
   WHERE event_type = 'daily.summary' 
   AND status = 'failed'
   ORDER BY sent_at DESC LIMIT 10;
   ```

### ต้องการส่งซ้ำ (ทดสอบ)

```sql
-- Reset last_sent_date เพื่อให้ส่งได้อีกครั้ง
UPDATE odoo_daily_summary_settings 
SET setting_value = NULL 
WHERE setting_key = 'last_sent_date';
```

## 📊 Performance

- **Cron check**: < 100ms (ถ้าไม่ถึงเวลา)
- **การส่งจริง**: ~100-300ms ต่อคน
- **รองรับ**: หลายร้อยคนพร้อมกัน
- **Memory**: ใช้น้อย (streaming send)

## 🔒 Security

- ✅ Prepared Statements (ป้องกัน SQL Injection)
- ✅ Input Validation ทุกครั้ง
- ✅ Permission Check ก่อนส่ง
- ✅ Rate Limiting (1 ครั้ง/วัน/คน)
- ✅ Error Handling ครอบคลุม
- ✅ Secure Logging (ไม่เก็บข้อมูลส่วนตัว)

## 📈 Future Enhancements

แนวคิดที่อาจพัฒนาต่อ:
- [ ] เลือกวันที่ต้องการส่ง (จันทร์-อาทิตย์)
- [ ] Template สรุปแบบต่างๆ (สั้น/ยาว/กราฟ)
- [ ] ส่งหลายรอบต่อวัน
- [ ] การแจ้งเตือนแอดมินเมื่อส่งเสร็จ
- [ ] Export รายงานเป็น CSV/Excel
- [ ] A/B Testing templates
- [ ] Personalization ตาม segment

## 📝 Changelog

### Version 1.0.0 (2026-02-26)
- ✨ Initial release
- ✅ Auto-send functionality
- ✅ Admin UI
- ✅ Logging system
- ✅ Cron job
- ✅ Complete documentation

## 👥 Credits

**Developed by:** RE-YA Development Team  
**Date:** February 26, 2026  
**License:** Proprietary

---

## 📚 เอกสารเพิ่มเติม

- [คู่มือติดตั้งฉบับเต็ม](install/INSTALL_ODOO_DAILY_SUMMARY_AUTO.md)
- [Database Migration](database/migration_odoo_daily_summary_settings.sql)
- [Cron Job Source](cron/odoo_daily_summary_auto.php)
- [API Documentation](api/odoo-daily-summary-settings.php)

## 💡 Tips

1. **ทดสอบก่อนใช้จริง**: ตั้งเวลาใกล้ๆ แล้วดู log
2. **Monitor logs**: ตรวจสอบ log เป็นประจำ
3. **Backup settings**: สำรองการตั้งค่าก่อนแก้ไข
4. **Test notifications**: ทดสอบส่ง manual ก่อน
5. **Check timezone**: ตรวจสอบ timezone ให้ตรงกัน

---

**หากมีคำถามหรือพบปัญหา โปรดตรวจสอบ logs และเอกสารประกอบก่อน**
