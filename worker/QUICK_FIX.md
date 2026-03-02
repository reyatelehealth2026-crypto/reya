# 🔧 แก้ปัญหา "No such file or directory" - ไฟล์ log ไม่มี

## ปัญหาที่เจอ
```
tail: cannot open 'logs/notification-worker.log' for reading: No such file or directory
```

## วิธีแก้ (ใช้ Terminal ใน cPanel)

### ขั้นตอนที่ 1: สร้างโฟลเดอร์และไฟล์ log

```bash
# 1. ไปที่โฟลเดอร์โปรเจค
cd ~/public_html/stg.re-ya.com

# 2. สร้างโฟลเดอร์ logs
mkdir -p logs

# 3. สร้างไฟล์ log
touch logs/notification-worker.log

# 4. ตั้งค่า permission
chmod 755 logs
chmod 644 logs/notification-worker.log
```

### ขั้นตอนที่ 2: ทดสอบรัน worker ด้วยมือ

```bash
# รัน worker 1 ครั้ง
php worker/notification-worker.php

# ดูผลลัพธ์
cat logs/notification-worker.log
```

### ขั้นตอนที่ 3: ตั้ง Cron Job ใน cPanel

กลับไปที่ cPanel → Cron Jobs แล้วกรอก:

**คำสั่ง:**
```bash
/usr/local/bin/php /home/zrismpsz/public_html/stg.re-ya.com/worker/notification-worker.php >> /home/zrismpsz/public_html/stg.re-ya.com/logs/notification-worker.log 2>&1
```

**ตั้งเวลา:** ทุกนาที (`*` ทุกช่อง)

---

## ตรวจสอบว่าสำเร็จ

```bash
# ดู log แบบ real-time
tail -f logs/notification-worker.log

# หรือดูทั้งหมด
cat logs/notification-worker.log

# เช็คว่ามีไฟล์หรือไม่
ls -lh logs/
```

---

## ถ้ายังไม่ได้ - ใช้วิธีนี้

### สร้างไฟล์ log ด้วย echo

```bash
cd ~/public_html/stg.re-ya.com
echo "[$(date)] Log file created" > logs/notification-worker.log
chmod 644 logs/notification-worker.log
```

### ทดสอบเขียน log

```bash
echo "[$(date)] Test message" >> logs/notification-worker.log
cat logs/notification-worker.log
```

ถ้าเห็นข้อความ = **สำเร็จ!** ✅

---

## คำสั่งครบ (Copy-Paste ได้เลย)

```bash
cd ~/public_html/stg.re-ya.com && \
mkdir -p logs && \
touch logs/notification-worker.log && \
chmod 755 logs && \
chmod 644 logs/notification-worker.log && \
echo "[$(date)] Log initialized" > logs/notification-worker.log && \
cat logs/notification-worker.log
```

ถ้าเห็น `Log initialized` = **พร้อมใช้งาน!** 🎉
