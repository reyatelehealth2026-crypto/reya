<?php

namespace Tools\Audit\Analyzers;

use Tools\Audit\Interfaces\ScannerInterface;

/**
 * Scanner for Next.js API endpoints in the inbox system.
 * 
 * Scans /src/app/api/inbox/ directory recursively to discover route handlers,
 * extract HTTP methods, request/response schemas, authentication, and LINE account filtering.
 * 
 * Requirements: 1.1, 1.3, 1.4
 */
class NextJsEndpointScanner implements ScannerInterface
{
    private string $basePath;
    private array $endpoints = [];
    private array $validationErrors = [];
    private array $scanStatistics = [
        'files_scanned' => 0,
        'endpoints_found' => 0,
        'errors' => 0,
        'skipped_files' => 0,
    ];

    /**
     * Constructor.
     * 
     * @param string $basePath Base path to Next.js codebase
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Run the analysis and return results.
     * 
     * @return array Analysis results with discovered endpoints
     */
    public function analyze(): array
    {
        if (!$this->validate()) {
            return [
                'success' => false,
                'errors' => $this->validationErrors,
                'endpoints' => [],
            ];
        }

        $apiPath = $this->basePath . '/src/app/api/inbox';
        $this->endpoints = $this->scan($apiPath);

        return [
            'success' => true,
            'endpoints' => $this->endpoints,
            'statistics' => $this->scanStatistics,
        ];
    }

    /**
     * Get the name of this analyzer.
     * 
     * @return string Analyzer name
     */
    public function getName(): string
    {
        return 'NextJsEndpointScanner';
    }

    /**
     * Get the version of this analyzer.
     * 
     * @return string Version string
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Validate that the analyzer is ready to run.
     * 
     * @return bool True if ready
     */
    public function validate(): bool
    {
        $this->validationErrors = [];

        if (empty($this->basePath)) {
            $this->validationErrors[] = 'Base path is not set';
        }

        if (!is_dir($this->basePath)) {
            $this->validationErrors[] = "Base path does not exist: {$this->basePath}";
        }

        $apiPath = $this->basePath . '/src/app/api/inbox';
        if (!is_dir($apiPath)) {
            $this->validationErrors[] = "Next.js API inbox directory not found: {$apiPath}";
        }

        return empty($this->validationErrors);
    }

    /**
     * Get validation errors.
     * 
     * @return array Array of error messages
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Scan the specified path and return discovered endpoints.
     * 
     * @param string $path Path to scan
     * @return array Array of discovered endpoints
     */
    public function scan(string $path): array
    {
        $endpoints = [];
        
        if (!is_dir($path)) {
            return $endpoints;
        }

        $files = $this->findRouteFiles($path);
        
        foreach ($files as $file) {
            try {
                $fileEndpoints = $this->scanFile($file);
                $endpoints = array_merge($endpoints, $fileEndpoints);
            } catch (\Exception $e) {
                $this->scanStatistics['errors']++;
                // Log error but continue scanning
                error_log("Error scanning file {$file}: " . $e->getMessage());
            }
        }

        return $endpoints;
    }

    /**
     * Get file patterns this scanner looks for.
     * 
     * @return array Array of patterns
     */
    public function getFilePatterns(): array
    {
        return ['route.ts', 'route.js'];
    }

    /**
     * Check if this scanner can handle the given file.
     * 
     * @param string $filePath Path to file
     * @return bool True if can scan
     */
    public function canScanFile(string $filePath): bool
    {
        $basename = basename($filePath);
        return in_array($basename, $this->getFilePatterns());
    }

    /**
     * Scan a single file and return discovered endpoints.
     * 
     * @param string $filePath Path to file
     * @return array Array of discovered endpoints
     */
    public function scanFile(string $filePath): array
    {
        if (!$this->canScanFile($filePath)) {
            $this->scanStatistics['skipped_files']++;
            return [];
        }

        $this->scanStatistics['files_scanned']++;

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception("Failed to read file: {$filePath}");
        }

        $endpoints = [];
        $apiPath = $this->extractApiPath($filePath);
        
        // Extract HTTP methods and their implementations
        $methods = $this->extractHttpMethods($content);
        
        foreach ($methods as $method => $methodInfo) {
            $endpoint = [
                'path' => $apiPath,
                'file' => $filePath,
                'method' => $method,
                'authentication' => $methodInfo['authentication'],
                'lineAccountFiltering' => $methodInfo['lineAccountFiltering'],
                'requestParams' => $methodInfo['requestParams'],
                'requestSchema' => $methodInfo['requestSchema'],
                'responseSchema' => $methodInfo['responseSchema'],
                'databaseTables' => $methodInfo['databaseTables'],
                'phpBridgeCalls' => $methodInfo['phpBridgeCalls'],
            ];
            
            $endpoints[] = $endpoint;
            $this->scanStatistics['endpoints_found']++;
        }

        return $endpoints;
    }

    /**
     * Get statistics about the last scan.
     * 
     * @return array Statistics
     */
    public function getScanStatistics(): array
    {
        return $this->scanStatistics;
    }

    /**
     * Find all route files in the directory recursively.
     * 
     * @param string $dir Directory to search
     * @return array Array of file paths
     */
    private function findRouteFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->canScanFile($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Extract API path from file path.
     * 
     * Converts file path to API route path.
     * Example: /path/to/src/app/api/inbox/conversations/route.ts -> /api/inbox/conversations
     * 
     * @param string $filePath File path
     * @return string API path
     */
    private function extractApiPath(string $filePath): string
    {
        // Remove base path and get relative path
        $relativePath = str_replace($this->basePath, '', $filePath);
        
        // Extract the API path from src/app/api/...
        if (preg_match('#/src/app/(api/[^/]+(?:/[^/]+)*)/route\.(ts|js)$#', $relativePath, $matches)) {
            return '/' . $matches[1];
        }

        // Fallback: try to extract from the path
        $parts = explode('/', trim($relativePath, '/'));
        $apiIndex = array_search('api', $parts);
        
        if ($apiIndex !== false) {
            $apiParts = array_slice($parts, $apiIndex);
            // Remove route.ts or route.js
            array_pop($apiParts);
            return '/' . implode('/', $apiParts);
        }

        return '/api/unknown';
    }

    /**
     * Extract HTTP methods and their details from file content.
     * 
     * @param string $content File content
     * @return array Array of methods with their details
     */
    private function extractHttpMethods(string $content): array
    {
        $methods = [];
        $httpMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($httpMethods as $method) {
            // Look for: export async function GET(request: NextRequest)
            $pattern = '/export\s+async\s+function\s+' . $method . '\s*\([^)]*\)\s*\{([^}]*(?:\{[^}]*\}[^}]*)*)\}/s';
            
            if (preg_match($pattern, $content, $matches)) {
                $methodBody = $matches[1];
                
                $methods[$method] = [
                    'authentication' => $this->detectAuthentication($methodBody),
                    'lineAccountFiltering' => $this->detectLineAccountFiltering($methodBody),
                    'requestParams' => $this->extractRequestParams($methodBody, $method),
                    'requestSchema' => $this->extractRequestSchema($methodBody, $method),
                    'responseSchema' => $this->extractResponseSchema($methodBody),
                    'databaseTables' => $this->extractDatabaseTables($methodBody),
                    'phpBridgeCalls' => $this->detectPhpBridgeCalls($methodBody),
                ];
            }
        }

        return $methods;
    }

    /**
     * Detect if authentication is required.
     * 
     * @param string $methodBody Method body content
     * @return bool True if authentication detected
     */
    private function detectAuthentication(string $methodBody): bool
    {
        // Look for: const session = await auth()
        // And check for: if (!session?.user)
        return (
            strpos($methodBody, 'await auth()') !== false ||
            strpos($methodBody, 'session?.user') !== false ||
            strpos($methodBody, 'session.user') !== false
        );
    }

    /**
     * Detect LINE account filtering in queries.
     * 
     * @param string $methodBody Method body content
     * @return bool True if LINE account filtering detected
     */
    private function detectLineAccountFiltering(string $methodBody): bool
    {
        // Look for: lineAccountId in where clauses or filters
        return (
            strpos($methodBody, 'lineAccountId') !== false &&
            (
                strpos($methodBody, 'where.lineAccountId') !== false ||
                strpos($methodBody, 'lineAccountId:') !== false ||
                strpos($methodBody, 'session.user.lineAccountId') !== false
            )
        );
    }

    /**
     * Extract request parameters from method body.
     * 
     * @param string $methodBody Method body content
     * @param string $method HTTP method
     * @return array Array of parameter names
     */
    private function extractRequestParams(string $methodBody, string $method): array
    {
        $params = [];

        if ($method === 'GET') {
            // Extract from searchParams.get('paramName')
            preg_match_all('/searchParams\.get\([\'"]([^\'"]+)[\'"]\)/', $methodBody, $matches);
            if (!empty($matches[1])) {
                $params = array_unique($matches[1]);
            }
        } else {
            // Extract from body destructuring: const { param1, param2 } = body
            if (preg_match('/const\s*\{([^}]+)\}\s*=\s*(?:body|await\s+request\.json\(\))/', $methodBody, $matches)) {
                $paramString = $matches[1];
                // Split by comma and clean up
                $paramList = array_map('trim', explode(',', $paramString));
                foreach ($paramList as $param) {
                    // Handle: param or param = defaultValue
                    if (preg_match('/^(\w+)/', $param, $paramMatch)) {
                        $params[] = $paramMatch[1];
                    }
                }
            }
        }

        return $params;
    }

    /**
     * Extract request schema (Zod validation).
     * 
     * @param string $methodBody Method body content
     * @param string $method HTTP method
     * @return array|null Schema definition or null
     */
    private function extractRequestSchema(string $methodBody, string $method): ?array
    {
        // Look for Zod schema validation patterns
        // Example: const schema = z.object({ ... })
        if (preg_match('/z\.object\(\{([^}]+)\}\)/', $methodBody, $matches)) {
            $schemaContent = $matches[1];
            $schema = [];
            
            // Extract field definitions: fieldName: z.string(), etc.
            preg_match_all('/(\w+):\s*z\.(\w+)\(\)/', $schemaContent, $fieldMatches, PREG_SET_ORDER);
            
            foreach ($fieldMatches as $match) {
                $schema[$match[1]] = [
                    'type' => $match[2],
                    'required' => strpos($schemaContent, $match[1] . ': z.' . $match[2] . '().optional()') === false,
                ];
            }
            
            return !empty($schema) ? $schema : null;
        }

        return null;
    }

    /**
     * Extract response schema from method body.
     * 
     * @param string $methodBody Method body content
     * @return array|null Response structure or null
     */
    private function extractResponseSchema(string $methodBody): ?array
    {
        $schema = [
            'success_responses' => [],
            'error_responses' => [],
        ];

        // Extract success responses: NextResponse.json({ ... })
        preg_match_all('/NextResponse\.json\(\s*\{([^}]+)\}(?:\s*,\s*\{\s*status:\s*(\d+)\s*\})?\)/', $methodBody, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $responseContent = $match[1];
            $status = isset($match[2]) ? (int)$match[2] : 200;
            
            // Extract field names from response
            preg_match_all('/(\w+):/', $responseContent, $fieldMatches);
            
            $response = [
                'status' => $status,
                'fields' => $fieldMatches[1] ?? [],
            ];
            
            if ($status >= 200 && $status < 300) {
                $schema['success_responses'][] = $response;
            } else {
                $schema['error_responses'][] = $response;
            }
        }

        return !empty($schema['success_responses']) || !empty($schema['error_responses']) ? $schema : null;
    }

    /**
     * Extract database tables accessed in the method.
     * 
     * @param string $methodBody Method body content
     * @return array Array of table names
     */
    private function extractDatabaseTables(string $methodBody): array
    {
        $tables = [];

        // Extract Prisma model access: prisma.modelName.findMany, etc.
        preg_match_all('/prisma\.(\w+)\.(?:findMany|findUnique|findFirst|create|update|updateMany|delete|deleteMany|count)/', $methodBody, $matches);
        
        if (!empty($matches[1])) {
            $tables = array_unique($matches[1]);
            
            // Convert camelCase to snake_case for table names
            $tables = array_map(function($table) {
                return $this->camelToSnake($table);
            }, $tables);
        }

        return $tables;
    }

    /**
     * Detect PHP bridge API calls.
     * 
     * @param string $methodBody Method body content
     * @return array Array of PHP bridge calls
     */
    private function detectPhpBridgeCalls(string $methodBody): array
    {
        $calls = [];

        // Look for PHP bridge function calls
        // Example: sendLineMessage({ ... })
        $phpBridgeFunctions = [
            'sendLineMessage',
            'callPhpApi',
            'phpBridge',
        ];

        foreach ($phpBridgeFunctions as $func) {
            if (strpos($methodBody, $func) !== false) {
                $calls[] = [
                    'function' => $func,
                    'detected' => true,
                ];
            }
        }

        // Look for direct fetch calls to PHP_API_URL
        if (strpos($methodBody, 'PHP_API_URL') !== false) {
            $calls[] = [
                'function' => 'fetch',
                'target' => 'PHP_API_URL',
                'detected' => true,
            ];
        }

        return $calls;
    }

    /**
     * Convert camelCase to snake_case.
     * 
     * @param string $input CamelCase string
     * @return string snake_case string
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}

