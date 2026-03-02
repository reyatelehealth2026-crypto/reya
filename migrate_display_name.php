<?php
/**
 * Migration Script: Import display_name from backup to custom_display_name
 * 
 * Usage: php migrate_display_name.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Backup file path - UPDATE THIS IF NEEDED
$backupFilePath = __DIR__ . '/../zrismpsz_cny (4).sql';

if (!file_exists($backupFilePath)) {
    die("Error: Backup file not found at $backupFilePath\n");
}

echo "Starting migration...\n";
echo "Backup file: $backupFilePath\n";

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // 1. Create Temporary Table
    echo "Creating temporary table 'users_import_temp'...\n";
    $conn->exec("DROP TABLE IF EXISTS users_import_temp");
    $sqlOriginal = "CREATE TABLE users_import_temp (
        line_user_id VARCHAR(50) NOT NULL PRIMARY KEY,
        display_name VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sqlOriginal);

    // 2. Process File
    echo "Reading SQL file and importing data to temporary table...\n";
    $handle = fopen($backupFilePath, "r");
    if ($handle) {
        $count = 0;
        $batchSize = 1000;
        $batchData = [];

        $stmt = $conn->prepare("INSERT IGNORE INTO users_import_temp (line_user_id, display_name) VALUES (:line_user_id, :display_name)");

        while (($line = fgets($handle)) !== false) {
            // Look for INSERT INTO `users`
            if (strpos($line, "INSERT INTO `users`") !== false) {
                // Determine insertion format based on known schema from file inspection
                // Schema from file: `id`, `line_account_id`, `line_user_id`, `display_name`, ...
                // Values start after "VALUES "
                
                $valuesPart = substr($line, strpos($line, "VALUES") + 7);
                $valuesPart = trim($valuesPart);
                // Remove trailing semicolon
                if (substr($valuesPart, -1) == ';') {
                    $valuesPart = substr($valuesPart, 0, -1);
                }

                // Split by "),(" to handle multiple rows per line if any (though typically dump uses one heavy line or multiple)
                // The provided dump seems to use one INSERT statement with multiple values or one per line?
                // Step 58 showed: INSERT INTO `users` ... VALUES (1, ...), (2, ...)
                // So we need to parse complex SQL value strings found in one line or multiple
                
                // Simple regex to extract (id, ..., 'line_user_id', 'display_name', ...)
                // The dump format: (id, line_account_id, 'LINE_ID', 'NAME', ...)
                // Indices (0-based):
                // 0: id
                // 1: line_account_id
                // 2: line_user_id
                // 3: display_name
                
                // We'll use a regex to capture each row group (...)
                preg_match_all('/\((.*?)\)/', $valuesPart, $matches);
                
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $rowStr) {
                         // Parse CSV respecting quotes
                         $row = str_getcsv($rowStr, ",", "'");
                         
                         // Fix potential parsing issues if strings contain commas (str_getcsv usually handles if quoted)
                         // But SQL dump strings are quoted with ' not "
                         
                         if (count($row) > 4) {
                             $lineUserId = trim($row[2], "' "); 
                             $displayName = trim($row[3], "' ");
                             
                             // Clean up SQL escaping if present (standard dumps might escape ' as \')
                             $lineUserId = str_replace("\\'", "'", $lineUserId);
                             $displayName = str_replace("\\'", "'", $displayName);

                             $stmt->execute([':line_user_id' => $lineUserId, ':display_name' => $displayName]);
                             $count++;
                             
                             if ($count % 100 == 0) {
                                 echo "Imported $count users to temp table...\r";
                             }
                         }
                    }
                }
            }
        }
        fclose($handle);
        echo "\nFinished importing $count users to temp table.\n";
    } else {
        die("Error opening file.\n");
    }

    // 3. Update Real Table
    echo "Updating 'users' table (setting custom_display_name = display_name from backup)...\n";
    
    // Only update if current custom_display_name is empty/null to avoid overwriting existing manual edits?
    // User request: "importสวน display_name จากข้อมูลเก่ามา ใส่ใน customdisplay_name"
    // Usually implies filling missing data or mapping.
    // WARNING: User said "ใส่ใน customdisplay_name" (put in custom_display_name).
    // I will update ALL matching users. If user wanted only empty ones, they usually say so or we'd ask.
    // However, to be safe, I'll update WHERE it is not null from backup.
    
    $updateSql = "UPDATE " . TABLE_USERS . " u
                  INNER JOIN users_import_temp t ON u.line_user_id = t.line_user_id
                  SET u.custom_display_name = t.display_name
                  WHERE t.display_name IS NOT NULL AND t.display_name != ''";
                  
    $result = $conn->exec($updateSql);
    
    echo "Update completed. Affected rows: $result\n";

    // 4. Cleanup
    $conn->exec("DROP TABLE users_import_temp");
    echo "Temporary table dropped.\n";
    echo "Migration Successful!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
