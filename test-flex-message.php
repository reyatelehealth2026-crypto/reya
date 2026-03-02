<?php
/**
 * Test Flex Message Sending
 * Debug script to test if Flex Messages can be sent via LINE API
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/LineAPI.php';

echo "=== Flex Message Test ===\n\n";

// Get database connection
try {
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connected\n\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Get LINE account info
$lineAccountId = 3; // Default account
$stmt = $db->prepare("SELECT id, channel_access_token, channel_secret FROM line_accounts WHERE id = ?");
$stmt->execute([$lineAccountId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    echo "❌ LINE account not found (ID: {$lineAccountId})\n";
    exit(1);
}

echo "LINE Account Info:\n";
echo "- ID: {$account['id']}\n";
echo "- Access Token: " . substr($account['channel_access_token'], 0, 20) . "...\n";
echo "- Secret: " . substr($account['channel_secret'], 0, 10) . "...\n\n";

// Get a test user
echo "Fetching test user...\n";
echo "Checking available tables...\n";

try {
    // First, check what tables exist
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Available tables: " . implode(', ', array_slice($tables, 0, 10)) . "...\n\n";
    
    // Try different possible table names
    $possibleTables = ['line_users', 'LineUser', 'line_user', 'users'];
    $userTable = null;
    
    foreach ($possibleTables as $table) {
        try {
            $stmt = $db->query("SELECT 1 FROM `{$table}` LIMIT 1");
            if ($stmt) {
                $userTable = $table;
                echo "✅ Found user table: {$table}\n";
                break;
            }
        } catch (Exception $e) {
            // Table doesn't exist, continue
        }
    }
    
    if (!$userTable) {
        echo "❌ Could not find user table. Available tables:\n";
        foreach ($tables as $table) {
            echo "  - {$table}\n";
        }
        exit(1);
    }
    
    // Now fetch a test user
    $stmt = $db->prepare("SELECT id, line_user_id, display_name, points, line_account_id FROM `{$userTable}` WHERE line_user_id IS NOT NULL LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ No users with LINE User ID found in table: {$userTable}\n";
        echo "Checking total users in table...\n";
        $count = $db->query("SELECT COUNT(*) FROM `{$userTable}`")->fetchColumn();
        echo "Total users in {$userTable}: {$count}\n";
        
        if ($count > 0) {
            echo "\nShowing first user (without line_user_id filter):\n";
            $stmt = $db->query("SELECT id, line_user_id, display_name, points, line_account_id FROM `{$userTable}` LIMIT 1");
            $sampleUser = $stmt->fetch(PDO::FETCH_ASSOC);
            print_r($sampleUser);
        }
        exit(1);
    }
    
    // If user is from different account, update credentials
    if (isset($user['line_account_id']) && $user['line_account_id'] != $lineAccountId) {
        echo "⚠️ User is from account {$user['line_account_id']}, updating credentials...\n";
        $lineAccountId = $user['line_account_id'];
        
        $stmt = $db->prepare("SELECT id, channel_access_token, channel_secret FROM line_accounts WHERE id = ?");
        $stmt->execute([$lineAccountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($account) {
            $line = new LineAPI($account['channel_access_token'], $account['channel_secret']);
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error fetching user: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nTest User:\n";
echo "- ID: {$user['id']}\n";
echo "- LINE User ID: {$user['line_user_id']}\n";
echo "- Name: " . ($user['display_name'] ?? 'N/A') . "\n";
echo "- Points: " . ($user['points'] ?? 0) . "\n";
echo "- Account ID: " . ($user['line_account_id'] ?? $lineAccountId) . "\n\n";

// Create LINE API instance
$line = new LineAPI($account['channel_access_token'], $account['channel_secret']);

// Create test Flex Message
$flexMessage = [
    'type' => 'flex',
    'altText' => '🎉 ทดสอบ Flex Message',
    'contents' => [
        'type' => 'bubble',
        'size' => 'kilo',
        'header' => [
            'type' => 'box',
            'layout' => 'vertical',
            'backgroundColor' => '#0C665D',
            'paddingAll' => '15px',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => '🧪 ทดสอบระบบ',
                    'color' => '#FFFFFF',
                    'weight' => 'bold',
                    'size' => 'lg',
                    'align' => 'center'
                ]
            ]
        ],
        'body' => [
            'type' => 'box',
            'layout' => 'vertical',
            'paddingAll' => '15px',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => 'ทดสอบส่ง Flex Message',
                    'weight' => 'bold',
                    'size' => 'md',
                    'align' => 'center'
                ],
                [
                    'type' => 'text',
                    'text' => 'ถ้าคุณเห็นข้อความนี้ แสดงว่าระบบทำงานปกติ',
                    'size' => 'sm',
                    'color' => '#888888',
                    'align' => 'center',
                    'margin' => 'md',
                    'wrap' => true
                ],
                [
                    'type' => 'separator',
                    'margin' => 'lg'
                ],
                [
                    'type' => 'text',
                    'text' => '✅ ระบบพร้อมใช้งาน',
                    'size' => 'sm',
                    'color' => '#10B981',
                    'align' => 'center',
                    'margin' => 'lg'
                ]
            ]
        ]
    ]
];

echo "Sending Flex Message...\n";
echo "JSON: " . json_encode($flexMessage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Send message
echo "Calling LINE API pushMessage()...\n";
try {
    $result = $line->pushMessage($user['line_user_id'], [$flexMessage]);
    
    echo "\nResult:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    if (isset($result['code']) && $result['code'] == 200) {
        echo "✅ Flex Message sent successfully!\n";
        echo "Check LINE app for the message.\n";
    } else {
        echo "❌ Failed to send Flex Message\n";
        if (isset($result['error'])) {
            echo "Error: {$result['error']}\n";
        }
        if (isset($result['message'])) {
            echo "Message: {$result['message']}\n";
        }
        if (isset($result['details'])) {
            echo "Details: " . json_encode($result['details'], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
