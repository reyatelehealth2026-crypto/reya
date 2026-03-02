<?php
/**
 * Test script for inbox-groups.php API
 * Tests all endpoints to verify functionality
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "=== Testing inbox-groups.php API ===\n\n";

// Test 1: Get Groups
echo "Test 1: GET /api/inbox-groups.php?action=get_groups\n";
$url = "https://cny.re-ya.com/api/inbox-groups.php?action=get_groups&line_account_id=1";
echo "URL: $url\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Admin-ID: 1',
    'X-Line-Account-ID: 1'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 2: Get Stats
echo "Test 2: GET /api/inbox-groups.php?action=get_stats\n";
$url = "https://cny.re-ya.com/api/inbox-groups.php?action=get_stats&line_account_id=1";
echo "URL: $url\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Admin-ID: 1',
    'X-Line-Account-ID: 1'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 3: Check if groups exist in database
echo "Test 3: Check database for groups\n";
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT COUNT(*) as count FROM line_groups");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total groups in database: " . $result['count'] . "\n";
    
    if ($result['count'] > 0) {
        $stmt = $db->query("SELECT id, group_id, group_name, is_active FROM line_groups LIMIT 5");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample groups:\n";
        print_r($groups);
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
