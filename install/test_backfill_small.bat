@echo off
cd /d "c:\Users\Administrator\Downloads\inbox-master\re-ya\install"
php backfill_odoo_sync_tables.php --batch=100 --offset=0
pause
