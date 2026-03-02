<?php

namespace Tools\Audit\Analyzers;

/**
 * SchemaComparator
 * 
 * Compares Prisma schema models with MySQL database tables.
 * Identifies compatibility issues, type mismatches, and missing elements.
 * 
 * Requirements: 2.1, 2.4, 2.6
 */
class SchemaComparator
{
    private $prismaParser;
    private $mysqlExtractor;
    private $issues = [];
    private $compatibilityReport = [];

    /**
     * Prisma to MySQL type mapping
     */
    private $typeMapping = [
        'String' => ['varchar', 'text', 'char', 'longtext', 'mediumtext', 'tinytext'],
        'Int' => ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'],
        'BigInt' => ['bigint'],
        'Float' => ['float', 'double', 'decimal'],
        'Decimal' => ['decimal', 'numeric'],
        'Boolean' => ['tinyint', 'boolean', 'bool'],
        'DateTime' => ['datetime', 'timestamp'],
        'Date' => ['date'],
        'Time' => ['time'],
        'Json' => ['json', 'text', 'longtext'],
        'Bytes' => ['blob', 'binary', 'varbinary', 'longblob', 'mediumblob', 'tinyblob']
    ];

    /**
     * Constructor
     * 
     * @param PrismaSchemaParser $prismaParser Prisma schema parser
     * @param MySqlSchemaExtractor $mysqlExtractor MySQL schema extractor
     */
    public function __construct($prismaParser, $mysqlExtractor)
    {
        $this->prismaParser = $prismaParser;
        $this->mysqlExtractor = $mysqlExtractor;
    }

    /**
     * Compare schemas and generate compatibility report
     * 
     * @return array Compatibility report
     */
    public function compare()
    {
        $models = $this->prismaParser->getModels();
        
        foreach ($models as $modelName => $model) {
            $this->compareModel($modelName, $model);
        }

        return [
            'success' => $this->getSeverityCount('critical') === 0,
            'issues' => $this->issues,
            'compatibilityReport' => $this->compatibilityReport,
            'summary' => $this->generateSummary()
        ];
    }

    /**
     * Compare a single Prisma model with its MySQL table
     * 
     * @param string $modelName Model name
     * @param array $model Model data
     */
    private function compareModel($modelName, $model)
    {
        $tableName = $model['tableName'];
        $mysqlTable = $this->mysqlExtractor->getTable($tableName);

        $modelReport = [
            'modelName' => $modelName,
            'tableName' => $tableName,
            'exists' => $mysqlTable !== null,
            'compatible' => true,
            'issues' => []
        ];

        if (!$mysqlTable) {
            $this->addIssue(
                'missing_table',
                "Table '{$tableName}' for model '{$modelName}' does not exist in MySQL",
                'critical',
                $modelName,
                null,
                "Create table '{$tableName}' in MySQL database"
            );
            $modelReport['compatible'] = false;
            $this->compatibilityReport[$modelName] = $modelReport;
            return;
        }

        // Compare columns
        $this->compareColumns($modelName, $model, $mysqlTable, $modelReport);

        // Compare indexes
        $this->compareIndexes($modelName, $model, $mysqlTable, $modelReport);

        // Compare foreign keys
        $this->compareForeignKeys($modelName, $model, $mysqlTable, $modelReport);

        // Check for extra columns in MySQL not in Prisma
        $this->checkExtraColumns($modelName, $model, $mysqlTable, $modelReport);

        $this->compatibilityReport[$modelName] = $modelReport;
    }

    /**
     * Compare columns between Prisma model and MySQL table
     * 
     * @param string $modelName Model name
     * @param array $model Model data
     * @param array $mysqlTable MySQL table data
     * @param array &$modelReport Model report to populate
     */
    private function compareColumns($modelName, $model, $mysqlTable, &$modelReport)
    {
        foreach ($model['fields'] as $fieldName => $field) {
            // Skip relation fields (they don't have direct columns)
            if ($field['relation'] !== null && empty($field['relation']['fields'])) {
                continue;
            }

            $columnName = $field['columnName'];
            $mysqlColumn = $mysqlTable['columns'][$columnName] ?? null;

            if (!$mysqlColumn) {
                $issue = $this->addIssue(
                    'missing_column',
                    "Column '{$columnName}' (field '{$fieldName}') does not exist in table '{$mysqlTable['name']}'",
                    'critical',
                    $modelName,
                    $fieldName,
                    "Add column '{$columnName}' to table '{$mysqlTable['name']}'"
                );
                $modelReport['issues'][] = $issue;
                $modelReport['compatible'] = false;
                continue;
            }

            // Compare data types
            $this->compareDataType($modelName, $fieldName, $field, $mysqlColumn, $modelReport);

            // Compare nullable constraint
            $this->compareNullable($modelName, $fieldName, $field, $mysqlColumn, $modelReport);

            // Compare default values
            $this->compareDefault($modelName, $fieldName, $field, $mysqlColumn, $modelReport);

            // Compare auto increment
            $this->compareAutoIncrement($modelName, $fieldName, $field, $mysqlColumn, $modelReport);
        }
    }

    /**
     * Compare data types between Prisma field and MySQL column
     * 
     * @param string $modelName Model name
     * @param string $fieldName Field name
     * @param array $field Field data
     * @param array $mysqlColumn MySQL column data
     * @param array &$modelReport Model report to populate
     */
    private function compareDataType($modelName, $fieldName, $field, $mysqlColumn, &$modelReport)
    {
        $prismaType = $field['type'];
        $mysqlType = $mysqlColumn['type'];

        // Check if types are compatible
        if (!$this->areTypesCompatible($prismaType, $mysqlType)) {
            $severity = $this->getTypeMismatchSeverity($prismaType, $mysqlType);
            $issue = $this->addIssue(
                'type_mismatch',
                "Type mismatch for '{$fieldName}': Prisma type '{$prismaType}' vs MySQL type '{$mysqlType}'",
                $severity,
                $modelName,
                $fieldName,
                "Change MySQL column type to be compatible with Prisma type '{$prismaType}'"
            );
            $modelReport['issues'][] = $issue;
            if ($severity === 'critical') {
                $modelReport['compatible'] = false;
            }
        }
    }

    /**
     * Check if Prisma type is compatible with MySQL type
     * 
     * @param string $prismaType Prisma type
     * @param string $mysqlType MySQL type
     * @return bool True if compatible
     */
    private function areTypesCompatible($prismaType, $mysqlType)
    {
        // Direct match
        if (strtolower($prismaType) === strtolower($mysqlType)) {
            return true;
        }

        // Check mapping
        if (isset($this->typeMapping[$prismaType])) {
            return in_array($mysqlType, $this->typeMapping[$prismaType]);
        }

        // Unknown Prisma type - might be enum or custom type
        return false;
    }

    /**
     * Determine severity of type mismatch
     * 
     * @param string $prismaType Prisma type
     * @param string $mysqlType MySQL type
     * @return string Severity level
     */
    private function getTypeMismatchSeverity($prismaType, $mysqlType)
    {
        // Critical mismatches that could cause data corruption
        $criticalMismatches = [
            ['String', 'int'],
            ['Int', 'varchar'],
            ['DateTime', 'int'],
            ['Boolean', 'varchar']
        ];

        foreach ($criticalMismatches as $mismatch) {
            if ($prismaType === $mismatch[0] && $mysqlType === $mismatch[1]) {
                return 'critical';
            }
        }

        // High severity for potential data loss
        if ($prismaType === 'BigInt' && in_array($mysqlType, ['int', 'smallint', 'tinyint'])) {
            return 'high';
        }

        return 'medium';
    }

    /**
     * Compare nullable constraints
     * 
     * @param string $modelName Model name
     * @param string $fieldName Field name
     * @param array $field Field data
     * @param array $mysqlColumn MySQL column data
     * @param array &$modelReport Model report to populate
     */
    private function compareNullable($modelName, $fieldName, $field, $mysqlColumn, &$modelReport)
    {
        if ($field['nullable'] !== $mysqlColumn['nullable']) {
            $severity = 'medium';
            $description = "Nullable mismatch for '{$fieldName}': Prisma " . 
                          ($field['nullable'] ? 'allows' : 'requires') . " NULL, MySQL " .
                          ($mysqlColumn['nullable'] ? 'allows' : 'requires') . " NOT NULL";
            
            // More severe if Prisma requires NOT NULL but MySQL allows NULL
            if (!$field['nullable'] && $mysqlColumn['nullable']) {
                $severity = 'high';
            }

            $issue = $this->addIssue(
                'constraint_mismatch',
                $description,
                $severity,
                $modelName,
                $fieldName,
                "Update MySQL column '{$mysqlColumn['name']}' nullable constraint to match Prisma"
            );
            $modelReport['issues'][] = $issue;
        }
    }

    /**
     * Compare default values
     * 
     * @param string $modelName Model name
     * @param string $fieldName Field name
     * @param array $field Field data
     * @param array $mysqlColumn MySQL column data
     * @param array &$modelReport Model report to populate
     */
    private function compareDefault($modelName, $fieldName, $field, $mysqlColumn, &$modelReport)
    {
        $prismaDefault = $field['default'];
        $mysqlDefault = $mysqlColumn['default'];

        // Normalize defaults for comparison
        $prismaDefault = $this->normalizeDefault($prismaDefault);
        $mysqlDefault = $this->normalizeDefault($mysqlDefault);

        if ($prismaDefault !== $mysqlDefault) {
            // Only report if it's a significant mismatch
            if ($prismaDefault !== null || $mysqlDefault !== null) {
                $issue = $this->addIssue(
                    'default_mismatch',
                    "Default value mismatch for '{$fieldName}': Prisma default '{$prismaDefault}' vs MySQL default '{$mysqlDefault}'",
                    'low',
                    $modelName,
                    $fieldName,
                    "Consider aligning default values between Prisma and MySQL"
                );
                $modelReport['issues'][] = $issue;
            }
        }
    }

    /**
     * Normalize default value for comparison
     * 
     * @param mixed $default Default value
     * @return string|null Normalized default
     */
    private function normalizeDefault($default)
    {
        if ($default === null) {
            return null;
        }

        $default = trim($default);
        
        // Remove quotes
        $default = trim($default, '"\'');
        
        // Normalize common functions
        $default = str_replace(['now()', 'CURRENT_TIMESTAMP'], 'now', strtolower($default));
        
        return $default;
    }

    /**
     * Compare auto increment settings
     * 
     * @param string $modelName Model name
     * @param string $fieldName Field name
     * @param array $field Field data
     * @param array $mysqlColumn MySQL column data
     * @param array &$modelReport Model report to populate
     */
    private function compareAutoIncrement($modelName, $fieldName, $field, $mysqlColumn, &$modelReport)
    {
        if ($field['autoIncrement'] !== $mysqlColumn['autoIncrement']) {
            $severity = $field['autoIncrement'] ? 'high' : 'medium';
            $issue = $this->addIssue(
                'constraint_mismatch',
                "Auto increment mismatch for '{$fieldName}': Prisma " . 
                ($field['autoIncrement'] ? 'has' : 'does not have') . " autoincrement, MySQL " .
                ($mysqlColumn['autoIncrement'] ? 'has' : 'does not have') . " auto_increment",
                $severity,
                $modelName,
                $fieldName,
                "Update MySQL column '{$mysqlColumn['name']}' auto_increment setting"
            );
            $modelReport['issues'][] = $issue;
        }
    }

    /**
     * Compare indexes between Prisma model and MySQL table
     * 
     * @param string $modelName Model name
     * @param array $model Model data
     * @param array $mysqlTable MySQL table data
     * @param array &$modelReport Model report to populate
     */
    private function compareIndexes($modelName, $model, $mysqlTable, &$modelReport)
    {
        // Build index map for comparison
        $prismaIndexes = $this->buildIndexMap($model);
        $mysqlIndexes = $this->buildMysqlIndexMap($mysqlTable);

        // Check for missing indexes in MySQL
        foreach ($prismaIndexes as $indexKey => $prismaIndex) {
            if (!isset($mysqlIndexes[$indexKey])) {
                $issue = $this->addIssue(
                    'missing_index',
                    "Index on columns [" . implode(', ', $prismaIndex['columns']) . "] is missing in MySQL table '{$mysqlTable['name']}'",
                    'medium',
                    $modelName,
                    null,
                    "Add index on columns [" . implode(', ', $prismaIndex['columns']) . "] to table '{$mysqlTable['name']}'"
                );
                $modelReport['issues'][] = $issue;
            }
        }
    }

    /**
     * Build index map from Prisma model
     * 
     * @param array $model Model data
     * @return array Index map
     */
    private function buildIndexMap($model)
    {
        $indexMap = [];

        foreach ($model['indexes'] as $index) {
            // Convert field names to column names
            $columns = [];
            foreach ($index['fields'] as $fieldName) {
                if (isset($model['fields'][$fieldName])) {
                    $columns[] = $model['fields'][$fieldName]['columnName'];
                }
            }
            
            if (!empty($columns)) {
                $key = implode('_', $columns);
                $indexMap[$key] = [
                    'columns' => $columns,
                    'unique' => $index['unique'] ?? false
                ];
            }
        }

        // Add unique constraints as indexes
        foreach ($model['uniqueConstraints'] as $unique) {
            $columns = [];
            foreach ($unique['fields'] as $fieldName) {
                if (isset($model['fields'][$fieldName])) {
                    $columns[] = $model['fields'][$fieldName]['columnName'];
                }
            }
            
            if (!empty($columns)) {
                $key = implode('_', $columns);
                $indexMap[$key] = [
                    'columns' => $columns,
                    'unique' => true
                ];
            }
        }

        return $indexMap;
    }

    /**
     * Build index map from MySQL table
     * 
     * @param array $mysqlTable MySQL table data
     * @return array Index map
     */
    private function buildMysqlIndexMap($mysqlTable)
    {
        $indexMap = [];

        foreach ($mysqlTable['indexes'] as $index) {
            // Skip primary key (handled separately)
            if ($index['primary'] ?? false) {
                continue;
            }

            $key = implode('_', $index['columns']);
            $indexMap[$key] = [
                'columns' => $index['columns'],
                'unique' => $index['unique']
            ];
        }

        return $indexMap;
    }

    /**
     * Compare foreign keys between Prisma model and MySQL table
     * 
     * @param string $modelName Model name
     * @param array $model Model data
     * @param array $mysqlTable MySQL table data
     * @param array &$modelReport Model report to populate
     */
    private function compareForeignKeys($modelName, $model, $mysqlTable, &$modelReport)
    {
        // Extract foreign keys from Prisma relations
        $prismaForeignKeys = $this->extractPrismaForeignKeys($model);
        $mysqlForeignKeys = $mysqlTable['foreignKeys'];

        // Build FK map for comparison
        $mysqlFkMap = [];
        foreach ($mysqlForeignKeys as $fk) {
            $key = implode('_', $fk['columns']) . '_' . $fk['referencedTable'];
            $mysqlFkMap[$key] = $fk;
        }

        // Check for missing foreign keys in MySQL
        foreach ($prismaForeignKeys as $prismaFk) {
            $key = implode('_', $prismaFk['columns']) . '_' . $prismaFk['referencedTable'];
            
            if (!isset($mysqlFkMap[$key])) {
                $issue = $this->addIssue(
                    'missing_foreign_key',
                    "Foreign key from [" . implode(', ', $prismaFk['columns']) . "] to '{$prismaFk['referencedTable']}' is missing in MySQL",
                    'medium',
                    $modelName,
                    null,
                    "Add foreign key constraint to table '{$mysqlTable['name']}'"
                );
                $modelReport['issues'][] = $issue;
            } else {
                // Compare FK actions (onDelete, onUpdate)
                $mysqlFk = $mysqlFkMap[$key];
                $this->compareForeignKeyActions($modelName, $prismaFk, $mysqlFk, $modelReport);
            }
        }
    }

    /**
     * Extract foreign keys from Prisma model relations
     * 
     * @param array $model Model data
     * @return array Foreign keys
     */
    private function extractPrismaForeignKeys($model)
    {
        $foreignKeys = [];

        foreach ($model['fields'] as $field) {
            if ($field['relation'] !== null && !empty($field['relation']['fields'])) {
                // Convert field names to column names
                $columns = [];
                foreach ($field['relation']['fields'] as $fieldName) {
                    if (isset($model['fields'][$fieldName])) {
                        $columns[] = $model['fields'][$fieldName]['columnName'];
                    }
                }

                // Get referenced table name (need to look up the related model)
                $relatedModelName = $field['type'];
                $referencedTable = $this->prismaParser->getTableName($relatedModelName);

                if (!empty($columns) && $referencedTable) {
                    $foreignKeys[] = [
                        'columns' => $columns,
                        'referencedTable' => $referencedTable,
                        'referencedColumns' => $field['relation']['references'],
                        'onDelete' => $field['relation']['onDelete'],
                        'onUpdate' => $field['relation']['onUpdate']
                    ];
                }
            }
        }

        return $foreignKeys;
    }

    /**
     * Compare foreign key actions (onDelete, onUpdate)
     * 
     * @param string $modelName Model name
     * @param array $prismaFk Prisma foreign key
     * @param array $mysqlFk MySQL foreign key
     * @param array &$modelReport Model report to populate
     */
    private function compareForeignKeyActions($modelName, $prismaFk, $mysqlFk, &$modelReport)
    {
        // Normalize actions for comparison
        $prismaOnDelete = strtoupper($prismaFk['onDelete'] ?? 'NO ACTION');
        $mysqlOnDelete = strtoupper($mysqlFk['onDelete'] ?? 'NO ACTION');
        
        if ($prismaOnDelete !== $mysqlOnDelete) {
            $issue = $this->addIssue(
                'foreign_key_mismatch',
                "Foreign key onDelete action mismatch: Prisma '{$prismaOnDelete}' vs MySQL '{$mysqlOnDelete}'",
                'low',
                $modelName,
                null,
                "Consider aligning foreign key actions between Prisma and MySQL"
            );
            $modelReport['issues'][] = $issue;
        }
    }

    /**
     * Check for extra columns in MySQL not defined in Prisma
     * 
     * @param string $modelName Model name
     * @param array $model Model data
     * @param array $mysqlTable MySQL table data
     * @param array &$modelReport Model report to populate
     */
    private function checkExtraColumns($modelName, $model, $mysqlTable, &$modelReport)
    {
        // Build map of Prisma column names
        $prismaColumns = [];
        foreach ($model['fields'] as $field) {
            $prismaColumns[$field['columnName']] = true;
        }

        // Check for extra MySQL columns
        foreach ($mysqlTable['columns'] as $columnName => $column) {
            if (!isset($prismaColumns[$columnName])) {
                $issue = $this->addIssue(
                    'extra_column',
                    "Column '{$columnName}' exists in MySQL but not in Prisma model '{$modelName}'",
                    'low',
                    $modelName,
                    null,
                    "Add field to Prisma model or remove column from MySQL table"
                );
                $modelReport['issues'][] = $issue;
            }
        }
    }

    /**
     * Add an issue to the issues list
     * 
     * @param string $type Issue type
     * @param string $description Issue description
     * @param string $severity Severity level
     * @param string $modelName Model name
     * @param string|null $fieldName Field name
     * @param string $recommendation Recommendation
     * @return array Issue data
     */
    private function addIssue($type, $description, $severity, $modelName, $fieldName, $recommendation)
    {
        $issue = [
            'type' => $type,
            'description' => $description,
            'severity' => $severity,
            'modelName' => $modelName,
            'fieldName' => $fieldName,
            'recommendation' => $recommendation
        ];

        $this->issues[] = $issue;
        return $issue;
    }

    /**
     * Generate summary of comparison results
     * 
     * @return array Summary data
     */
    private function generateSummary()
    {
        $summary = [
            'totalModels' => count($this->compatibilityReport),
            'compatibleModels' => 0,
            'incompatibleModels' => 0,
            'totalIssues' => count($this->issues),
            'issuesBySeverity' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ],
            'issuesByType' => []
        ];

        // Count compatible models
        foreach ($this->compatibilityReport as $report) {
            if ($report['compatible']) {
                $summary['compatibleModels']++;
            } else {
                $summary['incompatibleModels']++;
            }
        }

        // Count issues by severity and type
        foreach ($this->issues as $issue) {
            $summary['issuesBySeverity'][$issue['severity']]++;
            
            if (!isset($summary['issuesByType'][$issue['type']])) {
                $summary['issuesByType'][$issue['type']] = 0;
            }
            $summary['issuesByType'][$issue['type']]++;
        }

        return $summary;
    }

    /**
     * Get count of issues by severity
     * 
     * @param string $severity Severity level
     * @return int Issue count
     */
    private function getSeverityCount($severity)
    {
        $count = 0;
        foreach ($this->issues as $issue) {
            if ($issue['severity'] === $severity) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get all issues
     * 
     * @return array Issues array
     */
    public function getIssues()
    {
        return $this->issues;
    }

    /**
     * Get compatibility report
     * 
     * @return array Compatibility report
     */
    public function getCompatibilityReport()
    {
        return $this->compatibilityReport;
    }

    /**
     * Get issues for a specific model
     * 
     * @param string $modelName Model name
     * @return array Issues for the model
     */
    public function getModelIssues($modelName)
    {
        return array_filter($this->issues, function($issue) use ($modelName) {
            return $issue['modelName'] === $modelName;
        });
    }

    /**
     * Get issues by severity
     * 
     * @param string $severity Severity level
     * @return array Issues with specified severity
     */
    public function getIssuesBySeverity($severity)
    {
        return array_filter($this->issues, function($issue) use ($severity) {
            return $issue['severity'] === $severity;
        });
    }
}
