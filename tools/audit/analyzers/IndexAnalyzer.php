<?php

namespace Tools\Audit\Analyzers;

/**
 * IndexAnalyzer
 * 
 * Analyzes database queries to identify missing indexes and optimization opportunities.
 * Scans WHERE clauses, JOIN conditions, and ORDER BY clauses in both codebases.
 * 
 * Requirements: 2.5
 */
class IndexAnalyzer
{
    private $mysqlExtractor;
    private $recommendations = [];
    private $queryPatterns = [];
    private $errors = [];

    /**
     * Constructor
     * 
     * @param MySqlSchemaExtractor $mysqlExtractor MySQL schema extractor
     */
    public function __construct($mysqlExtractor)
    {
        $this->mysqlExtractor = $mysqlExtractor;
    }

    /**
     * Analyze codebase for missing indexes
     * 
     * @param array $paths Array of paths to scan for queries
     * @return array Analysis results
     */
    public function analyze($paths)
    {
        // Scan files for query patterns
        foreach ($paths as $path) {
            $this->scanPath($path);
        }

        // Analyze query patterns and generate recommendations
        $this->generateRecommendations();

        return [
            'success' => count($this->errors) === 0,
            'errors' => $this->errors,
            'queryPatterns' => $this->queryPatterns,
            'recommendations' => $this->recommendations,
            'summary' => $this->generateSummary()
        ];
    }

    /**
     * Scan a path for SQL queries
     * 
     * @param string $path Path to scan
     */
    private function scanPath($path)
    {
        if (!file_exists($path)) {
            $this->errors[] = "Path not found: {$path}";
            return;
        }

        if (is_file($path)) {
            $this->scanFile($path);
        } elseif (is_dir($path)) {
            $this->scanDirectory($path);
        }
    }

    /**
     * Scan a directory recursively
     * 
     * @param string $dir Directory path
     */
    private function scanDirectory($dir)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->shouldScanFile($file->getPathname())) {
                $this->scanFile($file->getPathname());
            }
        }
    }

    /**
     * Check if file should be scanned
     * 
     * @param string $filepath File path
     * @return bool True if should scan
     */
    private function shouldScanFile($filepath)
    {
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        
        // Scan PHP, TypeScript, and JavaScript files
        return in_array($extension, ['php', 'ts', 'js', 'tsx', 'jsx']);
    }

    /**
     * Scan a file for SQL queries
     * 
     * @param string $filepath File path
     */
    private function scanFile($filepath)
    {
        $content = file_get_contents($filepath);
        
        // Extract SQL queries from PHP PDO/MySQLi
        $this->extractPhpQueries($content, $filepath);
        
        // Extract Prisma queries from TypeScript/JavaScript
        $this->extractPrismaQueries($content, $filepath);
    }

    /**
     * Extract SQL queries from PHP code
     * 
     * @param string $content File content
     * @param string $filepath File path
     */
    private function extractPhpQueries($content, $filepath)
    {
        // Match SQL queries in strings
        $patterns = [
            // Direct SQL strings
            '/["\']SELECT\s+.*?FROM\s+(\w+)(.*?)["\'](?:\s*;|\s*\))/is',
            '/["\']UPDATE\s+(\w+)\s+SET.*?WHERE(.*?)["\'](?:\s*;|\s*\))/is',
            '/["\']DELETE\s+FROM\s+(\w+).*?WHERE(.*?)["\'](?:\s*;|\s*\))/is',
            // Prepared statements
            '/->prepare\s*\(\s*["\']SELECT\s+.*?FROM\s+(\w+)(.*?)["\'](?:\s*;|\s*\))/is',
            '/->query\s*\(\s*["\']SELECT\s+.*?FROM\s+(\w+)(.*?)["\'](?:\s*;|\s*\))/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $this->analyzeQuery($match[0], $filepath);
                }
            }
        }
    }

    /**
     * Extract Prisma queries from TypeScript/JavaScript code
     * 
     * @param string $content File content
     * @param string $filepath File path
     */
    private function extractPrismaQueries($content, $filepath)
    {
        // Match Prisma query patterns
        $patterns = [
            // findMany, findFirst, findUnique with where clause
            '/prisma\.(\w+)\.find(?:Many|First|Unique)\s*\(\s*\{[^}]*where:\s*\{([^}]+)\}/s',
            // update, updateMany with where clause
            '/prisma\.(\w+)\.update(?:Many)?\s*\(\s*\{[^}]*where:\s*\{([^}]+)\}/s',
            // delete, deleteMany with where clause
            '/prisma\.(\w+)\.delete(?:Many)?\s*\(\s*\{[^}]*where:\s*\{([^}]+)\}/s',
            // orderBy clause
            '/orderBy:\s*\{([^}]+)\}/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $this->analyzePrismaQuery($match, $filepath);
                }
            }
        }
    }

    /**
     * Analyze a SQL query for index usage
     * 
     * @param string $query SQL query
     * @param string $filepath File path
     */
    private function analyzeQuery($query, $filepath)
    {
        // Extract table name
        if (preg_match('/FROM\s+`?(\w+)`?/i', $query, $tableMatch)) {
            $tableName = $tableMatch[1];
            
            // Extract WHERE clause columns
            $whereColumns = $this->extractWhereColumns($query);
            
            // Extract JOIN columns
            $joinColumns = $this->extractJoinColumns($query);
            
            // Extract ORDER BY columns
            $orderByColumns = $this->extractOrderByColumns($query);
            
            // Record query pattern
            $this->recordQueryPattern($tableName, $whereColumns, $joinColumns, $orderByColumns, $filepath);
        }
    }

    /**
     * Analyze a Prisma query for index usage
     * 
     * @param array $match Regex match
     * @param string $filepath File path
     */
    private function analyzePrismaQuery($match, $filepath)
    {
        $modelName = $match[1] ?? null;
        $whereClause = $match[2] ?? '';

        if (!$modelName) {
            return;
        }

        // Extract field names from where clause
        $fields = [];
        if (preg_match_all('/(\w+):\s*[{\[]?/', $whereClause, $fieldMatches)) {
            $fields = $fieldMatches[1];
        }

        // Record query pattern (need to map model to table)
        // For now, use snake_case conversion
        $tableName = $this->toSnakeCase($modelName);
        $this->recordQueryPattern($tableName, $fields, [], [], $filepath);
    }

    /**
     * Extract column names from WHERE clause
     * 
     * @param string $query SQL query
     * @return array Column names
     */
    private function extractWhereColumns($query)
    {
        $columns = [];
        
        // Match WHERE clause
        if (preg_match('/WHERE\s+(.*?)(?:ORDER BY|GROUP BY|LIMIT|$)/is', $query, $whereMatch)) {
            $whereClause = $whereMatch[1];
            
            // Extract column names (simple pattern)
            if (preg_match_all('/`?(\w+)`?\s*(?:=|<|>|<=|>=|!=|LIKE|IN)/i', $whereClause, $colMatches)) {
                $columns = array_unique($colMatches[1]);
            }
        }
        
        return $columns;
    }

    /**
     * Extract column names from JOIN clauses
     * 
     * @param string $query SQL query
     * @return array Column names
     */
    private function extractJoinColumns($query)
    {
        $columns = [];
        
        // Match JOIN clauses
        if (preg_match_all('/JOIN\s+`?(\w+)`?\s+.*?ON\s+`?\w+`?\.`?(\w+)`?\s*=\s*`?\w+`?\.`?(\w+)`?/i', $query, $joinMatches, PREG_SET_ORDER)) {
            foreach ($joinMatches as $match) {
                $columns[] = $match[2];
                $columns[] = $match[3];
            }
        }
        
        return array_unique($columns);
    }

    /**
     * Extract column names from ORDER BY clause
     * 
     * @param string $query SQL query
     * @return array Column names
     */
    private function extractOrderByColumns($query)
    {
        $columns = [];
        
        // Match ORDER BY clause
        if (preg_match('/ORDER BY\s+(.*?)(?:LIMIT|$)/is', $query, $orderMatch)) {
            $orderClause = $orderMatch[1];
            
            // Extract column names
            if (preg_match_all('/`?(\w+)`?(?:\s+(?:ASC|DESC))?/i', $orderClause, $colMatches)) {
                $columns = array_unique($colMatches[1]);
            }
        }
        
        return $columns;
    }

    /**
     * Record a query pattern
     * 
     * @param string $tableName Table name
     * @param array $whereColumns WHERE clause columns
     * @param array $joinColumns JOIN columns
     * @param array $orderByColumns ORDER BY columns
     * @param string $filepath File path
     */
    private function recordQueryPattern($tableName, $whereColumns, $joinColumns, $orderByColumns, $filepath)
    {
        if (!isset($this->queryPatterns[$tableName])) {
            $this->queryPatterns[$tableName] = [
                'table' => $tableName,
                'whereColumns' => [],
                'joinColumns' => [],
                'orderByColumns' => [],
                'files' => []
            ];
        }

        // Aggregate column usage
        foreach ($whereColumns as $col) {
            if (!isset($this->queryPatterns[$tableName]['whereColumns'][$col])) {
                $this->queryPatterns[$tableName]['whereColumns'][$col] = 0;
            }
            $this->queryPatterns[$tableName]['whereColumns'][$col]++;
        }

        foreach ($joinColumns as $col) {
            if (!isset($this->queryPatterns[$tableName]['joinColumns'][$col])) {
                $this->queryPatterns[$tableName]['joinColumns'][$col] = 0;
            }
            $this->queryPatterns[$tableName]['joinColumns'][$col]++;
        }

        foreach ($orderByColumns as $col) {
            if (!isset($this->queryPatterns[$tableName]['orderByColumns'][$col])) {
                $this->queryPatterns[$tableName]['orderByColumns'][$col] = 0;
            }
            $this->queryPatterns[$tableName]['orderByColumns'][$col]++;
        }

        // Track files
        if (!in_array($filepath, $this->queryPatterns[$tableName]['files'])) {
            $this->queryPatterns[$tableName]['files'][] = $filepath;
        }
    }

    /**
     * Generate index recommendations based on query patterns
     */
    private function generateRecommendations()
    {
        foreach ($this->queryPatterns as $tableName => $pattern) {
            // Check if table exists
            if (!$this->mysqlExtractor->tableExists($tableName)) {
                continue;
            }

            $existingIndexes = $this->mysqlExtractor->getIndexes($tableName);
            $indexedColumns = $this->getIndexedColumns($existingIndexes);

            // Recommend indexes for frequently queried WHERE columns
            foreach ($pattern['whereColumns'] as $column => $count) {
                if (!in_array($column, $indexedColumns) && $count >= 2) {
                    $this->addRecommendation(
                        $tableName,
                        [$column],
                        'where_clause',
                        $count,
                        "Column '{$column}' is used in WHERE clause {$count} times but has no index",
                        'medium'
                    );
                }
            }

            // Recommend indexes for JOIN columns
            foreach ($pattern['joinColumns'] as $column => $count) {
                if (!in_array($column, $indexedColumns)) {
                    $this->addRecommendation(
                        $tableName,
                        [$column],
                        'join_clause',
                        $count,
                        "Column '{$column}' is used in JOIN clause but has no index",
                        'high'
                    );
                }
            }

            // Recommend composite indexes for common WHERE combinations
            $this->recommendCompositeIndexes($tableName, $pattern, $indexedColumns);
        }
    }

    /**
     * Get list of indexed columns from existing indexes
     * 
     * @param array $indexes Existing indexes
     * @return array Indexed column names
     */
    private function getIndexedColumns($indexes)
    {
        $columns = [];
        
        foreach ($indexes as $index) {
            // For single-column indexes, add the column
            if (count($index['columns']) === 1) {
                $columns[] = $index['columns'][0];
            }
        }
        
        return array_unique($columns);
    }

    /**
     * Recommend composite indexes for common column combinations
     * 
     * @param string $tableName Table name
     * @param array $pattern Query pattern
     * @param array $indexedColumns Already indexed columns
     */
    private function recommendCompositeIndexes($tableName, $pattern, $indexedColumns)
    {
        // Find columns that are frequently used together
        $whereColumns = array_keys($pattern['whereColumns']);
        
        // If multiple WHERE columns are used frequently, recommend composite index
        if (count($whereColumns) >= 2) {
            // Sort by usage frequency
            arsort($pattern['whereColumns']);
            $topColumns = array_slice(array_keys($pattern['whereColumns']), 0, 3);
            
            // Check if composite index exists
            if (!$this->hasCompositeIndex($tableName, $topColumns)) {
                $minCount = min(array_intersect_key($pattern['whereColumns'], array_flip($topColumns)));
                
                if ($minCount >= 2) {
                    $this->addRecommendation(
                        $tableName,
                        $topColumns,
                        'composite',
                        $minCount,
                        "Columns [" . implode(', ', $topColumns) . "] are frequently used together in WHERE clauses",
                        'medium'
                    );
                }
            }
        }
    }

    /**
     * Check if a composite index exists for given columns
     * 
     * @param string $tableName Table name
     * @param array $columns Column names
     * @return bool True if composite index exists
     */
    private function hasCompositeIndex($tableName, $columns)
    {
        $indexes = $this->mysqlExtractor->getIndexes($tableName);
        
        foreach ($indexes as $index) {
            // Check if index covers these columns (in any order)
            $indexColumns = $index['columns'];
            if (count(array_intersect($indexColumns, $columns)) === count($columns)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Add an index recommendation
     * 
     * @param string $tableName Table name
     * @param array $columns Column names
     * @param string $reason Reason for recommendation
     * @param int $usageCount Usage count
     * @param string $description Description
     * @param string $priority Priority level
     */
    private function addRecommendation($tableName, $columns, $reason, $usageCount, $description, $priority)
    {
        $this->recommendations[] = [
            'table' => $tableName,
            'columns' => $columns,
            'reason' => $reason,
            'usageCount' => $usageCount,
            'description' => $description,
            'priority' => $priority,
            'sql' => $this->generateIndexSql($tableName, $columns)
        ];
    }

    /**
     * Generate SQL for creating an index
     * 
     * @param string $tableName Table name
     * @param array $columns Column names
     * @return string SQL statement
     */
    private function generateIndexSql($tableName, $columns)
    {
        $indexName = 'idx_' . implode('_', $columns);
        $columnList = implode(', ', array_map(function($col) {
            return "`{$col}`";
        }, $columns));
        
        return "CREATE INDEX `{$indexName}` ON `{$tableName}` ({$columnList});";
    }

    /**
     * Generate summary of analysis
     * 
     * @return array Summary data
     */
    private function generateSummary()
    {
        $summary = [
            'tablesAnalyzed' => count($this->queryPatterns),
            'totalRecommendations' => count($this->recommendations),
            'recommendationsByPriority' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ],
            'recommendationsByReason' => []
        ];

        foreach ($this->recommendations as $rec) {
            $summary['recommendationsByPriority'][$rec['priority']]++;
            
            if (!isset($summary['recommendationsByReason'][$rec['reason']])) {
                $summary['recommendationsByReason'][$rec['reason']] = 0;
            }
            $summary['recommendationsByReason'][$rec['reason']]++;
        }

        return $summary;
    }

    /**
     * Convert PascalCase/camelCase to snake_case
     * 
     * @param string $str Input string
     * @return string snake_case string
     */
    private function toSnakeCase($str)
    {
        $snake = preg_replace('/([a-z])([A-Z])/', '$1_$2', $str);
        return strtolower($snake);
    }

    /**
     * Get all recommendations
     * 
     * @return array Recommendations array
     */
    public function getRecommendations()
    {
        return $this->recommendations;
    }

    /**
     * Get recommendations for a specific table
     * 
     * @param string $tableName Table name
     * @return array Recommendations for the table
     */
    public function getTableRecommendations($tableName)
    {
        return array_filter($this->recommendations, function($rec) use ($tableName) {
            return $rec['table'] === $tableName;
        });
    }

    /**
     * Get recommendations by priority
     * 
     * @param string $priority Priority level
     * @return array Recommendations with specified priority
     */
    public function getRecommendationsByPriority($priority)
    {
        return array_filter($this->recommendations, function($rec) use ($priority) {
            return $rec['priority'] === $priority;
        });
    }

    /**
     * Get query patterns
     * 
     * @return array Query patterns
     */
    public function getQueryPatterns()
    {
        return $this->queryPatterns;
    }
}
