<?php
/**
 * Test inbox-groups.php with correct line_account_id
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "=== Testing inbox-groups.php API (Fixed) ===\n\n";

// Test 1: Get Groups with line_account_id = 3
echo "Test 1: GET with line_account_id=3\n";
$url = "https://cny.re-ya.com/api/inbox-groups.php?action=get_groups&line_account_id=3";
echo "URL: $url\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Admin-ID: 1',
    'X-Line-Account-ID: 3'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
$data = json_decode($response, true);
echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
echo "Groups Count: " . count($data['data']['groups'] ?? []) . "\n";
echo "Stats: Total=" . ($data['data']['stats']['total'] ?? 0) . 
     ", Active=" . ($data['data']['stats']['active'] ?? 0) . "\n\n";

// Test 2: Get Groups without line_account_id (should return all)
echo "Test 2: GET without line_account_id (all groups)\n";
$url = "https://cny.re-ya.com/api/inbox-groups.php?action=get_groups";
echo "URL: $url\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
$data = json_decode($response, true);
echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
echo "Groups Count: " . count($data['data']['groups'] ?? []) . "\n";
echo "Stats: Total=" . ($data['data']['stats']['total'] ?? 0) . 
     ", Active=" . ($data['data']['stats']['active'] ?? 0) . "\n\n";

// Test 3: Show first 3 groups
if (!empty($data['data']['groups'])) {
    echo "Sample Groups:\n";
    foreach (array_slice($data['data']['groups'], 0, 3) as $group) {
        echo "  - ID: {$group['id']}, Name: {$group['group_name']}, Active: {$group['is_active']}\n";
    }
}

echo "\n=== Test Complete ===\n";
