# Task 3: Schema Compatibility Analyzer - Implementation Summary

## Overview

Successfully implemented all required classes for schema compatibility analysis between Prisma ORM schemas and MySQL database schemas. This implementation fulfills Requirements 2.1-2.6 from the API Compatibility Audit specification.

## Implemented Classes

### 1. PrismaSchemaParser (`analyzers/PrismaSchemaParser.php`)

**Purpose**: Parses Prisma schema files to extract models, fields, relations, indexes, and mappings.

**Key Features**:
- Parses `model` blocks with all field definitions
- Handles `@@map` directives for table name mappings
- Handles `@map` directives for column name mappings
- Extracts field types, nullable constraints, defaults, and auto-increment settings
- Parses `@relation` directives for foreign key relationships
- Extracts `@@index` and `@@unique` directives
- Parses `enum` definitions
- Provides helper methods for querying parsed schema

**Key Methods**:
- `parse()` - Main parsing method that returns complete schema structure
- `getModels()` - Returns all parsed models
- `getModel($modelName)` - Gets specific model by name
- `getTableName($modelName)` - Gets MySQL table name for a model
- `getColumnName($modelName, $fieldName)` - Gets MySQL column name for a field
- `toSnakeCase($str)` - Converts PascalCase/camelCase to snake_case

**Data Structure**:
```php
[
    'success' => bool,
    'errors' => array,
    'models' => [
        'ModelName' => [
            'name' => 'ModelName',
            'tableName' => 'table_name',
            'fields' => [
                'fieldName' => [
                    'name' => 'fieldName',
                    'columnName' => 'column_name',
                    'type' => 'String',
                    'nullable' => false,
                    'default' => null,
                    'unique' => false,
                    'primaryKey' => false,
                    'autoIncrement' => false,
                    'relation' => null
                ]
            ],
            'indexes' => [...],
            'relations' => [...],
            'uniqueConstraints' => [...]
        ]
    ],
    'enums' => [...]
]
```

### 2. MySqlSchemaExtractor (`analyzers/MySqlSchemaExtractor.php`)

**Purpose**: Extracts database schema information from MySQL database via PDO connection.

**Key Features**:
- Connects to MySQL database using PDO
- Extracts table structures using `SHOW FULL COLUMNS`
- Extracts indexes using `SHOW INDEX`
- Extracts foreign keys from `INFORMATION_SCHEMA`
- Parses column types, constraints, and defaults
- Identifies primary keys, unique constraints, and auto-increment columns
- Provides helper methods for querying extracted schema

**Key Methods**:
- `extract($tableNames)` - Main extraction method (extracts all tables if not specified)
- `getTables()` - Returns all extracted tables
- `getTable($tableName)` - Gets specific table by name
- `getColumn($tableName, $columnName)` - Gets specific column
- `tableExists($tableName)` - Checks if table exists
- `columnExists($tableName, $columnName)` - Checks if column exists
- `getPrimaryKey($tableName)` - Gets primary key columns
- `getIndexes($tableName)` - Gets all indexes for a table
- `getForeignKeys($tableName)` - Gets all foreign keys for a table

**Data Structure**:
```php
[
    'success' => bool,
    'errors' => array,
    'tables' => [
        'table_name' => [
            'name' => 'table_name',
            'columns' => [
                'column_name' => [
                    'name' => 'column_name',
                    'type' => 'varchar',
                    'fullType' => 'varchar(255)',
                    'nullable' => false,
                    'default' => null,
                    'extra' => '',
                    'autoIncrement' => false,
                    'primaryKey' => false,
                    'unique' => false,
                    'comment' => '',
                    'length' => '255',
                    'unsigned' => false
                ]
            ],
            'indexes' => [...],
            'foreignKeys' => [...],
            'primaryKey' => ['id']
        ]
    ]
]
```

### 3. SchemaComparator (`analyzers/SchemaComparator.php`)

**Purpose**: Compares Prisma schema models with MySQL database tables to identify compatibility issues.

**Key Features**:
- Compares table names (handles Prisma mappings)
- Compares column names (handles field mappings)
- Compares data types with comprehensive type mapping
- Compares nullable constraints
- Compares default values
- Compares auto-increment settings
- Compares indexes and unique constraints
- Compares foreign key relationships and actions
- Identifies missing tables, columns, indexes, and foreign keys
- Identifies extra columns in MySQL not in Prisma
- Assigns severity levels (critical, high, medium, low)
- Generates actionable recommendations

**Type Mapping**:
```php
'String' => ['varchar', 'text', 'char', 'longtext', 'mediumtext', 'tinytext']
'Int' => ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint']
'BigInt' => ['bigint']
'Float' => ['float', 'double', 'decimal']
'Decimal' => ['decimal', 'numeric']
'Boolean' => ['tinyint', 'boolean', 'bool']
'DateTime' => ['datetime', 'timestamp']
'Date' => ['date']
'Time' => ['time']
'Json' => ['json', 'text', 'longtext']
'Bytes' => ['blob', 'binary', 'varbinary', 'longblob', 'mediumblob', 'tinyblob']
```

**Key Methods**:
- `compare()` - Main comparison method that returns full compatibility report
- `getIssues()` - Returns all detected issues
- `getCompatibilityReport()` - Returns detailed compatibility report per model
- `getModelIssues($modelName)` - Gets issues for specific model
- `getIssuesBySeverity($severity)` - Gets issues by severity level

**Issue Types**:
- `missing_table` - Table doesn't exist in MySQL
- `missing_column` - Column doesn't exist in MySQL
- `type_mismatch` - Data type incompatibility
- `constraint_mismatch` - Nullable, default, or auto-increment mismatch
- `missing_index` - Index missing in MySQL
- `missing_foreign_key` - Foreign key missing in MySQL
- `foreign_key_mismatch` - Foreign key action mismatch
- `extra_column` - Column in MySQL but not in Prisma
- `default_mismatch` - Default value mismatch

**Data Structure**:
```php
[
    'success' => bool,
    'issues' => [
        [
            'type' => 'missing_column',
            'description' => 'Column ... does not exist',
            'severity' => 'critical',
            'modelName' => 'ModelName',
            'fieldName' => 'fieldName',
            'recommendation' => 'Add column ...'
        ]
    ],
    'compatibilityReport' => [
        'ModelName' => [
            'modelName' => 'ModelName',
            'tableName' => 'table_name',
            'exists' => true,
            'compatible' => false,
            'issues' => [...]
        ]
    ],
    'summary' => [
        'totalModels' => 3,
        'compatibleModels' => 2,
        'incompatibleModels' => 1,
        'totalIssues' => 5,
        'issuesBySeverity' => [
            'critical' => 1,
            'high' => 2,
            'medium' => 1,
            'low' => 1
        ],
        'issuesByType' => [...]
    ]
]
```

### 4. IndexAnalyzer (`analyzers/IndexAnalyzer.php`)

**Purpose**: Analyzes database queries in codebase to identify missing indexes and optimization opportunities.

**Key Features**:
- Scans PHP, TypeScript, and JavaScript files for SQL queries
- Extracts WHERE clause columns from SQL queries
- Extracts JOIN columns from SQL queries
- Extracts ORDER BY columns from SQL queries
- Parses Prisma query patterns (findMany, findFirst, update, delete)
- Tracks column usage frequency across codebase
- Identifies columns without indexes that are frequently queried
- Recommends single-column indexes for WHERE and JOIN clauses
- Recommends composite indexes for common column combinations
- Generates SQL statements for creating recommended indexes
- Assigns priority levels (high, medium, low)

**Key Methods**:
- `analyze($paths)` - Main analysis method that scans paths and generates recommendations
- `getRecommendations()` - Returns all index recommendations
- `getTableRecommendations($tableName)` - Gets recommendations for specific table
- `getRecommendationsByPriority($priority)` - Gets recommendations by priority
- `getQueryPatterns()` - Returns detected query patterns

**Recommendation Reasons**:
- `where_clause` - Column used in WHERE clause without index
- `join_clause` - Column used in JOIN without index
- `composite` - Multiple columns frequently used together

**Data Structure**:
```php
[
    'success' => bool,
    'errors' => array,
    'queryPatterns' => [
        'table_name' => [
            'table' => 'table_name',
            'whereColumns' => ['column1' => 5, 'column2' => 3],
            'joinColumns' => ['column3' => 2],
            'orderByColumns' => ['column4' => 4],
            'files' => ['path/to/file.php', ...]
        ]
    ],
    'recommendations' => [
        [
            'table' => 'table_name',
            'columns' => ['column1'],
            'reason' => 'where_clause',
            'usageCount' => 5,
            'description' => 'Column ... is used in WHERE clause 5 times',
            'priority' => 'medium',
            'sql' => 'CREATE INDEX `idx_column1` ON `table_name` (`column1`);'
        ]
    ],
    'summary' => [
        'tablesAnalyzed' => 10,
        'totalRecommendations' => 8,
        'recommendationsByPriority' => [
            'high' => 2,
            'medium' => 4,
            'low' => 2
        ],
        'recommendationsByReason' => [...]
    ]
]
```

## Testing

Created comprehensive test script: `test_schema_analyzer.php`

**Test Coverage**:
1. **PrismaSchemaParser Test**
   - Parses sample Prisma schema with models, fields, relations, indexes
   - Verifies model extraction
   - Verifies field mapping (@@map and @map directives)
   - Verifies enum extraction

2. **MySqlSchemaExtractor Test**
   - Connects to MySQL database
   - Extracts specific tables (line_users, conversations, messages)
   - Verifies column extraction with types and constraints
   - Verifies index extraction
   - Verifies foreign key extraction

3. **SchemaComparator Test**
   - Compares Prisma models with MySQL tables
   - Identifies compatibility issues
   - Reports issues by severity and type
   - Generates recommendations

4. **IndexAnalyzer Test**
   - Scans PHP files for SQL queries
   - Identifies query patterns
   - Generates index recommendations
   - Prioritizes recommendations

**Running Tests**:
```bash
cd re-ya/tools/audit
php test_schema_analyzer.php
```

## Integration Points

### With Existing Audit Tool

These classes integrate with the existing audit tool structure:

1. **Directory Structure**:
   - Classes placed in `re-ya/tools/audit/analyzers/`
   - Test script in `re-ya/tools/audit/`
   - Uses existing `cache/` directory for temporary files

2. **Dependencies**:
   - Uses `Database` class from `re-ya/classes/Database.php`
   - Compatible with existing `AnalyzerInterface` pattern
   - Can be orchestrated by `AuditOrchestrator` (to be implemented)

3. **Configuration**:
   - Uses existing `config.php` for database connection
   - Can be configured via `re-ya/tools/audit/config.php`

### Usage Example

```php
// 1. Parse Prisma schema
$prismaParser = new PrismaSchemaParser('/path/to/schema.prisma');
$parseResult = $prismaParser->parse();

// 2. Extract MySQL schema
$db = Database::getInstance()->getConnection();
$mysqlExtractor = new MySqlSchemaExtractor($db);
$extractResult = $mysqlExtractor->extract();

// 3. Compare schemas
$comparator = new SchemaComparator($prismaParser, $mysqlExtractor);
$compareResult = $comparator->compare();

// 4. Analyze indexes
$indexAnalyzer = new IndexAnalyzer($mysqlExtractor);
$analyzeResult = $indexAnalyzer->analyze(['/path/to/codebase']);

// 5. Generate report
$report = [
    'schemaComparison' => $compareResult,
    'indexRecommendations' => $analyzeResult
];
```

## Requirements Validation

### Requirement 2.1: Compare Prisma model definitions against MySQL table schemas ✓
- **PrismaSchemaParser** extracts complete Prisma model definitions
- **MySqlSchemaExtractor** extracts complete MySQL table schemas
- **SchemaComparator** performs comprehensive comparison

### Requirement 2.2: Document table name mappings ✓
- **PrismaSchemaParser** handles `@@map` directives
- **SchemaComparator** uses mapped table names for comparison

### Requirement 2.3: Document column name mappings ✓
- **PrismaSchemaParser** handles `@map` directives
- **SchemaComparator** uses mapped column names for comparison

### Requirement 2.4: Verify data type compatibility ✓
- **SchemaComparator** includes comprehensive type mapping
- Identifies type mismatches with severity levels
- Handles special cases (BigInt, Json, Bytes, etc.)

### Requirement 2.5: Identify missing indexes ✓
- **IndexAnalyzer** scans codebase for query patterns
- Identifies columns without indexes
- Recommends single-column and composite indexes
- Generates SQL for creating indexes

### Requirement 2.6: Verify foreign key relationships ✓
- **PrismaSchemaParser** extracts relation definitions
- **MySqlSchemaExtractor** extracts foreign key constraints
- **SchemaComparator** compares foreign keys and actions (onDelete, onUpdate)

### Requirement 2.7: Identify schema conflicts ✓
- **SchemaComparator** detects all conflict types
- Assigns severity levels (critical for data corruption risks)
- Provides actionable recommendations

## Next Steps

1. **Property-Based Tests** (Optional tasks 3.4-3.6, 3.8):
   - Implement property tests for schema comparison completeness
   - Implement property tests for schema name mapping
   - Implement property tests for data type compatibility
   - Implement property tests for missing index identification

2. **Integration**:
   - Wire these classes into the main audit orchestrator
   - Add to CLI interface for running schema analysis
   - Include in report generators

3. **Enhancements**:
   - Add support for schema migration generation
   - Add support for schema diff visualization
   - Add caching for large schema extractions
   - Add parallel processing for large codebases

## Files Created

1. `re-ya/tools/audit/analyzers/PrismaSchemaParser.php` (420 lines)
2. `re-ya/tools/audit/analyzers/MySqlSchemaExtractor.php` (340 lines)
3. `re-ya/tools/audit/analyzers/SchemaComparator.php` (720 lines)
4. `re-ya/tools/audit/analyzers/IndexAnalyzer.php` (580 lines)
5. `re-ya/tools/audit/test_schema_analyzer.php` (280 lines)
6. `re-ya/tools/audit/TASK-3-SUMMARY.md` (this file)

**Total Lines of Code**: ~2,340 lines

## Conclusion

Task 3 has been successfully completed with all required classes implemented and tested. The schema compatibility analyzer provides comprehensive analysis capabilities for identifying compatibility issues between Prisma ORM schemas and MySQL database schemas, as well as recommending missing indexes based on actual query patterns in the codebase.

The implementation is production-ready and can be integrated into the larger API Compatibility Audit tool for generating comprehensive compatibility reports.
