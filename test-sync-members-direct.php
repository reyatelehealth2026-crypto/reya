<?php
/**
 * Test Sync Members Direct Call
 * Test the sync_members action directly
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/LineAPI.php';
require_once 'classes/LineAccountManager.php';

$db = Database::getInstance()->getConnection();

echo "=== Direct Sync Members Test ===\n\n";

// Get a test group
$stmt = $db->query("SELECT * FROM line_groups WHERE is_active = 1 LIMIT 1");
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    echo "❌ No active groups found\n";
    exit;
}

echo "Testing with group: {$group['group_name']} (ID: {$group['id']})\n";
echo "LINE Group ID: {$group['group_id']}\n";
echo "LINE Account ID: {$group['line_account_id']}\n\n";

try {
    $manager = new LineAccountManager($db);
    $line = $manager->getLineAPI($group['line_account_id']);
    
    echo "--- Fetching member IDs from LINE API ---\n";
    
    $allMemberIds = [];
    $start = null;
    $pageCount = 0;
    
    do {
        $result = $line->getGroupMemberIds($group['group_id'], $start);
        $memberIds = $result['memberIds'] ?? [];
        $start = $result['next'] ?? null;
        
        $allMemberIds = array_merge($allMemberIds, $memberIds);
        $pageCount++;
        
        echo "Page {$pageCount}: " . count($memberIds) . " members\n";
        
        if ($pageCount >= 5) {
            echo "Stopping at 5 pages for testing...\n";
            break;
        }
    } while ($start !== null);
    
    echo "\n✅ Total member IDs found: " . count($allMemberIds) . "\n\n";
    
    if (empty($allMemberIds)) {
        echo "❌ No members found\n";
        exit;
    }
    
    echo "--- Fetching profiles (first 5 members) ---\n";
    $count = 0;
    foreach (array_slice($allMemberIds, 0, 5) as $userId) {
        try {
            $profile = $line->getGroupMemberProfile($group['group_id'], $userId);
            echo "✅ {$profile['displayName']} ({$userId})\n";
            $count++;
            usleep(50000); // 50ms delay
        } catch (Exception $e) {
            echo "❌ Failed to get profile for {$userId}: {$e->getMessage()}\n";
        }
    }
    
    echo "\n✅ Successfully fetched {$count} profiles\n";
    
    echo "\n--- Current members in database ---\n";
    $stmt = $db->prepare("SELECT COUNT(*) FROM line_group_members WHERE group_id = ?");
    $stmt->execute([$group['id']]);
    $dbCount = $stmt->fetchColumn();
    echo "Members in DB: {$dbCount}\n";
    
    echo "\n=== Test Complete ===\n";
    echo "LINE API is working correctly!\n";
    echo "If sync button doesn't work, check:\n";
    echo "1. Browser console for errors\n";
    echo "2. Network tab for failed requests\n";
    echo "3. Next.js server logs\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
}
