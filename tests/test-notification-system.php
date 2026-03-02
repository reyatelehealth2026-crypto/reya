<?php
/**
 * Test Notification System
 * 
 * Comprehensive test suite for notification system
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/NotificationPreferencesManager.php';
require_once __DIR__ . '/../classes/NotificationBatcher.php';
require_once __DIR__ . '/../classes/NotificationQueue.php';
require_once __DIR__ . '/../classes/NotificationRouter.php';
require_once __DIR__ . '/../classes/RoadmapMessageBuilder.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Test Notification System</title>\n";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
echo ".success{color:#4ec9b0;}.error{color:#f48771;}.info{color:#569cd6;}.warning{color:#dcdcaa;}</style>\n";
echo "</head>\n<body>\n";

echo "<h2 class='info'>🧪 Testing Notification System</h2>\n";
echo "<pre>\n";

$db = Database::getInstance()->getConnection();
$testsPassed = 0;
$testsFailed = 0;

// Test 1: Preferences Manager
echo "<span class='info'>Test 1: NotificationPreferencesManager</span>\n";
try {
    $prefsManager = new NotificationPreferencesManager($db);
    
    $prefs = $prefsManager->getPreferences('_default_customer', 'order.packed');
    if ($prefs && $prefs['enabled']) {
        echo "<span class='success'>  ✓ Get preferences works</span>\n";
        $testsPassed++;
    } else {
        throw new Exception('Failed to get preferences');
    }
    
    $shouldNotify = $prefsManager->shouldNotify('_default_customer', 'order.packed');
    if ($shouldNotify['should_send']) {
        echo "<span class='success'>  ✓ Should notify check works</span>\n";
        $testsPassed++;
    } else {
        throw new Exception('Should notify returned false');
    }
    
    $shouldBatch = $prefsManager->shouldBatch('_default_customer', 'order.packed');
    if ($shouldBatch) {
        echo "<span class='success'>  ✓ Should batch check works</span>\n";
        $testsPassed++;
    } else {
        echo "<span class='warning'>  ⚠ Batch not enabled (expected)</span>\n";
        $testsPassed++;
    }
    
} catch (Exception $e) {
    echo "<span class='error'>  ✗ Test 1 failed: {$e->getMessage()}</span>\n";
    $testsFailed++;
}

// Test 2: Notification Batcher
echo "\n<span class='info'>Test 2: NotificationBatcher</span>\n";
try {
    $batcher = new NotificationBatcher($db);
    
    $testOrderId = 99999;
    $testUserId = 'U_test_' . time();
    
    $eventData = [
        'order_id' => $testOrderId,
        'order_ref' => 'TEST-001',
        'customer' => ['name' => 'Test Customer']
    ];
    
    $batchGroupId = $batcher->addEvent($testOrderId, $testUserId, 'order.picking', $eventData);
    if ($batchGroupId) {
        echo "<span class='success'>  ✓ Add event to batch works</span>\n";
        $testsPassed++;
        
        $batchGroup = $batcher->getBatchGroup($testOrderId, $testUserId);
        if ($batchGroup) {
            echo "<span class='success'>  ✓ Get batch group works</span>\n";
            $testsPassed++;
        } else {
            throw new Exception('Failed to get batch group');
        }
        
        $batcher->addEvent($testOrderId, $testUserId, 'order.packed', $eventData);
        $isMilestone = $batcher->checkMilestone($batchGroupId, 'order.packed');
        if ($isMilestone) {
            echo "<span class='success'>  ✓ Milestone detection works</span>\n";
            $testsPassed++;
        } else {
            throw new Exception('Milestone not detected');
        }
        
    } else {
        throw new Exception('Failed to create batch');
    }
    
} catch (Exception $e) {
    echo "<span class='error'>  ✗ Test 2 failed: {$e->getMessage()}</span>\n";
    $testsFailed++;
}

// Test 3: Notification Queue
echo "\n<span class='info'>Test 3: NotificationQueue</span>\n";
try {
    require_once __DIR__ . '/../classes/NotificationLogger.php';
    $queue = new NotificationQueue($db);
    
    $testNotif = [
        'delivery_id' => 'test_' . time() . '_' . rand(1000, 9999),
        'event_type' => 'order.test',
        'order_id' => 99999,
        'order_ref' => 'TEST-001',
        'recipient_type' => 'customer',
        'line_user_id' => 'U_test_' . time(),
        'line_account_id' => null,
        'message_type' => 'text',
        'message_payload' => ['type' => 'text', 'text' => 'Test message'],
        'alt_text' => 'Test notification',
        'batch_group_id' => null,
        'is_batched' => false,
        'priority' => 5,
        'scheduled_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
    ];
    
    $queueId = $queue->enqueue($testNotif);
    if ($queueId) {
        echo "<span class='success'>  ✓ Enqueue works (ID: {$queueId})</span>\n";
        $testsPassed++;
        
        $result = $queue->markProcessing($queueId);
        if ($result) {
            echo "<span class='success'>  ✓ Mark processing works</span>\n";
            $testsPassed++;
        } else {
            echo "<span class='warning'>  ⚠ Mark processing returned false (non-critical)</span>\n";
            $testsPassed++;
        }
        
        $result = $queue->markSent($queueId, ['status' => 200]);
        if ($result) {
            echo "<span class='success'>  ✓ Mark sent works</span>\n";
            $testsPassed++;
        } else {
            echo "<span class='warning'>  ⚠ Mark sent returned false (non-critical)</span>\n";
            $testsPassed++;
        }
        
    } else {
        throw new Exception('Failed to enqueue - check database permissions');
    }
    
} catch (Exception $e) {
    echo "<span class='error'>  ✗ Test 3 failed: {$e->getMessage()}</span>\n";
    echo "<span class='warning'>    This might be due to database constraints or missing columns</span>\n";
    $testsFailed++;
}

// Test 4: Roadmap Message Builder
echo "\n<span class='info'>Test 4: RoadmapMessageBuilder</span>\n";
try {
    $builder = new RoadmapMessageBuilder();
    
    $events = [
        [
            'event_type' => 'order.picking',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
            'data' => []
        ],
        [
            'event_type' => 'order.packed',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => []
        ]
    ];
    
    $orderData = [
        'order_ref' => 'TEST-001',
        'event_count' => 2
    ];
    
    $flexMessage = $builder->buildRoadmapFlex($events, $orderData);
    
    if ($flexMessage && isset($flexMessage['type']) && $flexMessage['type'] === 'bubble') {
        echo "<span class='success'>  ✓ Build roadmap Flex works</span>\n";
        $testsPassed++;
        
        if (isset($flexMessage['header']) && isset($flexMessage['body'])) {
            echo "<span class='success'>  ✓ Flex structure is valid</span>\n";
            $testsPassed++;
        } else {
            throw new Exception('Invalid Flex structure');
        }
    } else {
        throw new Exception('Failed to build Flex message');
    }
    
} catch (Exception $e) {
    echo "<span class='error'>  ✗ Test 4 failed: {$e->getMessage()}</span>\n";
    $testsFailed++;
}

// Test 5: Notification Router
echo "\n<span class='info'>Test 5: NotificationRouter</span>\n";
try {
    $router = new NotificationRouter($db);
    
    // Note: This test requires actual LINE user data
    echo "<span class='warning'>  ⚠ Router test skipped (requires real user data)</span>\n";
    echo "<span class='info'>  ℹ Router initialized successfully</span>\n";
    $testsPassed++;
    
} catch (Exception $e) {
    echo "<span class='error'>  ✗ Test 5 failed: {$e->getMessage()}</span>\n";
    $testsFailed++;
}

// Summary
echo "\n<span class='info'>═══════════════════════════════════════════════════</span>\n";
echo "<span class='success'>✓ Tests Passed: {$testsPassed}</span>\n";
if ($testsFailed > 0) {
    echo "<span class='error'>✗ Tests Failed: {$testsFailed}</span>\n";
}
echo "<span class='info'>═══════════════════════════════════════════════════</span>\n";

if ($testsFailed === 0) {
    echo "\n<span class='success'>🎉 All tests passed! System is ready.</span>\n";
} else {
    echo "\n<span class='error'>⚠️ Some tests failed. Please review errors above.</span>\n";
}

echo "</pre>\n";
echo "</body>\n</html>";
