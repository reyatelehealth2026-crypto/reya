<?php
/**
 * Test Odoo Orders API
 * 
 * Tests all actions of the odoo-orders.php API endpoint:
 * - list: Get orders list with filters
 * - detail: Get order detail
 * - tracking: Get order tracking timeline
 * - search: Search orders
 * 
 * @version 1.0.0
 * @created 2026-02-03
 */

require_once __DIR__ . '/config/config.php';

// Test configuration
$apiUrl = 'http://localhost/re-ya/api/odoo-orders.php';
$testLineUserId = 'U1234567890abcdef'; // Replace with actual test user ID

// ANSI color codes for terminal output
$colors = [
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'reset' => "\033[0m"
];

echo "\n";
echo "========================================\n";
echo "  Odoo Orders API Test Suite\n";
echo "========================================\n\n";

// ============================================================================
// Test 1: List Orders (Basic)
// ============================================================================
echo "Test 1: List Orders (Basic)\n";
echo "----------------------------\n";

$response = callAPI($apiUrl, [
    'action' => 'list',
    'line_user_id' => $testLineUserId
]);

if ($response['success']) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Orders list retrieved\n";
    echo "  Orders count: " . count($response['data']['orders'] ?? []) . "\n";
    
    if (isset($response['data']['total'])) {
        echo "  Total orders: " . $response['data']['total'] . "\n";
    }
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - " . $response['error'] . "\n";
}

echo "\n";

// ============================================================================
// Test 2: List Orders with State Filter
// ============================================================================
echo "Test 2: List Orders with State Filter\n";
echo "--------------------------------------\n";

$response = callAPI($apiUrl, [
    'action' => 'list',
    'line_user_id' => $testLineUserId,
    'state' => 'sale' // Filter by confirmed orders
]);

if ($response['success']) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Orders filtered by state\n";
    echo "  Confirmed orders: " . count($response['data']['orders'] ?? []) . "\n";
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - " . $response['error'] . "\n";
}

echo "\n";

// ============================================================================
// Test 3: List Orders with Date Range
// ============================================================================
echo "Test 3: List Orders with Date Range\n";
echo "------------------------------------\n";

$response = callAPI($apiUrl, [
    'action' => 'list',
    'line_user_id' => $testLineUserId,
    'date_from' => '2026-01-01',
    'date_to' => '2026-12-31'
]);

if ($response['success']) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Orders filtered by date range\n";
    echo "  Orders in 2026: " . count($response['data']['orders'] ?? []) . "\n";
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - " . $response['error'] . "\n";
}

echo "\n";

// ============================================================================
// Test 4: List Orders with Pagination
// ============================================================================
echo "Test 4: List Orders with Pagination\n";
echo "------------------------------------\n";

$response = callAPI($apiUrl, [
    'action' => 'list',
    'line_user_id' => $testLineUserId,
    'limit' => 10,
    'offset' => 0
]);

if ($response['success']) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Orders with pagination\n";
    echo "  Page 1 orders: " . count($response['data']['orders'] ?? []) . "\n";
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - " . $response['error'] . "\n";
}

echo "\n";

// ============================================================================
// Test 5: Order Detail
// ============================================================================
echo "Test 5: Order Detail\n";
echo "--------------------\n";

// First get an order ID from the list
$listResponse = callAPI($apiUrl, [
    'action' => 'list',
    'line_user_id' => $testLineUserId,
    'limit' => 1
]);

if ($listResponse['success'] && !empty($listResponse['data']['orders'])) {
    $orderId = $listResponse['data']['orders'][0]['id'];
    
    $response = callAPI($apiUrl, [
        'action' => 'detail',
        'line_user_id' => $testLineUserId,
        'order_id' => $orderId
    ]);
    
    if ($response['success']) {
        echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Order detail retrieved\n";
        echo "  Order ID: " . $orderId . "\n";
        
        if (isset($response['data']['name'])) {
            echo "  Order Name: " . $response['data']['name'] . "\n";
        }
        
        if (isset($response['data']['state'])) {
            echo "  State: " . $response['data']['state'] . "\n";
        }
        
        if (isset($response['data']['amount_total'])) {
            echo "  Total: " . number_format($response['data']['amount_total'], 2) . " บาท\n";
        }
    } else {
        echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - " . $response['error'] . "\n";
    }
} else {
    echo $colors['yellow'] . "⊘ SKIP" . $colors['reset'] . " - No orders available for testing\n";
}

echo "\n";

// ============================================================================
// Test 6: Order Detail - Missing order_id
// ============================================================================
echo "Test 6: Order Detail - Missing order_id\n";
echo "----------------------------------------\n";

$response = callAPI($apiUrl, [
    'action' => 'detail',
    'line_user_id' => $testLineUserId
    // Missing order_id
]);

if (!$response['success'] && strpos($response['error'], 'order_id') !== false) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Correctly validates missing order_id\n";
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - Should require order_id\n";
}

echo "\n";

// ============================================================================
// Test 7: Order Tracking
// ============================================================================
echo "Test 7: Order Tracking\n";
echo "----------------------\n";

// Get an order ID from the list
$listResponse = callAPI($apiUrl, [
    'action' => 'list',
    'line_user_id' => $testLineUserId,
    'limit' => 1
]);

if ($listResponse['success'] && !empty($listResponse['data']['orders'])) {
    $orderId = $listResponse['data']['orders'][0]['id'];
    
    $response = callAPI($apiUrl, [
        'action' => 'tracking',
        'line_user_id' => $testLineUserId,
        'order_id' => $orderId
    ]);
    
    if ($response['success']) {
        echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Order tracking retrieved\n";
        echo "  Order ID: " . $orderId . "\n";
        
        if (isset($response['data']['timeline'])) {
            echo "  Timeline events: " . count($response['data']['timeline']) . "\n";
        }
        
        if (isset($response['data']['current_state'])) {
            echo "  Current state: " . $response['data']['current_state'] . "\n";
        }
    } else {
        echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - " . $response['error'] . "\n";
    }
} else {
    echo $colors['yellow'] . "⊘ SKIP" . $colors['reset'] . " - No orders available for testing\n";
}

echo "\n";

// ============================================================================
// Test 8: Search Orders
// ============================================================================
echo "Test 8: Search Orders\n";
echo "---------------------\n";

$response = callAPI($apiUrl, [
    'action' => 'search',
    'line_user_id' => $testLineUserId,
    'query' => 'SO' // Search for orders starting with SO
]);

if ($response['success']) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Search executed\n";
    echo "  Results: " . count($response['data']['orders'] ?? []) . "\n";
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - " . $response['error'] . "\n";
}

echo "\n";

// ============================================================================
// Test 9: Search with Multiple Filters
// ============================================================================
echo "Test 9: Search with Multiple Filters\n";
echo "-------------------------------------\n";

$response = callAPI($apiUrl, [
    'action' => 'search',
    'line_user_id' => $testLineUserId,
    'query' => 'SO',
    'state' => 'sale',
    'date_from' => '2026-01-01',
    'limit' => 5
]);

if ($response['success']) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Search with filters executed\n";
    echo "  Results: " . count($response['data']['orders'] ?? []) . "\n";
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - " . $response['error'] . "\n";
}

echo "\n";

// ============================================================================
// Test 10: Invalid Action
// ============================================================================
echo "Test 10: Invalid Action\n";
echo "-----------------------\n";

$response = callAPI($apiUrl, [
    'action' => 'invalid_action',
    'line_user_id' => $testLineUserId
]);

if (!$response['success'] && strpos($response['error'], 'Invalid action') !== false) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Correctly rejects invalid action\n";
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - Should reject invalid action\n";
}

echo "\n";

// ============================================================================
// Test 11: Missing line_user_id
// ============================================================================
echo "Test 11: Missing line_user_id\n";
echo "------------------------------\n";

$response = callAPI($apiUrl, [
    'action' => 'list'
    // Missing line_user_id
]);

if (!$response['success'] && strpos($response['error'], 'line_user_id') !== false) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Correctly validates missing line_user_id\n";
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - Should require line_user_id\n";
}

echo "\n";

// ============================================================================
// Test 12: GET Request (Should Fail)
// ============================================================================
echo "Test 12: GET Request (Should Fail)\n";
echo "-----------------------------------\n";

$ch = curl_init($apiUrl . '?action=list&line_user_id=' . $testLineUserId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 405) {
    echo $colors['green'] . "✓ PASS" . $colors['reset'] . " - Correctly rejects GET requests\n";
} else {
    echo $colors['red'] . "✗ FAIL" . $colors['reset'] . " - Should only accept POST requests\n";
}

echo "\n";

echo "========================================\n";
echo "  Test Suite Complete\n";
echo "========================================\n\n";

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Call API endpoint
 * 
 * @param string $url API URL
 * @param array $data Request data
 * @return array Response data
 */
function callAPI($url, $data)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'error' => 'Network error'
        ];
    }

    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Invalid JSON response'
        ];
    }

    return $result;
}
