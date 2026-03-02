<?php
/**
 * Test Group Members Data
 * Check if database has member data
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== Testing Group Members Data ===\n\n";
    
    // Check line_groups table
    echo "1. Groups in database:\n";
    $stmt = $db->query("SELECT id, group_id, group_name, member_count FROM line_groups LIMIT 5");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($groups as $group) {
        echo "  - ID: {$group['id']}, Group ID: {$group['group_id']}, Name: {$group['group_name']}, Member Count: {$group['member_count']}\n";
    }
    
    echo "\n2. Members in database:\n";
    $stmt = $db->query("SELECT * FROM line_group_members LIMIT 10");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($members)) {
        echo "  ❌ NO MEMBERS FOUND IN DATABASE!\n";
        echo "  This is why the UI shows 0 members.\n\n";
        
        echo "3. Checking table structure:\n";
        $stmt = $db->query("DESCRIBE line_group_members");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  Columns: " . implode(', ', array_column($columns, 'Field')) . "\n\n";
        
        echo "4. Sample INSERT statement to add test data:\n";
        echo "  INSERT INTO line_group_members (group_id, line_user_id, display_name, is_active, joined_at)\n";
        echo "  VALUES (1, 'U1234567890abcdef', 'Test User', 1, NOW());\n\n";
        
    } else {
        echo "  ✓ Found " . count($members) . " members\n";
        foreach ($members as $member) {
            echo "  - Group ID: {$member['group_id']}, User: {$member['line_user_id']}, Name: {$member['display_name']}\n";
        }
        
        echo "\n3. Testing API query for group_id = 1:\n";
        $stmt = $db->prepare("SELECT * FROM line_group_members WHERE group_id = ?");
        $stmt->execute([1]);
        $group1Members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($group1Members)) {
            echo "  ❌ No members for group_id = 1\n";
            echo "  Try another group ID that has members.\n";
        } else {
            echo "  ✓ Found " . count($group1Members) . " members for group_id = 1\n";
            foreach ($group1Members as $member) {
                echo "    - {$member['display_name']} ({$member['line_user_id']})\n";
            }
        }
    }
    
    echo "\n4. Testing messages:\n";
    $stmt = $db->query("SELECT COUNT(*) as count FROM line_group_messages");
    $messageCount = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  Total messages: {$messageCount['count']}\n";
    
    if ($messageCount['count'] > 0) {
        $stmt = $db->query("SELECT group_id, COUNT(*) as count FROM line_group_messages GROUP BY group_id LIMIT 5");
        $messageCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  Messages by group:\n";
        foreach ($messageCounts as $row) {
            echo "    - Group ID {$row['group_id']}: {$row['count']} messages\n";
        }
    }
    
    echo "\n=== Test Complete ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
