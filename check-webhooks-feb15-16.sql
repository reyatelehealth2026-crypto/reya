-- ตรวจสอบ Webhooks วันที่ 15-16 กุมภาพันธ์ 2026
-- รันใน phpMyAdmin หรือ MySQL client

-- 1. นับจำนวน webhooks วันที่ 15 ก.พ. 2026
SELECT 
    'วันที่ 15 ก.พ. 2026' as วัน,
    COUNT(*) as จำนวน_webhooks,
    COUNT(DISTINCT event_type) as จำนวน_event_types,
    COUNT(DISTINCT order_id) as จำนวน_orders
FROM odoo_webhooks_log
WHERE DATE(processed_at) = '2026-02-15';

-- 2. นับจำนวน webhooks วันที่ 16 ก.พ. 2026
SELECT 
    'วันที่ 16 ก.พ. 2026' as วัน,
    COUNT(*) as จำนวน_webhooks,
    COUNT(DISTINCT event_type) as จำนวน_event_types,
    COUNT(DISTINCT order_id) as จำนวน_orders
FROM odoo_webhooks_log
WHERE DATE(processed_at) = '2026-02-16';

-- 3. รายละเอียด webhooks วันที่ 15-16 ก.พ. 2026 (แสดง 50 รายการล่าสุด)
SELECT 
    id,
    delivery_id,
    event_type,
    status,
    order_id,
    processed_at as เวลา,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.order_name')) as order_name,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.customer.name')) as customer_name,
    error_message
FROM odoo_webhooks_log
WHERE DATE(processed_at) BETWEEN '2026-02-15' AND '2026-02-16'
ORDER BY processed_at DESC
LIMIT 50;

-- 4. สรุปตาม event_type วันที่ 15-16 ก.พ. 2026
SELECT 
    event_type,
    COUNT(*) as จำนวน,
    COUNT(CASE WHEN status = 'success' THEN 1 END) as สำเร็จ,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as ล้มเหลว
FROM odoo_webhooks_log
WHERE DATE(processed_at) BETWEEN '2026-02-15' AND '2026-02-16'
GROUP BY event_type
ORDER BY จำนวน DESC;

-- 5. สรุปตามชั่วโมง วันที่ 15-16 ก.พ. 2026
SELECT 
    DATE(processed_at) as วัน,
    HOUR(processed_at) as ชั่วโมง,
    COUNT(*) as จำนวน_webhooks
FROM odoo_webhooks_log
WHERE DATE(processed_at) BETWEEN '2026-02-15' AND '2026-02-16'
GROUP BY วัน, ชั่วโมง
ORDER BY วัน, ชั่วโมง;
