<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

// Sample webhook data for testing
$sampleWebhooks = [
    [
        'event_type' => 'order_state_change',
        'order_name' => 'SO12345',
        'customer' => [
            'id' => '47277',
            'ref' => '47277',
            'name' => 'อติรุจ ชวลิตวัชระ',
            'credit_limit' => '10000',
            'total_due' => '2500',
            'overdue_amount' => '500'
        ],
        'amount_total' => '5000',
        'line_user_id' => 'U1234567890'
    ],
    [
        'event_type' => 'invoice_created',
        'invoice_number' => 'INV12345',
        'order_name' => 'SO12345',
        'customer' => [
            'id' => '47277',
            'ref' => '47277',
            'name' => 'อติรุจ ชวลิตวัชระ'
        ],
        'amount_total' => '5000',
        'invoice_date' => '2025-02-15',
        'due_date' => '2025-03-15',
        'state' => 'posted',
        'line_user_id' => 'U1234567890'
    ],
    [
        'event_type' => 'credit_updated',
        'customer' => [
            'id' => '47277',
            'ref' => '47277',
            'name' => 'อติรุจ ชวลิตวัชระ',
            'credit_limit' => '15000',
            'total_due' => '3000',
            'overdue_amount' => '750'
        ],
        'line_user_id' => 'U1234567890'
    ]
];

echo "Seeding webhook data...\n";

foreach ($sampleWebhooks as $webhook) {
    $stmt = $db->prepare("
        INSERT INTO odoo_webhooks_log (
            event_type, status, payload, processed_at, line_user_id, order_id
        ) VALUES (
            ?, 'success', ?, NOW(), ?, 
            JSON_UNQUOTE(JSON_EXTRACT(? , '$.order_name'))
        )
    ");
    
    $payload = json_encode($webhook, JSON_UNESCAPED_UNICODE);
    $stmt->execute([
        $webhook['event_type'],
        $payload,
        $webhook['line_user_id'],
        $payload
    ]);
    
    echo "Inserted: {$webhook['event_type']} for {$webhook['customer']['name']}\n";
}

echo "\nDone! Check user detail page for credit and invoice data.\n";
