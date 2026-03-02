<?php
/**
 * Simple Queue Test
 * Direct test without test framework
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Simple Queue Test</title>\n";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
echo ".success{color:#4ec9b0;}.error{color:#f48771;}.info{color:#569cd6;}</style>\n";
echo "</head>\n<body>\n";

echo "<h2 class='info'>🧪 Simple Queue Test</h2>\n";
echo "<pre>\n";

try {
    $db = Database::getInstance()->getConnection();
    $queue = new NotificationQueue($db);
    
    echo "<span class='info'>Creating test notification...</span>\n";
    
    $testNotif = [
        'delivery_id' => 'simple_test_' . time() . '_' . rand(1000, 9999),
        'event_type' => 'order.test',
        'order_id' => 99999,
        'order_ref' => 'TEST-SIMPLE',
        'recipient_type' => 'customer',
        'line_user_id' => 'U_simple_test_' . time(),
        'line_account_id' => null,
        'message_type' => 'text',
        'message_payload' => ['type' => 'text', 'text' => 'Simple test message'],
        'alt_text' => 'Test notification',
        'batch_group_id' => null,
        'is_batched' => false,
        'priority' => 5,
        'scheduled_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
    ];
    
    echo "<span class='info'>Calling queue->enqueue()...</span>\n";
    
    $queueId = $queue->enqueue($testNotif);
    
    if ($queueId) {
        echo "<span class='success'>✓ SUCCESS! Queue ID: {$queueId}</span>\n\n";
        
        // Verify it was inserted
        $stmt = $db->prepare("SELECT * FROM odoo_notification_queue WHERE id = ?");
        $stmt->execute([$queueId]);
        $inserted = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inserted) {
            echo "<span class='success'>✓ Verified in database:</span>\n";
            echo "  ID: {$inserted['id']}\n";
            echo "  Delivery ID: {$inserted['delivery_id']}\n";
            echo "  Event Type: {$inserted['event_type']}\n";
            echo "  Status: {$inserted['status']}\n";
            echo "  Created: {$inserted['created_at']}\n";
            
            // Clean up
            $db->exec("DELETE FROM odoo_notification_queue WHERE id = {$queueId}");
            echo "\n<span class='success'>✓ Cleanup done</span>\n";
        }
        
    } else {
        echo "<span class='error'>✗ FAILED: enqueue() returned null</span>\n";
        
        // Check error log
        $errors = error_get_last();
        if ($errors) {
            echo "<span class='error'>Last error: {$errors['message']}</span>\n";
        }
    }
    
    echo "\n<span class='success'>═══════════════════════════════════════════════════</span>\n";
    echo "<span class='success'>Test complete!</span>\n";
    echo "<span class='success'>═══════════════════════════════════════════════════</span>\n";
    
} catch (Exception $e) {
    echo "<span class='error'>✗ Exception: {$e->getMessage()}</span>\n";
    echo "<span class='error'>File: {$e->getFile()}:{$e->getLine()}</span>\n";
    echo "<span class='error'>Trace:</span>\n";
    echo "<span class='error'>" . $e->getTraceAsString() . "</span>\n";
}

echo "</pre>\n";
echo "</body>\n</html>";
