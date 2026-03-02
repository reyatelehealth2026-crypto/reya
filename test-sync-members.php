<?php
/**
 * Test Script: Sync Group Members from LINE API
 * 
 * Usage: php test-sync-members.php [group_db_id]
 * Example: php test-sync-members.php 1
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/LineAPI.php';
require_once __DIR__ . '/classes/LineAccountManager.php';

// Get group ID from command line
$groupDbId = isset($argv[1]) ? (int)$argv[1] : 1;

echo "🔍 Testing Member Sync for Group ID: {$groupDbId}\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Get group info
    echo "1️⃣ Fetching group info...\n";
    $stmt = $db->prepare("SELECT * FROM line_groups WHERE id = ?");
    $stmt->execute([$groupDbId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        die("❌ Group not found!\n");
    }
    
    echo "✅ Group found: {$group['group_name']}\n";
    echo "   - LINE Group ID: {$group['group_id']}\n";
    echo "   - Account ID: {$group['line_account_id']}\n";
    echo "   - Active: " . ($group['is_active'] ? 'Yes' : 'No') . "\n";
    echo "   - Current member count: {$group['member_count']}\n\n";
    
    if (!$group['is_active']) {
        die("❌ Cannot sync inactive group!\n");
    }
    
    // Initialize LINE API
    echo "2️⃣ Initializing LINE API...\n";
    $manager = new LineAccountManager($db);
    $line = $manager->getLineAPI($group['line_account_id']);
    echo "✅ LINE API initialized\n\n";
    
    // Get member IDs (paginated)
    echo "3️⃣ Fetching member IDs from LINE API...\n";
    $allMemberIds = [];
    $start = null;
    $pageCount = 0;
    $maxPages = 10; // Limit for testing
    
    do {
        $result = $line->getGroupMemberIds($group['group_id'], $start);
        $memberIds = $result['memberIds'] ?? [];
        $start = $result['next'] ?? null;
        
        $allMemberIds = array_merge($allMemberIds, $memberIds);
        $pageCount++;
        
        echo "   Page {$pageCount}: " . count($memberIds) . " members\n";
        
        if ($pageCount >= $maxPages) {
            echo "   ⚠️ Reached max pages limit ({$maxPages}) for testing\n";
            break;
        }
    } while ($start !== null);
    
    echo "✅ Total members found: " . count($allMemberIds) . "\n\n";
    
    if (empty($allMemberIds)) {
        die("❌ No members found in group!\n");
    }
    
    // Show first 5 member IDs
    echo "   First 5 member IDs:\n";
    foreach (array_slice($allMemberIds, 0, 5) as $userId) {
        echo "   - {$userId}\n";
    }
    echo "\n";
    
    // Mark existing members as inactive
    echo "4️⃣ Marking existing members as inactive...\n";
    $stmt = $db->prepare("UPDATE line_group_members SET is_active = 0 WHERE group_id = ?");
    $stmt->execute([$groupDbId]);
    echo "✅ Marked " . $stmt->rowCount() . " existing members as inactive\n\n";
    
    // Fetch profiles and save (limit to first 5 for testing)
    echo "5️⃣ Fetching member profiles (first 5 only for testing)...\n";
    $syncedCount = 0;
    $errorCount = 0;
    $testLimit = 5;
    
    foreach (array_slice($allMemberIds, 0, $testLimit) as $userId) {
        try {
            echo "   Fetching profile for {$userId}... ";
            
            // Get member profile
            $profile = $line->getGroupMemberProfile($group['group_id'], $userId);
            
            if (empty($profile) || !isset($profile['userId'])) {
                echo "❌ Failed (no profile data)\n";
                $errorCount++;
                continue;
            }
            
            echo "✅ {$profile['displayName']}\n";
            
            // Insert or update member
            $stmt = $db->prepare("
                INSERT INTO line_group_members 
                (group_id, line_user_id, display_name, picture_url, is_active, joined_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, 1, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    display_name = VALUES(display_name),
                    picture_url = VALUES(picture_url),
                    is_active = 1,
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $groupDbId,
                $profile['userId'],
                $profile['displayName'] ?? 'Unknown',
                $profile['pictureUrl'] ?? null
            ]);
            
            $syncedCount++;
            
            // Rate limiting
            usleep(50000); // 50ms
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    echo "\n✅ Synced {$syncedCount} members successfully\n";
    if ($errorCount > 0) {
        echo "⚠️ {$errorCount} errors occurred\n";
    }
    echo "\n";
    
    // Update group member count
    echo "6️⃣ Updating group member count...\n";
    $stmt = $db->prepare("
        UPDATE line_groups 
        SET member_count = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$syncedCount, $groupDbId]);
    echo "✅ Group member count updated\n\n";
    
    // Show results
    echo "7️⃣ Final Results:\n";
    $stmt = $db->prepare("
        SELECT 
            display_name,
            is_active,
            total_messages,
            created_at,
            updated_at
        FROM line_group_members
        WHERE group_id = ?
        ORDER BY is_active DESC, display_name ASC
        LIMIT 10
    ");
    $stmt->execute([$groupDbId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Members in database (showing first 10):\n";
    foreach ($members as $member) {
        $status = $member['is_active'] ? '✅' : '❌';
        echo "   {$status} {$member['display_name']} (messages: {$member['total_messages']})\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ Test completed successfully!\n\n";
    
    echo "📊 Summary:\n";
    echo "   - Total members found: " . count($allMemberIds) . "\n";
    echo "   - Members synced (test): {$syncedCount}\n";
    echo "   - Errors: {$errorCount}\n";
    echo "   - Pages fetched: {$pageCount}\n";
    echo "\n";
    
    echo "💡 To sync all members, use the web interface or modify this script.\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
