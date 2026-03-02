<?php

namespace Tools\Audit\Analyzers;

/**
 * MySqlSchemaExtractor
 * 
 * Extracts database schema information from MySQL database.
 * Retrieves table structures, columns, indexes, and foreign keys.
 * 
 * Requirements: 2.1
 */
class MySqlSchemaExtractor
{
    private $db;
    private $tables = [];
    private $errors = [];

    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Extract schema from database
     * 
     * @param array $tableNames Optional array of specific table names to extract
     * @return array Extracted schema structure
     */
    public function extract($tableNames = null)
    {
        try {
            // Get all tables if not specified
            if ($tableNames === null) {
                $tableNames = $this->getAllTableNames();
            }

            // Extract each table
            foreach ($tableNames as $tableName) {
                $this->extractTable($tableName);
            }

            return [
                'success' => count($this->errors) === 0,
                'errors' => $this->errors,
                'tables' => $this->tables
            ];
        } catch (Exception $e) {
            $this->errors[] = "Database extraction failed: " . $e->getMessage();
            return [
                'success' => false,
                'errors' => $this->errors,
                'tables' => []
            ];
        }
    }

    /**
     * Get all table names from database
     * 
     * @return array Table names
     */
    private function getAllTableNames()
    {
        $stmt = $this->db->query("SHOW TABLES");
        $tables = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        return $tables;
    }

    /**
     * Extract single table schema
     * 
     * @param string $tableName Table name
     */
    private function extractTable($tableName)
    {
        try {
            $table = [
                'name' => $tableName,
                'columns' => [],
                'indexes' => [],
                'foreignKeys' => [],
                'primaryKey' => null
            ];

            // Get columns
            $this->extractColumns($tableName, $table);

            // Get indexes
            $this->extractIndexes($tableName, $table);

            // Get foreign keys
            $this->extractForeignKeys($tableName, $table);

            $this->tables[$tableName] = $table;
        } catch (Exception $e) {
            $this->errors[] = "Failed to extract table '{$tableName}': " . $e->getMessage();
        }
    }

    /**
     * Extract column information for a table
     * 
     * @param string $tableName Table name
     * @param array &$table Table array to populate
     */
    private function extractColumns($tableName, &$table)
    {
        $stmt = $this->db->query("SHOW FULL COLUMNS FROM `{$tableName}`");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $column = [
                'name' => $row['Field'],
                'type' => $this->parseColumnType($row['Type']),
                'fullType' => $row['Type'],
                'nullable' => $row['Null'] === 'YES',
                'default' => $row['Default'],
                'extra' => $row['Extra'],
                'autoIncrement' => strpos($row['Extra'], 'auto_increment') !== false,
                'primaryKey' => $row['Key'] === 'PRI',
                'unique' => $row['Key'] === 'UNI',
                'comment' => $row['Comment'] ?? ''
            ];

            // Parse length/precision from type
            if (preg_match('/\(([^)]+)\)/', $row['Type'], $match)) {
                $column['length'] = $match[1];
            }

            // Parse unsigned
            $column['unsigned'] = strpos($row['Type'], 'unsigned') !== false;

            $table['columns'][$row['Field']] = $column;

            // Track primary key
            if ($column['primaryKey']) {
                if ($table['primaryKey'] === null) {
                    $table['primaryKey'] = [$row['Field']];
                } else {
                    $table['primaryKey'][] = $row['Field'];
                }
            }
        }
    }

    /**
     * Parse column type from full type string
     * 
     * @param string $fullType Full type string (e.g., "varchar(255)", "int(11) unsigned")
     * @return string Base type (e.g., "varchar", "int")
     */
    private function parseColumnType($fullType)
    {
        // Extract base type before parentheses or space
        if (preg_match('/^(\w+)/', $fullType, $match)) {
            return strtolower($match[1]);
        }
        return strtolower($fullType);
    }

    /**
     * Extract index information for a table
     * 
     * @param string $tableName Table name
     * @param array &$table Table array to populate
     */
    private function extractIndexes($tableName, &$table)
    {
        $stmt = $this->db->query("SHOW INDEX FROM `{$tableName}`");
        $indexes = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $indexName = $row['Key_name'];
            
            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'name' => $indexName,
                    'columns' => [],
                    'unique' => $row['Non_unique'] == 0,
                    'primary' => $indexName === 'PRIMARY',
                    'type' => $row['Index_type']
                ];
            }
            
            // Add column to index (in sequence order)
            $indexes[$indexName]['columns'][$row['Seq_in_index']] = $row['Column_name'];
        }
        
        // Sort columns by sequence and convert to simple array
        foreach ($indexes as &$index) {
            ksort($index['columns']);
            $index['columns'] = array_values($index['columns']);
        }
        
        $table['indexes'] = array_values($indexes);
    }

    /**
     * Extract foreign key information for a table
     * 
     * @param string $tableName Table name
     * @param array &$table Table array to populate
     */
    private function extractForeignKeys($tableName, &$table)
    {
        $sql = "
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                UPDATE_RULE,
                DELETE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tableName
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tableName' => $tableName]);
        
        $foreignKeys = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $constraintName = $row['CONSTRAINT_NAME'];
            
            if (!isset($foreignKeys[$constraintName])) {
                $foreignKeys[$constraintName] = [
                    'name' => $constraintName,
                    'columns' => [],
                    'referencedTable' => $row['REFERENCED_TABLE_NAME'],
                    'referencedColumns' => [],
                    'onUpdate' => $row['UPDATE_RULE'],
                    'onDelete' => $row['DELETE_RULE']
                ];
            }
            
            $foreignKeys[$constraintName]['columns'][] = $row['COLUMN_NAME'];
            $foreignKeys[$constraintName]['referencedColumns'][] = $row['REFERENCED_COLUMN_NAME'];
        }
        
        $table['foreignKeys'] = array_values($foreignKeys);
    }

    /**
     * Get extracted tables
     * 
     * @return array Tables array
     */
    public function getTables()
    {
        return $this->tables;
    }

    /**
     * Get extraction errors
     * 
     * @return array Errors array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get table by name
     * 
     * @param string $tableName Table name
     * @return array|null Table data or null if not found
     */
    public function getTable($tableName)
    {
        return $this->tables[$tableName] ?? null;
    }

    /**
     * Get column by name
     * 
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return array|null Column data or null if not found
     */
    public function getColumn($tableName, $columnName)
    {
        $table = $this->getTable($tableName);
        if (!$table || !isset($table['columns'][$columnName])) {
            return null;
        }
        return $table['columns'][$columnName];
    }

    /**
     * Check if table exists
     * 
     * @param string $tableName Table name
     * @return bool True if table exists
     */
    public function tableExists($tableName)
    {
        return isset($this->tables[$tableName]);
    }

    /**
     * Check if column exists in table
     * 
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return bool True if column exists
     */
    public function columnExists($tableName, $columnName)
    {
        $table = $this->getTable($tableName);
        return $table && isset($table['columns'][$columnName]);
    }

    /**
     * Get primary key columns for a table
     * 
     * @param string $tableName Table name
     * @return array|null Primary key columns or null if not found
     */
    public function getPrimaryKey($tableName)
    {
        $table = $this->getTable($tableName);
        return $table ? $table['primaryKey'] : null;
    }

    /**
     * Get indexes for a table
     * 
     * @param string $tableName Table name
     * @return array Indexes array
     */
    public function getIndexes($tableName)
    {
        $table = $this->getTable($tableName);
        return $table ? $table['indexes'] : [];
    }

    /**
     * Get foreign keys for a table
     * 
     * @param string $tableName Table name
     * @return array Foreign keys array
     */
    public function getForeignKeys($tableName)
    {
        $table = $this->getTable($tableName);
        return $table ? $table['foreignKeys'] : [];
    }
}
