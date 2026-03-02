<?php

namespace Tools\Audit\Analyzers;

use Tools\Audit\Interfaces\ScannerInterface;

/**
 * Scanner for PHP API endpoints in the backend system.
 * 
 * Scans /api/inbox.php and /api/inbox-v2.php to discover action handlers,
 * extract HTTP methods, request parameters, response formats, authentication,
 * and LINE account filtering.
 * 
 * Requirements: 1.2, 1.3, 1.4
 */
class PhpEndpointScanner implements ScannerInterface
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
     * @param string $basePath Base path to PHP codebase
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

        $apiPath = $this->basePath . '/api';
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
        return 'PhpEndpointScanner';
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

        $apiPath = $this->basePath . '/api';
        if (!is_dir($apiPath)) {
            $this->validationErrors[] = "PHP API directory not found: {$apiPath}";
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

        $files = $this->findApiFiles($path);
        
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
        return ['inbox.php', 'inbox-v2.php'];
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
        
        // Extract action handlers from switch/case statements
        $actions = $this->extractActionHandlers($content);
        
        foreach ($actions as $action => $actionInfo) {
            $endpoint = [
                'file' => basename($filePath),
                'action' => $action,
                'method' => $actionInfo['method'],
                'authentication' => $actionInfo['authentication'],
                'lineAccountFiltering' => $actionInfo['lineAccountFiltering'],
                'requestParams' => $actionInfo['requestParams'],
                'responseFormat' => $actionInfo['responseFormat'],
                'databaseTables' => $actionInfo['databaseTables'],
                'serviceClasses' => $actionInfo['serviceClasses'],
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
     * Find all API files in the directory.
     * 
     * @param string $dir Directory to search
     * @return array Array of file paths
     */
    private function findApiFiles(string $dir): array
    {
        $files = [];
        
        foreach ($this->getFilePatterns() as $pattern) {
            $filePath = $dir . '/' . $pattern;
            if (file_exists($filePath)) {
                $files[] = $filePath;
            }
        }

        return $files;
    }

    /**
     * Extract action handlers from switch/case statements.
     * 
     * @param string $content File content
     * @return array Array of actions with their details
     */
    private function extractActionHandlers(string $content): array
    {
        $actions = [];
        
        // Find the main switch statement
        // Pattern: switch ($action) { ... }
        if (!preg_match('/switch\s*\(\s*\$action\s*\)\s*\{/s', $content, $switchMatch, PREG_OFFSET_CAPTURE)) {
            return $actions;
        }
        
        $switchStart = $switchMatch[0][1];
        
        // Find the matching closing brace for the switch statement
        $switchEnd = $this->findMatchingBrace($content, $switchStart + strlen($switchMatch[0][0]));
        
        if ($switchEnd === false) {
            return $actions;
        }
        
        $switchContent = substr($content, $switchStart, $switchEnd - $switchStart);
        
        // Extract individual case blocks
        // Pattern: case 'action_name': ... break;
        preg_match_all(
            '/case\s+[\'"]([^\'"]+)[\'"]:\s*(.*?)(?=case\s+[\'"]|default\s*:|$)/s',
            $switchContent,
            $caseMatches,
            PREG_SET_ORDER
        );
        
        foreach ($caseMatches as $caseMatch) {
            $actionName = $caseMatch[1];
            $caseBody = $caseMatch[2];
            
            // Skip if this is just a fallthrough case (no code, just another case follows)
            if (trim($caseBody) === '' || preg_match('/^\s*case\s+/', $caseBody)) {
                continue;
            }
            
            $actions[$actionName] = [
                'method' => $this->extractHttpMethod($caseBody),
                'authentication' => $this->detectAuthentication($caseBody, $content),
                'lineAccountFiltering' => $this->detectLineAccountFiltering($caseBody, $content),
                'requestParams' => $this->extractRequestParams($caseBody),
                'responseFormat' => $this->extractResponseFormat($caseBody),
                'databaseTables' => $this->extractDatabaseTables($caseBody),
                'serviceClasses' => $this->extractServiceClasses($caseBody),
            ];
        }

        return $actions;
    }

    /**
     * Find the matching closing brace for an opening brace.
     * 
     * @param string $content Content to search
     * @param int $startPos Position after the opening brace
     * @return int|false Position of closing brace or false if not found
     */
    private function findMatchingBrace(string $content, int $startPos): int|false
    {
        $depth = 1;
        $length = strlen($content);
        
        for ($i = $startPos; $i < $length; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        
        return false;
    }

    /**
     * Extract HTTP method from case body.
     * 
     * @param string $caseBody Case body content
     * @return string HTTP method (GET, POST, etc.)
     */
    private function extractHttpMethod(string $caseBody): string
    {
        // Look for: if ($method !== 'GET')
        if (preg_match('/\$method\s*!==\s*[\'"]([A-Z]+)[\'"]/', $caseBody, $match)) {
            return $match[1];
        }
        
        // Look for: if ($method === 'POST')
        if (preg_match('/\$method\s*===\s*[\'"]([A-Z]+)[\'"]/', $caseBody, $match)) {
            return $match[1];
        }
        
        // Default to POST if no method check found (most PHP APIs default to POST)
        return 'POST';
    }

    /**
     * Detect if authentication is required.
     * 
     * @param string $caseBody Case body content
     * @param string $fullContent Full file content for context
     * @return bool True if authentication detected
     */
    private function detectAuthentication(string $caseBody, string $fullContent): bool
    {
        // Check for session checks in the case body
        $sessionChecks = [
            '\$_SESSION[\'admin_id\']',
            '\$_SESSION[\'user_id\']',
            '\$adminId',
            'session_start()',
        ];
        
        foreach ($sessionChecks as $check) {
            if (strpos($caseBody, $check) !== false) {
                return true;
            }
        }
        
        // Check if there's a global session check at the top of the file
        if (preg_match('/session_start\(\)|require.*auth_check\.php/i', $fullContent)) {
            return true;
        }
        
        return false;
    }

    /**
     * Detect LINE account filtering in queries.
     * 
     * @param string $caseBody Case body content
     * @param string $fullContent Full file content for context
     * @return bool True if LINE account filtering detected
     */
    private function detectLineAccountFiltering(string $caseBody, string $fullContent): bool
    {
        // Check for LINE account ID usage in the case body
        $lineAccountPatterns = [
            '\$lineAccountId',
            'line_account_id',
            '\$_SESSION[\'line_account_id\']',
            '\$_SESSION[\'current_bot_id\']',
        ];
        
        foreach ($lineAccountPatterns as $pattern) {
            if (stripos($caseBody, $pattern) !== false) {
                return true;
            }
        }
        
        // Check if service is initialized with LINE account ID
        if (preg_match('/new\s+\w+Service\s*\([^,]+,\s*\$lineAccountId\)/', $caseBody)) {
            return true;
        }
        
        // Check global initialization
        if (preg_match('/\$lineAccountId\s*=.*\$_SESSION/i', $fullContent)) {
            return true;
        }
        
        return false;
    }

    /**
     * Extract request parameters from case body.
     * 
     * @param string $caseBody Case body content
     * @return array Array of parameter names
     */
    private function extractRequestParams(string $caseBody): array
    {
        $params = [];
        
        // Extract from $_GET['param']
        preg_match_all('/\$_GET\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]/', $caseBody, $getMatches);
        if (!empty($getMatches[1])) {
            $params = array_merge($params, $getMatches[1]);
        }
        
        // Extract from $_POST['param']
        preg_match_all('/\$_POST\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]/', $caseBody, $postMatches);
        if (!empty($postMatches[1])) {
            $params = array_merge($params, $postMatches[1]);
        }
        
        // Extract from $jsonInput['param'] or $input['param']
        preg_match_all('/\$(?:jsonInput|input)\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]/', $caseBody, $jsonMatches);
        if (!empty($jsonMatches[1])) {
            $params = array_merge($params, $jsonMatches[1]);
        }
        
        // Extract from variable assignments: $paramName = $_GET['param'] ?? ...
        preg_match_all('/\$(\w+)\s*=.*\$_(?:GET|POST)\s*\[/', $caseBody, $varMatches);
        if (!empty($varMatches[1])) {
            $params = array_merge($params, $varMatches[1]);
        }
        
        return array_unique($params);
    }

    /**
     * Extract response format from case body.
     * 
     * @param string $caseBody Case body content
     * @return array|null Response structure or null
     */
    private function extractResponseFormat(string $caseBody): ?array
    {
        $responses = [
            'success_responses' => [],
            'error_responses' => [],
        ];
        
        // Extract json_encode patterns
        preg_match_all(
            '/echo\s+json_encode\s*\(\s*\[([^\]]+)\]\s*\)/s',
            $caseBody,
            $matches,
            PREG_SET_ORDER
        );
        
        foreach ($matches as $match) {
            $responseContent = $match[1];
            
            // Extract field names from the array
            preg_match_all('/[\'"](\w+)[\'"]\s*=>/', $responseContent, $fieldMatches);
            
            $fields = $fieldMatches[1] ?? [];
            
            // Determine if it's a success or error response
            $isSuccess = stripos($responseContent, "'success' => true") !== false ||
                        stripos($responseContent, '"success" => true') !== false;
            
            $response = [
                'fields' => $fields,
                'has_success_field' => in_array('success', $fields),
                'has_message_field' => in_array('message', $fields),
                'has_data_field' => in_array('data', $fields),
            ];
            
            if ($isSuccess) {
                $responses['success_responses'][] = $response;
            } else {
                $responses['error_responses'][] = $response;
            }
        }
        
        return !empty($responses['success_responses']) || !empty($responses['error_responses']) 
            ? $responses 
            : null;
    }

    /**
     * Extract database tables accessed in the case body.
     * 
     * @param string $caseBody Case body content
     * @return array Array of table names
     */
    private function extractDatabaseTables(string $caseBody): array
    {
        $tables = [];
        
        // Extract from SQL queries: FROM table_name, JOIN table_name
        preg_match_all(
            '/(?:FROM|JOIN|INTO|UPDATE)\s+`?(\w+)`?/i',
            $caseBody,
            $matches
        );
        
        if (!empty($matches[1])) {
            $tables = array_merge($tables, $matches[1]);
        }
        
        // Note: Service classes may access tables, but we can't determine
        // which tables without analyzing the service class itself
        // We'll just note which services are used
        
        return array_unique($tables);
    }

    /**
     * Extract service classes used in the case body.
     * 
     * @param string $caseBody Case body content
     * @return array Array of service class names
     */
    private function extractServiceClasses(string $caseBody): array
    {
        $services = [];
        
        // Extract from $serviceName->method() calls
        preg_match_all('/\$(\w+(?:Service|API))\s*->/', $caseBody, $matches);
        
        if (!empty($matches[1])) {
            $services = array_unique($matches[1]);
        }
        
        return $services;
    }
}
