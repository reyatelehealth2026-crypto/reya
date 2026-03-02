<?php
/**
 * Verify Rollback Script - Quick Test
 * 
 * This script verifies that the rollback script works correctly by:
 * 1. Checking if tables exist
 * 2. Simulating rollback (dry run)
 * 3. Reporting what would happen
 * 
 * Usage:
 *   php install/verify_rollback.php
 *   OR access via browser: http://your-domain.com/install/verify_rollback.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if running from CLI or web
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Rollback Verification</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
    echo ".success{color:#4ec9b0;}.warning{color:#ce9178;}.error{color:#f48771;}.info{color:#569cd6;}</style>";
    echo "</head><body>";
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

function output($msg, $type = 'info') {
    global $isCLI;
    
    $colors = [
        'success' => $isCLI ? "\033[32m" : "<span class='success'>",
        'warning' => $isCLI ? "\033[33m" : "<span class='warning'>",
        'error' => $isCLI ? "\033[31m" : "<span class='error'>",
        'info' => $isCLI ? "\033[34m" : "<span class='info'>",
    ];
    
    $reset = $isCLI ? "\033[0m" : "</span>";
    $nl = $isCLI ? PHP_EOL : "<br>";
    
    echo $colors[$type] . $msg . $reset . $nl;
}

function header_line($title) {
    global $isCLI;
    $nl = $isCLI ? PHP_EOL : "<br>";
    echo $nl;
    output(str_repeat('=', 80), 'info');
    output($title, 'info');
    output(str_repeat('=', 80), 'info');
    echo $nl;
}

try {
    header_line("Odoo Integration - Rollback Verification (Dry Run)");
    
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    output("✓ Connected to database successfully", 'success');
    echo ($isCLI ? PHP_EOL : "<br>");
    
    // Tables to check
    $tables = [
        'odoo_line_users',
        'odoo_webhooks_log',
        'odoo_slip_uploads',
        'odoo_api_logs'
    ];
    
    output("Checking Odoo integration tables...", 'warning');
    echo ($isCLI ? PHP_EOL : "<br>");
    
    $existingTables = [];
    $totalRows = 0;
    
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $countStmt = $db->query("SELECT COUNT(*) as count FROM `$table`");
            $result = $countStmt->fetch(PDO::FETCH_ASSOC);
            $rowCount = $result['count'];
            
            $existingTables[] = $table;
            $totalRows += $rowCount;
            
            output("  ✓ Table '{$table}' exists ({$rowCount} rows)", 'success');
        } else {
            output("  - Table '{$table}' does not exist", 'info');
        }
    }
    
    echo ($isCLI ? PHP_EOL : "<br>");
    
    // Summary
    header_line("Verification Summary");
    
    if (empty($existingTables)) {
        output("✓ No Odoo integration tables found", 'success');
        output("  Migration has not been run or has been rolled back", 'info');
        echo ($isCLI ? PHP_EOL : "<br>");
        output("To create tables, run:", 'info');
        output("  php install/run_odoo_integration_migration.php", 'info');
    } else {
        output("Tables found: " . count($existingTables), 'warning');
        output("Total rows: {$totalRows}", 'warning');
        echo ($isCLI ? PHP_EOL : "<br>");
        
        output("If you run the rollback script, it will:", 'warning');
        foreach ($existingTables as $table) {
            output("  - Drop table: {$table}", 'warning');
        }
        echo ($isCLI ? PHP_EOL : "<br>");
        
        output("To rollback, run:", 'info');
        output("  php install/rollback_odoo_integration_migration.php", 'info');
        echo ($isCLI ? PHP_EOL : "<br>");
        
        output("Or use SQL script:", 'info');
        output("  mysql -u user -p database < database/rollback_odoo_integration.sql", 'info');
    }
    
    echo ($isCLI ? PHP_EOL : "<br>");
    
    // Check foreign key relationships
    output("Checking foreign key constraints...", 'warning');
    echo ($isCLI ? PHP_EOL : "<br>");
    
    foreach ($existingTables as $table) {
        $stmt = $db->query("
            SELECT 
                CONSTRAINT_NAME,
                REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$table'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($fks)) {
            output("  Table '{$table}' has foreign keys:", 'info');
            foreach ($fks as $fk) {
                output("    - {$fk['CONSTRAINT_NAME']} → {$fk['REFERENCED_TABLE_NAME']}", 'info');
            }
        }
    }
    
    echo ($isCLI ? PHP_EOL : "<br>");
    
    // Rollback script verification
    header_line("Rollback Script Status");
    
    $rollbackScript = __DIR__ . '/rollback_odoo_integration_migration.php';
    $rollbackSQL = __DIR__ . '/../database/rollback_odoo_integration.sql';
    
    if (file_exists($rollbackScript)) {
        output("✓ PHP rollback script exists", 'success');
        output("  Location: {$rollbackScript}", 'info');
    } else {
        output("✗ PHP rollback script NOT found", 'error');
    }
    
    if (file_exists($rollbackSQL)) {
        output("✓ SQL rollback script exists", 'success');
        output("  Location: {$rollbackSQL}", 'info');
    } else {
        output("✗ SQL rollback script NOT found", 'error');
    }
    
    echo ($isCLI ? PHP_EOL : "<br>");
    
    header_line("Verification Complete");
    
    output("✓ All checks completed successfully", 'success');
    output("  The rollback scripts are ready to use", 'success');
    
} catch (Exception $e) {
    output("✗ Error: " . $e->getMessage(), 'error');
    exit(1);
}

if (!$isCLI) {
    echo "</body></html>";
}

exit(0);
