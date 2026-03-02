# 🚀 ตั้งค่า Notification Worker สำหรับ cny.re-ya.com

## ขั้นตอนที่ 1: สร้างโฟลเดอร์และไฟล์ log

เปิด **Terminal** ใน cPanel แล้ว copy คำสั่งนี้:

```bash
cd ~/public_html/cny.re-ya.com && mkdir -p logs && touch logs/notification-worker.log && chmod 755 logs && chmod 644 logs/notification-worker.log && echo "[$(date)] Log initialized for cny.re-ya.com" > logs/notification-worker.log && cat logs/notification-worker.log
```

ถ้าเห็น `Log initialized for cny.re-ya.com` = **สำเร็จ!** ✅

---

## ขั้นตอนที่ 2: ทดสอบรัน Worker

```bash
cd ~/public_html/cny.re-ya.com
php worker/notification-worker.php
```

ดูผลลัพธ์:
```bash
cat logs/notification-worker.log
```

ควรเห็น:
```
[2026-02-18 00:27:00] Notification Worker Started
```

---

## ขั้นตอนที่ 3: ตั้ง Cron Job ใน cPanel

### 1. เข้า cPanel → Cron Jobs

### 2. กรอกข้อมูล:

| ฟิลด์ | ค่า |
|------|-----|
| นาที | `*` |
| ชั่วโมง | `*` |
| วัน | `*` |
| เดือน | `*` |
| วันในสัปดาห์ | `*` |

### 3. คำสั่ง (Command):

```bash
/usr/local/bin/php /home/zrismpsz/public_html/cny.re-ya.com/worker/notification-worker.php >> /home/zrismpsz/public_html/cny.re-ya.com/logs/notification-worker.log 2>&1
```

### 4. กดปุ่ม "เพิ่ม Cron Job ใหม่"

---

## ✅ ตรวจสอบว่าทำงาน

### วิธีที่ 1: ดู Log แบบ Real-time
```bash
tail -f ~/public_html/cny.re-ya.com/logs/notification-worker.log
```

กด Ctrl+C เพื่อหยุด

### วิธีที่ 2: ดู Log ทั้งหมด
```bash
cat ~/public_html/cny.re-ya.com/logs/notification-worker.log
```

### วิธีที่ 3: ดู 20 บรรทัดล่าสุด
```bash
tail -20 ~/public_html/cny.re-ya.com/logs/notification-worker.log
```

---

## 📊 ตรวจสอบ Database

```bash
# เข้า MySQL ใน cPanel → phpMyAdmin
# รันคำสั่ง SQL นี้:
```

```sql
-- เช็ค queue
SELECT status, COUNT(*) as count 
FROM odoo_notification_queue 
GROUP BY status;

-- เช็ค batch groups
SELECT status, COUNT(*) as count 
FROM odoo_notification_batch_groups 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY status;

-- เช็ค notification log
SELECT status, COUNT(*) as count 
FROM odoo_notification_log 
WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY status;
```

---

## 🎯 สิ่งที่ควรเห็น (หลังรัน 5 นาที)

### ใน Log:
```
[2026-02-18 00:27:15] Notification Worker Started
[00:27:20] ✓ Sent notification #123 to U123456
[00:27:25] 📦 Sent 2 roadmap batches
[00:27:30] 📊 Queue Stats:
  pending: 5 (avg retries: 0.00)
  sent: 45 (avg retries: 0.20)
  Processed: 45, Errors: 0
```

### ใน Database:
- `odoo_notification_queue`: status = 'sent' หรือ 'pending' น้อยๆ
- `odoo_notification_log`: มีข้อมูลการส่ง
- `odoo_notification_batch_groups`: status = 'sent' เมื่อถึง milestone

---

## ⚠️ แก้ปัญหา

### ปัญหา: Worker ไม่ทำงาน

1. **เช็ค PHP path**
   ```bash
   which php
   # ลองทั้ง /usr/local/bin/php และ /usr/bin/php
   ```

2. **ทดสอบ PHP ใน cron**
   ```bash
   /usr/local/bin/php -v
   ```

3. **เช็ค permission**
   ```bash
   ls -la ~/public_html/cny.re-ya.com/worker/notification-worker.php
   ls -la ~/public_html/cny.re-ya.com/logs/
   ```

### ปัญหา: ไม่มี log

```bash
# สร้างใหม่
cd ~/public_html/cny.re-ya.com
mkdir -p logs
echo "[$(date)] Manual log creation" > logs/notification-worker.log
chmod 644 logs/notification-worker.log
```

### ปัญหา: Cron ไม่ทำงาน

1. ตรวจสอบ path ให้แน่ใจว่าถูกต้อง:
   ```bash
   pwd
   # ควรได้: /home/zrismpsz/public_html/cny.re-ya.com
   ```

2. ลองใช้ absolute path ทั้งหมด (ไม่ใช้ ~)

3. เช็ค cron log ใน cPanel

---

## 🔍 คำสั่งที่ใช้บ่อย

```bash
# ไปที่โฟลเดอร์โปรเจค
cd ~/public_html/cny.re-ya.com

# ดู log
tail -f logs/notification-worker.log

# รัน worker ด้วยมือ
php worker/notification-worker.php

# เช็คว่ามีไฟล์อะไรบ้าง
ls -la worker/
ls -la logs/

# ดู path ปัจจุบัน
pwd

# หา PHP
which php
```

---

## ✅ Checklist

- [ ] สร้างโฟลเดอร์ `logs` แล้ว
- [ ] สร้างไฟล์ `logs/notification-worker.log` แล้ว
- [ ] ทดสอบรัน `php worker/notification-worker.php` ได้
- [ ] เห็น log ใน `logs/notification-worker.log`
- [ ] ตั้ง Cron Job ใน cPanel แล้ว
- [ ] รอ 2-3 นาที แล้วเช็ค log อีกครั้ง
- [ ] เห็น worker ทำงานทุกนาที

ถ้าครบทุกข้อ = **สำเร็จ!** 🎉

---

## 📞 ต้องการความช่วยเหลือ?

ส่งข้อมูลนี้มา:

```bash
# 1. Path info
pwd
which php

# 2. File check
ls -la worker/notification-worker.php
ls -la logs/

# 3. Log content
tail -50 logs/notification-worker.log

# 4. Test run
php worker/notification-worker.php 2>&1 | head -20
```
