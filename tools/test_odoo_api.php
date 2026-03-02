<?php
require_once __DIR__ . '/../config/config.php';

function testOdooAPI($endpoint, $params) {
    $url = 'https://erp.cnyrxapp.com' . $endpoint;
    $data = [
        'jsonrpc' => '2.0',
        'params' => $params
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Api-Key: 5pG-doAH1EEqHXurM3t466WAgKRYemmK7FANAB74o5A'
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "=== $endpoint ===\n";
    echo "HTTP Code: $httpCode\n";
    if ($error) {
        echo "cURL Error: $error\n";
    } else {
        echo "Response: " . $response . "\n\n";
    }
    echo str_repeat("-", 80) . "\n\n";
    
    return json_decode($response, true);
}

// Test with the actual linked line user ID
$lineUserId = 'U1234567890';

echo "Testing Odoo API for line_user_id: $lineUserId\n\n";

// Test Credit Status
testOdooAPI('/reya/credit-status', ['line_user_id' => $lineUserId]);

// Test Invoices
testOdooAPI('/reya/invoices', ['line_user_id' => $lineUserId, 'limit' => 10]);

echo "Done!\n";
echo "If API returns empty results, we'll use webhook fallback data.\n";
