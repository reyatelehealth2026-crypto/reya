<?php
/**
 * Standalone test for inbox-groups.php
 * This bypasses authentication for testing
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Test 1: Check if line_groups table exists
    $result = ['tests' => []];
    
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'line_groups'");
        $tableExists = $stmt->rowCount() > 0;
        $result['tests'][] = [
            'name' => 'Table Exists',
            'success' => $tableExists,
            'message' => $tableExists ? 'line_groups table exists' : 'line_groups table not found'
        ];
    } catch (Exception $e) {
        $result['tests'][] = [
            'name' => 'Table Exists',
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // Test 2: Count groups
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM line_groups");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['tests'][] = [
            'name' => 'Count Groups',
            'success' => true,
            'count' => (int)$count['count']
        ];
    } catch (Exception $e) {
        $result['tests'][] = [
            'name' => 'Count Groups',
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // Test 3: Get sample groups
    try {
        $stmt = $db->query("SELECT id, group_id, group_name, is_active FROM line_groups LIMIT 3");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result['tests'][] = [
            'name' => 'Sample Groups',
            'success' => true,
            'groups' => $groups
        ];
    } catch (Exception $e) {
        $result['tests'][] = [
            'name' => 'Sample Groups',
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // Test 4: Test the actual API endpoint
    try {
        $_GET['action'] = 'get_stats';
        $_GET['line_account_id'] = '1';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_ADMIN_ID'] = '1';
        $_SERVER['HTTP_X_LINE_ACCOUNT_ID'] = '1';
        
        ob_start();
        include __DIR__ . '/inbox-groups.php';
        $apiResponse = ob_get_clean();
        
        $result['tests'][] = [
            'name' => 'API Endpoint Test',
            'success' => true,
            'response' => json_decode($apiResponse, true)
        ];
    } catch (Exception $e) {
        ob_end_clean();
        $result['tests'][] = [
            'name' => 'API Endpoint Test',
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    $result['success'] = true;
    $result['message'] = 'All tests completed';
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
