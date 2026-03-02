# คู่มือติดตั้งระบบส่งสรุปออเดอร์อัตโนมัติ (Odoo Daily Summary Auto-Send)

## ภาพรวม

ระบบนี้จะส่งสรุปออเดอร์ประจำวันให้ลูกค้าอัตโนมัติทาง LINE ตามเวลาที่แอดมินกำหนด โดยจะรวบรวมกิจกรรมออเดอร์ของวันที่ผ่านมาและส่งให้ลูกค้าที่มีการเคลื่อนไหว

## ฟีเจอร์หลัก

- ✅ **ตั้งเวลาส่งอัตโนมัติ** - แอดมินกำหนดเวลาส่งได้ (เช่น 09:00 น.)
- ✅ **ส่งอัตโนมัติทุกวัน** - Cron job ทำงานทุกนาทีและตรวจสอบเวลา
- ✅ **ป้องกันส่งซ้ำ** - ส่งได้ 1 ครั้ง/วัน/คน
- ✅ **บันทึก Log** - เก็บประวัติการส่งทุกครั้ง
- ✅ **UI ตั้งค่า** - หน้าจอสำหรับแอดมินจัดการการตั้งค่า
- ✅ **รายงานสถิติ** - แสดงผลการส่งแต่ละครั้ง

## ขั้นตอนการติดตั้ง

### 1. รัน Database Migration

เข้า phpMyAdmin หรือใช้ MySQL client รันคำสั่ง:

```bash
mysql -u [username] -p [database_name] < database/migration_odoo_daily_summary_settings.sql
```

หรือคัดลอกเนื้อหาจากไฟล์ `database/migration_odoo_daily_summary_settings.sql` และรันใน phpMyAdmin

ตารางที่จะถูกสร้าง:
- `odoo_daily_summary_settings` - เก็บการตั้งค่า
- `odoo_daily_summary_auto_log` - เก็บ log การส่งอัตโนมัติ

### 2. ตั้งค่า Cron Job

เพิ่ม cron job ให้รันทุกนาที:

#### สำหรับ Linux/Unix:

```bash
# แก้ไข crontab
crontab -e

# เพิ่มบรรทัดนี้ (แก้ path ให้ถูกต้อง)
* * * * * /usr/bin/php /path/to/re-ya/cron/odoo_daily_summary_auto.php >> /path/to/logs/daily_summary_auto.log 2>&1
```

#### สำหรับ Windows (Task Scheduler):

1. เปิด Task Scheduler
2. สร้าง Basic Task ใหม่
3. ตั้งค่า Trigger: Daily, Repeat every 1 minute
4. Action: Start a program
   - Program: `C:\php\php.exe`
   - Arguments: `C:\path\to\re-ya\cron\odoo_daily_summary_auto.php`
5. บันทึก

#### ตัวอย่าง Batch Script สำหรับ Windows:

สร้างไฟล์ `run_daily_summary_auto.bat`:

```batch
@echo off
cd /d "C:\path\to\re-ya"
php cron\odoo_daily_summary_auto.php >> logs\daily_summary_auto.log 2>&1
```

### 3. ตรวจสอบ Permissions

ตรวจสอบว่าไฟล์ cron สามารถรันได้:

```bash
# Linux/Unix
chmod +x cron/odoo_daily_summary_auto.php

# ทดสอบรัน
php cron/odoo_daily_summary_auto.php
```

### 4. เข้าใช้งาน UI

1. เข้า `odoo-dashboard.php`
2. คลิกเมนู **"สรุปประจำวัน"**
3. ในส่วน **"ตั้งค่าส่งอัตโนมัติ"** จะเห็น:
   - สวิตช์เปิด/ปิดการส่งอัตโนมัติ
   - ช่องกำหนดเวลาส่ง (เช่น 09:00)
   - ช่องกำหนดย้อนหลังกี่วัน (1-7 วัน)

## การใช้งาน

### การตั้งค่าครั้งแรก

1. เปิดสวิตช์ "เปิดใช้งานส่งอัตโนมัติ"
2. ตั้งเวลาที่ต้องการส่ง (เช่น 09:00 สำหรับ 9 โมงเช้า)
3. ตั้งย้อนหลังกี่วัน (แนะนำ 1 วัน)
4. ระบบจะบันทึกอัตโนมัติ

### การทำงานของระบบ

1. **Cron รันทุกนาที** - ตรวจสอบว่าถึงเวลาส่งหรือยัง
2. **ตรงเวลาที่กำหนด** - ดึงรายการลูกค้าที่มีกิจกรรมออเดอร์เมื่อวาน
3. **กรองผู้รับ** - เลือกเฉพาะคนที่ยังไม่ได้รับสรุปวันนี้
4. **ส่ง Flex Message** - ส่งสรุปออเดอร์ทาง LINE
5. **บันทึก Log** - เก็บผลการส่งลงฐานข้อมูล

### ตรวจสอบสถานะ

ในหน้า UI จะแสดง:
- **ส่งครั้งล่าสุด**: วันที่ส่งล่าสุด
- **ผู้รับทั้งหมด**: จำนวนลูกค้าที่มีสิทธิ์รับ
- **ส่งสำเร็จ**: จำนวนที่ส่งสำเร็จ
- **ล้มเหลว**: จำนวนที่ส่งไม่สำเร็จ
- **เวลาประมวลผล**: เวลาที่ใช้ในการส่ง (ms)

### ประวัติการส่งอัตโนมัติ

ตารางแสดงประวัติ 10 ครั้งล่าสุด:
- วันที่และเวลาที่ส่ง
- เวลาที่ตั้งไว้
- จำนวนผู้รับ, สำเร็จ, ล้มเหลว, ข้าม
- เวลาประมวลผล
- สถานะ (สำเร็จ/บางส่วน/ล้มเหลว)

## การแก้ปัญหา

### ไม่มีการส่งอัตโนมัติ

1. **ตรวจสอบ Cron Job**
   ```bash
   # ดู cron logs
   tail -f /path/to/logs/daily_summary_auto.log
   ```

2. **ตรวจสอบการตั้งค่า**
   - เปิดใช้งานหรือยัง?
   - เวลาถูกต้องหรือไม่?
   - Timezone ตรงกันหรือไม่?

3. **ตรวจสอบฐานข้อมูล**
   ```sql
   SELECT * FROM odoo_daily_summary_settings;
   SELECT * FROM odoo_daily_summary_auto_log ORDER BY execution_time DESC LIMIT 5;
   ```

### ส่งไม่สำเร็จ

1. **ตรวจสอบ LINE Access Token**
   - Token ยังใช้งานได้หรือไม่?
   - ลูกค้ามี LINE User ID หรือไม่?

2. **ตรวจสอบ Error Log**
   ```bash
   tail -f /path/to/logs/daily_summary_auto.log
   ```

3. **ตรวจสอบ Notification Log**
   ```sql
   SELECT * FROM odoo_notification_log 
   WHERE event_type = 'daily.summary' 
   AND DATE(sent_at) = CURDATE()
   ORDER BY sent_at DESC;
   ```

### ส่งซ้ำ

- ระบบป้องกันการส่งซ้ำโดยตรวจสอบ `last_sent_date`
- ถ้าต้องการส่งซ้ำ ให้ reset:
  ```sql
  UPDATE odoo_daily_summary_settings 
  SET setting_value = NULL 
  WHERE setting_key = 'last_sent_date';
  ```

## โครงสร้างไฟล์

```
re-ya/
├── api/
│   ├── odoo-daily-summary-settings.php    # API สำหรับจัดการการตั้งค่า
│   └── odoo-webhooks-dashboard.php        # API สำหรับดึงข้อมูลและส่ง
├── cron/
│   └── odoo_daily_summary_auto.php        # Cron job หลัก
├── database/
│   └── migration_odoo_daily_summary_settings.sql  # SQL migration
├── odoo-dashboard.php                     # UI หน้าแอดมิน
└── odoo-dashboard.js                      # JavaScript สำหรับ UI
```

## ตัวอย่าง Flex Message

ลูกค้าจะได้รับข้อความแบบ Flex Message ที่มี:
- ชื่อลูกค้า
- รายการออเดอร์ที่มีการเคลื่อนไหว
- Timeline แสดงสถานะแต่ละออเดอร์
- เวลาอัพเดทล่าสุด

## API Endpoints

### GET/POST `/api/odoo-daily-summary-settings.php`

**Actions:**
- `get_settings` - ดึงการตั้งค่าปัจจุบัน
- `save_settings` - บันทึกการตั้งค่า
- `get_logs` - ดึงประวัติการส่ง
- `test_send` - ทดสอบดูจำนวนผู้รับ

**ตัวอย่าง:**
```javascript
// ดึงการตั้งค่า
fetch('api/odoo-daily-summary-settings.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'get_settings'})
})

// บันทึกการตั้งค่า
fetch('api/odoo-daily-summary-settings.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'save_settings',
        auto_send_enabled: 1,
        send_time: '09:00',
        lookback_days: 1
    })
})
```

## Security Notes

- ✅ ใช้ Prepared Statements ป้องกัน SQL Injection
- ✅ Validate input ทุกครั้ง
- ✅ ตรวจสอบ permissions ก่อนส่ง
- ✅ Rate limiting ป้องกันส่งซ้ำ
- ✅ Error handling ครอบคลุม

## Performance

- Cron รันเร็ว (< 100ms) ถ้าไม่ถึงเวลา
- การส่งจริงใช้เวลาประมาณ 100-300ms ต่อคน
- รองรับการส่งพร้อมกันหลายร้อยคน

## การอัพเกรดในอนาคต

แนวคิดที่อาจเพิ่มเติม:
- [ ] เลือกวันที่ต้องการส่ง (จันทร์-อาทิตย์)
- [ ] Template สรุปแบบต่างๆ
- [ ] ส่งหลายรอบต่อวัน
- [ ] การแจ้งเตือนแอดมินเมื่อส่งเสร็จ
- [ ] Export รายงานเป็น CSV

## Support

หากพบปัญหาหรือต้องการความช่วยเหลือ:
1. ตรวจสอบ logs ก่อน
2. ดูที่ `odoo_daily_summary_auto_log` table
3. ตรวจสอบ cron job configuration

---

**เวอร์ชัน:** 1.0.0  
**วันที่สร้าง:** 2026-02-26  
**ผู้พัฒนา:** RE-YA Development Team
