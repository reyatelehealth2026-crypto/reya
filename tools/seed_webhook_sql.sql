-- Insert sample webhook data for testing credit and invoice fallback
-- This will populate data for customer 47277 (อติรุจ ชวลิตวัชระ)

INSERT INTO odoo_webhooks_log (
    event_type, 
    status, 
    payload, 
    processed_at, 
    line_user_id, 
    order_id,
    delivery_id
) VALUES 
(
    'credit_updated',
    'success',
    '{"event_type":"credit_updated","customer":{"id":"47277","ref":"47277","name":"อติรุจ ชวลิตวัชระ","credit_limit":"15000","total_due":"3000","overdue_amount":"750"},"line_user_id":"U1234567890"}',
    NOW(),
    'U1234567890',
    NULL,
    'wh_credit_001'
),
(
    'invoice_created',
    'success',
    '{"event_type":"invoice_created","invoice_number":"INV12345","order_name":"SO12345","customer":{"id":"47277","ref":"47277","name":"อติรุจ ชวลิตวัชระ"},"amount_total":"5000","invoice_date":"2025-02-15","due_date":"2025-03-15","state":"posted","line_user_id":"U1234567890"}',
    NOW(),
    'U1234567890',
    12345,
    'wh_invoice_001'
),
(
    'order_state_change',
    'success',
    '{"event_type":"order_state_change","order_name":"SO12345","customer":{"id":"47277","ref":"47277","name":"อติรุจ ชวลิตวัชระ","credit_limit":"15000","total_due":"3000","overdue_amount":"750"},"amount_total":"5000","line_user_id":"U1234567890"}',
    NOW(),
    'U1234567890',
    12345,
    'wh_order_001'
);

-- Show inserted records
SELECT 
    event_type,
    line_user_id,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.customer.name')) as customer_name,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.customer.credit_limit')) as credit_limit,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.invoice_number')) as invoice_number
FROM odoo_webhooks_log 
WHERE line_user_id = 'U1234567890' 
ORDER BY processed_at DESC;
