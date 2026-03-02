<?php
/**
 * Test Group Message Saving
 * Verify that messages are being saved correctly to database
 */

require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance()->getConnection();

echo "=== Group Message Save Test ===\n\n";

// Get a test group
$stmt = $db->query("SELECT * FROM line_groups WHERE is_active = 1 LIMIT 1");
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    echo "❌ No active groups found\n";
    exit;
}

echo "✅ Testing with group: {$group['group_name']} (ID: {$group['id']})\n\n";

// Check recent messages
echo "--- Recent Messages ---\n";
$stmt = $db->prepare("
    SELECT gm.*, lgm.display_name 
    FROM line_group_messages gm
    LEFT JOIN line_group_members lgm ON gm.group_id = lgm.group_id AND gm.line_user_id = lgm.line_user_id
    WHERE gm.group_id = ?
    ORDER BY gm.created_at DESC
    LIMIT 10
");
$stmt->execute([$group['id']]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($messages)) {
    echo "⚠️  No messages found in database\n";
} else {
    echo "✅ Found " . count($messages) . " messages:\n\n";
    foreach ($messages as $msg) {
        $sender = $msg['display_name'] ?: $msg['line_user_id'];
        $content = mb_substr($msg['content'], 0, 50);
        $time = $msg['created_at'];
        echo "  [{$time}] {$sender}: {$content}\n";
    }
}

echo "\n--- Group Statistics ---\n";
echo "Total Messages: {$group['total_messages']}\n";
echo "Member Count: {$group['member_count']}\n";
echo "Last Activity: {$group['last_activity_at']}\n";

// Check if webhook is saving messages
echo "\n--- Webhook Event Check ---\n";
$stmt = $db->query("SELECT COUNT(*) FROM webhook_events WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$recentEvents = $stmt->fetchColumn();
echo "Recent webhook events (last hour): {$recentEvents}\n";

// Check message count consistency
$stmt = $db->prepare("SELECT COUNT(*) FROM line_group_messages WHERE group_id = ?");
$stmt->execute([$group['id']]);
$actualCount = $stmt->fetchColumn();

echo "\n--- Consistency Check ---\n";
echo "Group total_messages field: {$group['total_messages']}\n";
echo "Actual messages in database: {$actualCount}\n";

if ($actualCount == $group['total_messages']) {
    echo "✅ Counts match perfectly!\n";
} else {
    echo "⚠️  Count mismatch - may need to sync\n";
}

echo "\n=== Test Complete ===\n";
