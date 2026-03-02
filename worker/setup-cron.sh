#!/bin/bash
# Simple Cron Job Setup for Notification Worker
# วิธีใช้: bash worker/setup-cron.sh

# หา path ของ PHP
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    echo "❌ ไม่พบ PHP กรุณาติดตั้ง PHP ก่อน"
    exit 1
fi

# หา path ของโปรเจค
PROJECT_PATH=$(pwd)
WORKER_PATH="$PROJECT_PATH/worker/notification-worker.php"
LOG_PATH="$PROJECT_PATH/logs/notification-worker.log"

# สร้างโฟลเดอร์ logs ถ้ายังไม่มี
mkdir -p "$PROJECT_PATH/logs"

# Cron job command
CRON_CMD="* * * * * cd $PROJECT_PATH && $PHP_PATH $WORKER_PATH >> $LOG_PATH 2>&1 &"

echo "📋 Cron Job ที่จะติดตั้ง:"
echo "$CRON_CMD"
echo ""
echo "ต้องการติดตั้งหรือไม่? (y/n)"
read -r response

if [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
    # เพิ่ม cron job
    (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -
    echo "✅ ติดตั้ง Cron Job เรียบร้อย!"
    echo ""
    echo "ตรวจสอบ Cron Jobs ทั้งหมด:"
    crontab -l
    echo ""
    echo "ดู log ได้ที่: tail -f $LOG_PATH"
else
    echo "❌ ยกเลิกการติดตั้ง"
fi
