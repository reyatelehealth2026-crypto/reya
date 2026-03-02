<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
use Modules\Core\Database;

$db = Database::getInstance()->getConnection();

// Get 3 recent invoice webhooks and 3 recent order webhooks
$stmt = $db->prepare("
    (SELECT id, event_type, order_id, line_user_id,
            JSON_KEYS(payload) AS payload_keys,
            LEFT(payload, 800) AS payload_preview
     FROM odoo_webhooks_log
     WHERE event_type LIKE 'invoice.%'
     ORDER BY id DESC LIMIT 3)
    UNION ALL
    (SELECT id, event_type, order_id, line_user_id,
            JSON_KEYS(payload) AS payload_keys,
            LEFT(payload, 800) AS payload_preview
     FROM odoo_webhooks_log
     WHERE event_type LIKE 'sale.order.%'
     ORDER BY id DESC LIMIT 3)
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
