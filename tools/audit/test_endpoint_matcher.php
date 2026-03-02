<?php
/**
 * Simple test script for EndpointMatcher class.
 * 
 * This script tests the basic functionality of the EndpointMatcher
 * to ensure it correctly identifies similar endpoints.
 */

require_once __DIR__ . '/bootstrap.php';

use Tools\Audit\Analyzers\EndpointMatcher;

echo "=== EndpointMatcher Test ===\n\n";

// Sample Next.js endpoints
$nextjsEndpoints = [
    [
        'path' => '/api/inbox/conversations',
        'method' => 'GET',
        'authentication' => true,
        'lineAccountFiltering' => true,
        'databaseTables' => ['conversations', 'messages'],
        'requestParams' => ['limit', 'offset'],
    ],
    [
        'path' => '/api/inbox/messages',
        'method' => 'POST',
        'authentication' => true,
        'lineAccountFiltering' => true,
        'databaseTables' => ['messages', 'conversations'],
        'requestParams' => ['conversationId', 'content'],
    ],
    [
        'path' => '/api/inbox/templates',
        'method' => 'GET',
        'authentication' => true,
        'lineAccountFiltering' => true,
        'databaseTables' => ['templates'],
        'requestParams' => [],
    ],
];

// Sample PHP endpoints
$phpEndpoints = [
    [
        'file' => 'inbox.php',
        'action' => 'get_conversations',
        'method' => 'GET',
        'authentication' => true,
        'lineAccountFiltering' => true,
        'databaseTables' => ['conversations', 'messages'],
        'requestParams' => ['page', 'per_page'],
    ],
    [
        'file' => 'inbox.php',
        'action' => 'send_message',
        'method' => 'POST',
        'authentication' => true,
        'lineAccountFiltering' => true,
        'databaseTables' => ['messages'],
        'requestParams' => ['conversation_id', 'message'],
    ],
    [
        'file' => 'inbox-v2.php',
        'action' => 'list_templates',
        'method' => 'GET',
        'authentication' => true,
        'lineAccountFiltering' => true,
        'databaseTables' => ['templates'],
        'requestParams' => ['category'],
    ],
    [
        'file' => 'inbox.php',
        'action' => 'get_stats',
        'method' => 'GET',
        'authentication' => true,
        'lineAccountFiltering' => false,
        'databaseTables' => ['analytics'],
        'requestParams' => [],
    ],
];

// Create matcher and run analysis
$matcher = new EndpointMatcher($nextjsEndpoints, $phpEndpoints);

echo "Running endpoint matching analysis...\n\n";

$result = $matcher->analyze();

if (!$result['success']) {
    echo "ERROR: Analysis failed\n";
    print_r($result['errors']);
    exit(1);
}

// Display statistics
echo "=== Match Statistics ===\n";
foreach ($result['statistics'] as $key => $value) {
    echo sprintf("%-30s: %d\n", ucwords(str_replace('_', ' ', $key)), $value);
}
echo "\n";

// Display matches
echo "=== Endpoint Matches ===\n\n";

foreach ($result['matches'] as $index => $match) {
    echo "Match #" . ($index + 1) . ":\n";
    echo "  Similarity: {$match['similarity_score']} ({$match['similarity_level']})\n";
    
    if ($match['nextjs']) {
        echo "  Next.js: {$match['nextjs']['method']} {$match['nextjs']['path']}\n";
    } else {
        echo "  Next.js: (none)\n";
    }
    
    if ($match['php']) {
        echo "  PHP: {$match['php']['method']} {$match['php']['file']}?action={$match['php']['action']}\n";
    } else {
        echo "  PHP: (none)\n";
    }
    
    if (!empty($match['matching_factors'])) {
        echo "  Factors: " . implode(', ', $match['matching_factors']) . "\n";
    }
    
    if (!empty($match['shared_tables'])) {
        echo "  Shared Tables: " . implode(', ', $match['shared_tables']) . "\n";
    }
    
    if ($match['operation_type']) {
        echo "  Operation: {$match['operation_type']}\n";
    }
    
    echo "\n";
}

// Test validation
echo "=== Validation Test ===\n";
$emptyMatcher = new EndpointMatcher([], []);
$emptyResult = $emptyMatcher->analyze();

if (!$emptyResult['success']) {
    echo "✓ Validation correctly rejects empty endpoints\n";
    echo "  Errors: " . implode(', ', $emptyResult['errors']) . "\n";
} else {
    echo "✗ Validation should reject empty endpoints\n";
}

echo "\n=== Test Complete ===\n";
