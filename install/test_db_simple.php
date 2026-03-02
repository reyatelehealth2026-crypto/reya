<?php
/**
 * Simple Database Test
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    echo "Testing database connection..." . PHP_EOL;

    $db = Database::getInstance();
    $pdo = $db->getConnection();

    echo "✓ Database connected!" . PHP_EOL;
    echo "Database: " . DB_NAME . PHP_EOL;

    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM odoo_line_users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "✓ Query successful!" . PHP_EOL;
    echo "odoo_line_users table has {$result['count']} rows" . PHP_EOL;

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
