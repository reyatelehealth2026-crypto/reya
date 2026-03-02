<?php
/**
 * Migration: Fix UNIQUE constraint for multi-platform support
 *
 * Problem: The existing UNIQUE KEY `unique_line_user` on (line_account_id, line_user_id)
 * causes issues when multiple Facebook users are created because:
 * - Both have line_account_id = NULL
 * - MySQL treats multiple (NULL, value) as duplicates in UNIQUE constraints
 *
 * Solution: Add a composite UNIQUE constraint on (platform, platform_user_id, facebook_account_id)
 * for Facebook users specifically.
 */

header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== Migration: Fix Facebook UNIQUE Constraint ===\n\n";

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = null;

if (class_exists('Database')) {
    try {
        $db = Database::getInstance()->getConnection();
        echo "[OK] Connected via Database class\n";
    } catch (Exception $e) {
        echo "[WARN] Database class failed: " . $e->getMessage() . "\n";
    }
}

if (!$db) {
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $name = defined('DB_NAME') ? DB_NAME : '';
    $user = defined('DB_USER') ? DB_USER : '';
    $pass = defined('DB_PASS') ? DB_PASS : '';

    if (!$name) {
        echo "[FAIL] Cannot connect: DB_HOST/DB_NAME/DB_USER/DB_PASS not defined.\n";
        exit(1);
    }

    try {
        $db = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "[OK] Connected via direct PDO ({$user}@{$host}/{$name})\n";
    } catch (PDOException $e) {
        echo "[FAIL] PDO connection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n";

function runStep(PDO $db, string $label, string $sql): void
{
    try {
        $db->exec($sql);
        echo "[OK]   {$label}\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (
            stripos($msg, 'Duplicate column name') !== false ||
            stripos($msg, 'already exists') !== false ||
            stripos($msg, "Duplicate key name") !== false ||
            stripos($msg, "Can't DROP") !== false
        ) {
            echo "[SKIP] {$label} (already applied)\n";
        } else {
            echo "[FAIL] {$label}: {$msg}\n";
        }
    }
}

// Add UNIQUE constraint for Facebook users
echo "--- Add Facebook UNIQUE constraint ---\n";

runStep($db, 'Add UNIQUE constraint for Facebook users', "
    ALTER TABLE users
    ADD UNIQUE KEY unique_facebook_user (platform, platform_user_id, facebook_account_id)
");

// Add UNIQUE constraint for TikTok users (future-proofing)
runStep($db, 'Add UNIQUE constraint for TikTok users', "
    ALTER TABLE users
    ADD UNIQUE KEY unique_tiktok_user (platform, platform_user_id, tiktok_account_id)
");

echo "\n=== Migration Complete ===\n";
echo "Now Facebook users with different PSIDs can be created without conflicts.\n";
