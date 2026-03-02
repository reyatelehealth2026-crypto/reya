<?php
/**
 * Backfill NULL order_id in odoo_webhooks_log from order_name in payload.
 * 
 * For rows where order_id IS NULL but payload contains an order_name,
 * look up the order_id from another row that has the same order_name.
 * 
 * Run once: php install/backfill_order_id.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getInstance()->getConnection();

echo "=== Backfill order_id from order_name ===\n\n";

// Step 1: Find rows with NULL order_id but valid order_name in payload
$stmt = $pdo->query("
    SELECT id, delivery_id, event_type,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.order_name')) as order_name,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.order_id')) as payload_order_id,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.order.id')) as payload_order_obj_id,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.id')) as payload_root_id
    FROM odoo_webhooks_log
    WHERE order_id IS NULL
    ORDER BY id DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($rows) . " rows with NULL order_id\n";

$fixed = 0;
$skipped = 0;

// Build order_name → order_id lookup from existing data
$lookupStmt = $pdo->prepare("
    SELECT order_id FROM odoo_webhooks_log
    WHERE order_id IS NOT NULL
      AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.order_name')) = ?
    ORDER BY id DESC LIMIT 1
");

$updateStmt = $pdo->prepare("UPDATE odoo_webhooks_log SET order_id = ? WHERE id = ?");

foreach ($rows as $row) {
    $resolvedId = null;

    // Try direct payload fields first
    foreach (['payload_order_id', 'payload_order_obj_id', 'payload_root_id'] as $field) {
        $val = $row[$field] ?? null;
        if ($val !== null && $val !== '' && $val !== 'null' && is_numeric($val)) {
            $resolvedId = (int) $val;
            break;
        }
    }

    // Fallback: lookup by order_name
    if (!$resolvedId && $row['order_name'] && $row['order_name'] !== '' && $row['order_name'] !== 'null') {
        $lookupStmt->execute([$row['order_name']]);
        $found = $lookupStmt->fetch(PDO::FETCH_ASSOC);
        if ($found && $found['order_id']) {
            $resolvedId = (int) $found['order_id'];
        }
    }

    if ($resolvedId) {
        $updateStmt->execute([$resolvedId, $row['id']]);
        $fixed++;
        if ($fixed <= 20) {
            echo "  Fixed #{$row['id']} ({$row['event_type']}) order_name={$row['order_name']} → order_id={$resolvedId}\n";
        }
    } else {
        $skipped++;
    }
}

echo "\nDone: {$fixed} fixed, {$skipped} could not resolve\n";

// Step 2: Show duplicate stats
echo "\n=== Duplicate Stats (today) ===\n";
$dupStats = $pdo->query("
    SELECT status, COUNT(*) as cnt
    FROM odoo_webhooks_log
    WHERE DATE(COALESCE(processed_at, created_at)) = CURDATE()
    GROUP BY status
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($dupStats as $ds) {
    echo "  {$ds['status']}: {$ds['cnt']}\n";
}

echo "\nDone.\n";
