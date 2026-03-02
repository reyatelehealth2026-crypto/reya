<?php
/**
 * Simple test to verify JSON response works
 */

// Prevent any output before JSON
ob_start();

// Set error handling
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error: ' . $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

// Test database connection
try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    
    $db = Database::getInstance()->getConnection();
    
    // Simple query
    $stmt = $db->query("SELECT COUNT(*) as count FROM line_groups");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Database connection OK',
        'data' => [
            'groups_count' => (int)$result['count'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
