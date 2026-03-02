# 🚀 ตั้งค่า Notification Worker แบบง่ายๆ

## วิธีที่ 1: ใช้ Cron Job (แนะนำ)

### สำหรับ Linux/Mac

1. **เปิด Terminal แล้วไปที่โฟลเดอร์โปรเจค**
   ```bash
   cd /path/to/re-ya
   ```

2. **รัน script ติดตั้งอัตโนมัติ**
   ```bash
   bash worker/setup-cron.sh
   ```

3. **เสร็จแล้ว!** Worker จะทำงานทุกนาทีอัตโนมัติ

### ตั้งเองด้วยมือ (ถ้า script ไม่ได้)

1. **เปิด crontab**
   ```bash
   crontab -e
   ```

2. **เพิ่มบรรทัดนี้** (แก้ path ให้ถูกต้อง)
   ```
   * * * * * cd /path/to/re-ya && php worker/notification-worker.php >> logs/notification-worker.log 2>&1 &
   ```

3. **บันทึกและออก** (กด Ctrl+X, Y, Enter)

4. **ตรวจสอบว่าติดตั้งแล้ว**
   ```bash
   crontab -l
   ```

---

## วิธีที่ 2: ใช้ cPanel (สำหรับ Shared Hosting)

### ขั้นตอน:

1. **เข้า cPanel → Cron Jobs**

2. **กรอกข้อมูล:**
   - **นาที**: `*`
   - **ชั่วโมง**: `*`
   - **วัน**: `*`
   - **เดือน**: `*`
   - **วันในสัปดาห์**: `*`

3. **คำสั่ง (Command):**
   ```bash
   /usr/local/bin/php /home/username/public_html/path/to/re-ya/worker/notification-worker.php >> /home/username/public_html/path/to/re-ya/logs/notification-worker.log 2>&1
   ```
   
   **⚠️ แก้ path ให้ถูกต้อง:**
   - แทน `/home/username/public_html/path/to/re-ya` ด้วย path จริงของคุณ
   - ดู path ได้จาก: `pwd` ใน Terminal

4. **กด "Add New Cron Job"**

5. **เสร็จแล้ว!**

---

## วิธีที่ 3: รันด้วยมือ (สำหรับทดสอบ)

### รันครั้งเดียว:
```bash
cd /path/to/re-ya
php worker/notification-worker.php
```

### รันแบบ loop (ไม่แนะนำสำหรับ production):
```bash
while true; do
    php worker/notification-worker.php
    sleep 60
done
```

---

## 🔍 ตรวจสอบว่าทำงานหรือไม่

### 1. ดู Log
```bash
tail -f logs/notification-worker.log
```

คุณจะเห็น:
```
[2026-02-18 00:20:15] Notification Worker Started
[00:20:20] ✓ Sent notification #123 to U123456
[00:20:25] 📦 Sent 2 roadmap batches
```

### 2. เช็ค Process
```bash
ps aux | grep notification-worker
```

### 3. เช็ค Database
```sql
SELECT COUNT(*) FROM odoo_notification_queue WHERE status = 'pending';
```

ถ้าเห็น 0 หรือน้อยๆ แสดงว่า worker ทำงานปกติ

---

## ⚠️ แก้ปัญหา

### Worker ไม่ทำงาน

1. **เช็ค PHP path**
   ```bash
   which php
   # ผลลัพธ์: /usr/bin/php หรือ /usr/local/bin/php
   ```

2. **เช็ค permission**
   ```bash
   chmod +x worker/notification-worker.php
   ```

3. **ทดสอบรันด้วยมือ**
   ```bash
   php worker/notification-worker.php
   ```
   ดูว่ามี error หรือไม่

### Cron Job ไม่ทำงาน

1. **เช็ค cron log**
   ```bash
   # Ubuntu/Debian
   tail -f /var/log/syslog | grep CRON
   
   # CentOS/RHEL
   tail -f /var/log/cron
   ```

2. **เช็ค path ถูกต้องหรือไม่**
   - ใช้ absolute path เท่านั้น (เริ่มด้วย `/`)
   - ไม่ใช้ `~` หรือ relative path

3. **เช็ค PHP ใน cron**
   ```bash
   * * * * * /usr/bin/php -v >> /tmp/php-test.log 2>&1
   ```
   ดูว่า PHP ทำงานใน cron หรือไม่

---

## 📊 ตัวอย่าง cPanel

ตามรูปที่คุณแนบมา ให้กรอกแบบนี้:

| ฟิลด์ | ค่า |
|------|-----|
| นาที | `*` |
| ชั่วโมง | `*` |
| วัน | `*` |
| เดือน | `*` |
| วันในสัปดาห์ | `*` |
| คำสั่ง | `/usr/local/bin/php /home/zrismpsz/public_html/stg.re-ya.com/worker/notification-worker.php >> /home/zrismpsz/public_html/stg.re-ya.com/logs/notification-worker.log 2>&1` |

**⚠️ สำคัญ:** แก้ path ให้ตรงกับของคุณ!

---

## ✅ เช็คว่าสำเร็จ

หลังตั้งค่า รอ 1-2 นาที แล้วเช็ค:

```bash
# 1. มีไฟล์ log หรือไม่
ls -lh logs/notification-worker.log

# 2. มีข้อมูลใน log หรือไม่
tail logs/notification-worker.log

# 3. Queue ถูกประมวลผลหรือไม่
mysql -u user -p -e "SELECT status, COUNT(*) FROM odoo_notification_queue GROUP BY status;"
```

ถ้าทั้ง 3 ข้อผ่าน = **สำเร็จ!** 🎉

---

## 🆘 ยังไม่ได้?

ส่งข้อมูลนี้มาให้ดู:

1. **Output ของคำสั่งนี้:**
   ```bash
   which php
   pwd
   ls -la worker/notification-worker.php
   ```

2. **Log file:**
   ```bash
   tail -50 logs/notification-worker.log
   ```

3. **Cron jobs ปัจจุบัน:**
   ```bash
   crontab -l
   ```
