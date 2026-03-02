<?php
/**
 * Odoo Integration - Rollback Migration Script
 * 
 * This script safely removes all Odoo integration tables from the database.
 * Tables are dropped in the correct order to respect foreign key constraints.
 * 
 * Usage:
 *   php install/rollback_odoo_integration_migration.php
 * 
 * WARNING: This will permanently delete all Odoo integration data!
 * 
 * @package Re-Ya
 * @subpackage Odoo Integration
 * @version 1.0.0
 */

// Include database configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

// ANSI color codes for terminal output
define('COLOR_RED', "\033[31m");
define('COLOR_GREEN', "\033[32m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

/**
 * Print colored message to console
 */
function printMessage($message, $color = COLOR_RESET) {
    echo $color . $message . COLOR_RESET . PHP_EOL;
}

/**
 * Print section header
 */
function printHeader($title) {
    echo PHP_EOL;
    printMessage(str_repeat('=', 80), COLOR_BLUE);
    printMessage($title, COLOR_BLUE);
    printMessage(str_repeat('=', 80), COLOR_BLUE);
    echo PHP_EOL;
}

/**
 * Check if a table exists
 */
function tableExists($db, $tableName) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get row count from a table
 */
function getRowCount($db, $tableName) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM `{$tableName}`");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Drop a table if it exists
 */
function dropTable($db, $tableName) {
    try {
        $db->exec("DROP TABLE IF EXISTS `{$tableName}`");
        return true;
    } catch (Exception $e) {
        printMessage("  ✗ Error: " . $e->getMessage(), COLOR_RED);
        return false;
    }
}

// ============================================================================
// Main Rollback Process
// ============================================================================

printHeader("Odoo Integration - Rollback Migration");

try {
    // Get database connection
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    printMessage("Connected to database successfully", COLOR_GREEN);
    echo PHP_EOL;
    
    // ========================================================================
    // Step 1: Check which tables exist
    // ========================================================================
    
    printMessage("Step 1: Checking existing tables...", COLOR_YELLOW);
    echo PHP_EOL;
    
    $tables = [
        'odoo_api_logs',
        'odoo_slip_uploads',
        'odoo_webhooks_log',
        'odoo_line_users'
    ];
    
    $existingTables = [];
    $totalRows = 0;
    
    foreach ($tables as $table) {
        if (tableExists($db, $table)) {
            $rowCount = getRowCount($db, $table);
            $existingTables[] = $table;
            $totalRows += $rowCount;
            printMessage("  ✓ Table '{$table}' exists ({$rowCount} rows)", COLOR_GREEN);
        } else {
            printMessage("  - Table '{$table}' does not exist", COLOR_BLUE);
        }
    }
    
    echo PHP_EOL;
    
    // ========================================================================
    // Step 2: Confirm rollback
    // ========================================================================
    
    if (empty($existingTables)) {
        printMessage("No Odoo integration tables found. Nothing to rollback.", COLOR_BLUE);
        exit(0);
    }
    
    printMessage("WARNING: This will permanently delete the following tables:", COLOR_RED);
    foreach ($existingTables as $table) {
        printMessage("  - {$table}", COLOR_RED);
    }
    printMessage("Total rows to be deleted: {$totalRows}", COLOR_RED);
    echo PHP_EOL;
    
    printMessage("Are you sure you want to continue? (yes/no): ", COLOR_YELLOW);
    $handle = fopen("php://stdin", "r");
    $confirmation = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($confirmation) !== 'yes') {
        printMessage("Rollback cancelled by user.", COLOR_BLUE);
        exit(0);
    }
    
    echo PHP_EOL;
    
    // ========================================================================
    // Step 3: Disable foreign key checks
    // ========================================================================
    
    printMessage("Step 2: Disabling foreign key checks...", COLOR_YELLOW);
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    printMessage("  ✓ Foreign key checks disabled", COLOR_GREEN);
    echo PHP_EOL;
    
    // ========================================================================
    // Step 4: Drop tables in correct order
    // ========================================================================
    
    printMessage("Step 3: Dropping tables...", COLOR_YELLOW);
    echo PHP_EOL;
    
    // Drop tables in reverse order of creation to respect dependencies
    // Even though we disabled FK checks, it's good practice
    $dropOrder = [
        'odoo_api_logs',        // No dependencies
        'odoo_slip_uploads',    // References line_accounts
        'odoo_webhooks_log',    // References line_accounts
        'odoo_line_users'       // References line_accounts
    ];
    
    $droppedCount = 0;
    foreach ($dropOrder as $table) {
        if (in_array($table, $existingTables)) {
            printMessage("  Dropping table '{$table}'...", COLOR_BLUE);
            if (dropTable($db, $table)) {
                printMessage("  ✓ Table '{$table}' dropped successfully", COLOR_GREEN);
                $droppedCount++;
            } else {
                printMessage("  ✗ Failed to drop table '{$table}'", COLOR_RED);
            }
        }
    }
    
    echo PHP_EOL;
    
    // ========================================================================
    // Step 5: Re-enable foreign key checks
    // ========================================================================
    
    printMessage("Step 4: Re-enabling foreign key checks...", COLOR_YELLOW);
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    printMessage("  ✓ Foreign key checks re-enabled", COLOR_GREEN);
    echo PHP_EOL;
    
    // ========================================================================
    // Step 6: Verify rollback
    // ========================================================================
    
    printMessage("Step 5: Verifying rollback...", COLOR_YELLOW);
    echo PHP_EOL;
    
    $remainingTables = [];
    foreach ($tables as $table) {
        if (tableExists($db, $table)) {
            $remainingTables[] = $table;
            printMessage("  ✗ Table '{$table}' still exists!", COLOR_RED);
        } else {
            printMessage("  ✓ Table '{$table}' removed", COLOR_GREEN);
        }
    }
    
    echo PHP_EOL;
    
    // ========================================================================
    // Final Summary
    // ========================================================================
    
    printHeader("Rollback Summary");
    
    if (empty($remainingTables)) {
        printMessage("✓ Rollback completed successfully!", COLOR_GREEN);
        printMessage("  - Tables dropped: {$droppedCount}", COLOR_GREEN);
        printMessage("  - Rows deleted: {$totalRows}", COLOR_GREEN);
        echo PHP_EOL;
        printMessage("All Odoo integration tables have been removed.", COLOR_GREEN);
        printMessage("You can re-run the migration anytime using:", COLOR_BLUE);
        printMessage("  php install/run_odoo_integration_migration.php", COLOR_BLUE);
    } else {
        printMessage("✗ Rollback completed with errors!", COLOR_RED);
        printMessage("  - Tables dropped: {$droppedCount}", COLOR_YELLOW);
        printMessage("  - Tables remaining: " . count($remainingTables), COLOR_RED);
        echo PHP_EOL;
        printMessage("The following tables could not be removed:", COLOR_RED);
        foreach ($remainingTables as $table) {
            printMessage("  - {$table}", COLOR_RED);
        }
        echo PHP_EOL;
        printMessage("Please check the error messages above and try again.", COLOR_YELLOW);
        exit(1);
    }
    
} catch (Exception $e) {
    printMessage("✗ Fatal Error: " . $e->getMessage(), COLOR_RED);
    printMessage("Stack trace:", COLOR_RED);
    printMessage($e->getTraceAsString(), COLOR_RED);
    exit(1);
}

echo PHP_EOL;
printMessage(str_repeat('=', 80), COLOR_BLUE);
echo PHP_EOL;

exit(0);
