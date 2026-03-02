<?php
/**
 * Run slip migration — creates odoo tables + adds image_path columns
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

use Modules\Core\Database;

try {
    $db = Database::getInstance()->getConnection();
    echo "Connected to database.\n";

    $sqlFile = __DIR__ . '/../database/migration_slip_complete.sql';
    $sql = file_get_contents($sqlFile);

    // Split by semicolons, filter out empty/comment-only statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function ($s) {
            $s = trim($s);
            if (empty($s)) return false;
            // Remove lines that are only comments
            $lines = array_filter(explode("\n", $s), function ($line) {
                $line = trim($line);
                return !empty($line) && !str_starts_with($line, '--');
            });
            return !empty($lines);
        }
    );

    $ok = 0;
    $err = 0;
    foreach ($statements as $stmt) {
        try {
            $db->exec($stmt);
            // Show first 80 chars
            $preview = substr(preg_replace('/\s+/', ' ', $stmt), 0, 80);
            echo "OK: {$preview}...\n";
            $ok++;
        } catch (Exception $e) {
            echo "ERR: " . $e->getMessage() . "\n";
            $err++;
        }
    }

    echo "\nMigration complete: {$ok} OK, {$err} errors.\n";

} catch (Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
