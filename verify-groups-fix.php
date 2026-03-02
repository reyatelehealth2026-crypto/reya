<?php
/**
 * Final verification of inbox-groups.php fix
 */

echo "=== Final Verification ===\n\n";

// Test 1: With line_account_id = 3
echo "Test 1: With line_account_id=3\n";
$ch = curl_init("https://cny.re-ya.com/api/inbox-groups.php?action=get_groups&line_account_id=3");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
echo "Groups: " . count($data['data']['groups'] ?? []) . "\n";
echo "Stats Total: " . ($data['data']['stats']['total'] ?? 0) . "\n\n";

// Test 2: Without line_account_id
echo "Test 2: Without line_account_id\n";
$ch = curl_init("https://cny.re-ya.com/api/inbox-groups.php?action=get_groups");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
echo "Groups: " . count($data['data']['groups'] ?? []) . "\n";
echo "Stats Total: " . ($data['data']['stats']['total'] ?? 0) . "\n\n";

// Test 3: With line_account_id = 0 (should return all)
echo "Test 3: With line_account_id=0\n";
$ch = curl_init("https://cny.re-ya.com/api/inbox-groups.php?action=get_groups&line_account_id=0");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
echo "Groups: " . count($data['data']['groups'] ?? []) . "\n";
echo "Stats Total: " . ($data['data']['stats']['total'] ?? 0) . "\n\n";

echo "Expected Results:\n";
echo "- Test 1: 16 groups (filtered by account 3)\n";
echo "- Test 2: 16 groups (all groups)\n";
echo "- Test 3: 16 groups (all groups)\n";
