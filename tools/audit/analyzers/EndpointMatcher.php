<?php

namespace Tools\Audit\Analyzers;

use Tools\Audit\Interfaces\AnalyzerInterface;

/**
 * Matcher for identifying similar endpoints across Next.js and PHP systems.
 * 
 * Compares endpoints by functionality (database tables accessed, operation type)
 * to identify overlapping features between the two systems.
 * 
 * Requirements: 1.5
 */
class EndpointMatcher implements AnalyzerInterface
{
    private array $nextjsEndpoints = [];
    private array $phpEndpoints = [];
    private array $matches = [];
    private array $validationErrors = [];
    private array $matchStatistics = [
        'nextjs_endpoints' => 0,
        'php_endpoints' => 0,
        'exact_matches' => 0,
        'high_similarity_matches' => 0,
        'medium_similarity_matches' => 0,
        'low_similarity_matches' => 0,
        'no_matches' => 0,
    ];

    /**
     * Constructor.
     * 
     * @param array $nextjsEndpoints Endpoints from NextJsEndpointScanner
     * @param array $phpEndpoints Endpoints from PhpEndpointScanner
     */
    public function __construct(array $nextjsEndpoints = [], array $phpEndpoints = [])
    {
        $this->nextjsEndpoints = $nextjsEndpoints;
        $this->phpEndpoints = $phpEndpoints;
    }

    /**
     * Set Next.js endpoints.
     * 
     * @param array $endpoints Endpoints from NextJsEndpointScanner
     * @return self
     */
    public function setNextjsEndpoints(array $endpoints): self
    {
        $this->nextjsEndpoints = $endpoints;
        return $this;
    }

    /**
     * Set PHP endpoints.
     * 
     * @param array $endpoints Endpoints from PhpEndpointScanner
     * @return self
     */
    public function setPhpEndpoints(array $endpoints): self
    {
        $this->phpEndpoints = $endpoints;
        return $this;
    }

    /**
     * Run the analysis and return results.
     * 
     * @return array Analysis results with matched endpoints
     */
    public function analyze(): array
    {
        if (!$this->validate()) {
            return [
                'success' => false,
                'errors' => $this->validationErrors,
                'matches' => [],
            ];
        }

        $this->matches = $this->matchEndpoints();

        return [
            'success' => true,
            'matches' => $this->matches,
            'statistics' => $this->matchStatistics,
        ];
    }

    /**
     * Get the name of this analyzer.
     * 
     * @return string Analyzer name
     */
    public function getName(): string
    {
        return 'EndpointMatcher';
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

        if (empty($this->nextjsEndpoints) && empty($this->phpEndpoints)) {
            $this->validationErrors[] = 'No endpoints provided for matching';
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
     * Match endpoints between Next.js and PHP systems.
     * 
     * @return array Array of matches with similarity scores
     */
    private function matchEndpoints(): array
    {
        $matches = [];
        
        $this->matchStatistics['nextjs_endpoints'] = count($this->nextjsEndpoints);
        $this->matchStatistics['php_endpoints'] = count($this->phpEndpoints);

        // For each Next.js endpoint, find the best matching PHP endpoint(s)
        foreach ($this->nextjsEndpoints as $nextjsEndpoint) {
            $bestMatches = $this->findBestMatches($nextjsEndpoint, $this->phpEndpoints);
            
            if (!empty($bestMatches)) {
                foreach ($bestMatches as $match) {
                    $matches[] = [
                        'nextjs' => $nextjsEndpoint,
                        'php' => $match['endpoint'],
                        'similarity_score' => $match['score'],
                        'similarity_level' => $this->getSimilarityLevel($match['score']),
                        'matching_factors' => $match['factors'],
                        'shared_tables' => $match['shared_tables'],
                        'operation_type' => $match['operation_type'],
                    ];
                    
                    // Update statistics
                    $this->updateMatchStatistics($match['score']);
                }
            } else {
                // No match found for this Next.js endpoint
                $matches[] = [
                    'nextjs' => $nextjsEndpoint,
                    'php' => null,
                    'similarity_score' => 0,
                    'similarity_level' => 'no_match',
                    'matching_factors' => [],
                    'shared_tables' => [],
                    'operation_type' => null,
                ];
                
                $this->matchStatistics['no_matches']++;
            }
        }

        // Find PHP endpoints that don't match any Next.js endpoint
        $matchedPhpEndpoints = array_column(array_filter($matches, fn($m) => $m['php'] !== null), 'php');
        $unmatchedPhpEndpoints = array_filter(
            $this->phpEndpoints,
            fn($phpEndpoint) => !$this->isEndpointInList($phpEndpoint, $matchedPhpEndpoints)
        );

        foreach ($unmatchedPhpEndpoints as $phpEndpoint) {
            $matches[] = [
                'nextjs' => null,
                'php' => $phpEndpoint,
                'similarity_score' => 0,
                'similarity_level' => 'no_match',
                'matching_factors' => [],
                'shared_tables' => [],
                'operation_type' => null,
            ];
            
            $this->matchStatistics['no_matches']++;
        }

        return $matches;
    }

    /**
     * Find the best matching PHP endpoints for a Next.js endpoint.
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoints PHP endpoints to search
     * @return array Array of best matches with scores
     */
    private function findBestMatches(array $nextjsEndpoint, array $phpEndpoints): array
    {
        $matches = [];
        $threshold = 0.3; // Minimum similarity score to consider a match

        foreach ($phpEndpoints as $phpEndpoint) {
            $score = $this->calculateSimilarityScore($nextjsEndpoint, $phpEndpoint);
            
            if ($score >= $threshold) {
                $matches[] = [
                    'endpoint' => $phpEndpoint,
                    'score' => $score,
                    'factors' => $this->getMatchingFactors($nextjsEndpoint, $phpEndpoint),
                    'shared_tables' => $this->getSharedTables($nextjsEndpoint, $phpEndpoint),
                    'operation_type' => $this->determineOperationType($nextjsEndpoint, $phpEndpoint),
                ];
            }
        }

        // Sort by score descending
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return only high-scoring matches (top matches or all above high threshold)
        $highThreshold = 0.7;
        $highMatches = array_filter($matches, fn($m) => $m['score'] >= $highThreshold);
        
        if (!empty($highMatches)) {
            return $highMatches;
        }

        // If no high matches, return the top match if it exists
        return !empty($matches) ? [$matches[0]] : [];
    }

    /**
     * Calculate similarity score between two endpoints.
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoint PHP endpoint
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateSimilarityScore(array $nextjsEndpoint, array $phpEndpoint): float
    {
        $score = 0.0;
        $weights = [
            'database_tables' => 0.40,  // Most important: shared data access
            'operation_type' => 0.25,   // HTTP method and operation type
            'path_similarity' => 0.15,  // URL/action name similarity
            'authentication' => 0.10,   // Both require auth
            'line_filtering' => 0.10,   // Both filter by LINE account
        ];

        // 1. Database tables similarity (most important)
        $tableScore = $this->calculateTableSimilarity($nextjsEndpoint, $phpEndpoint);
        $score += $tableScore * $weights['database_tables'];

        // 2. Operation type similarity (HTTP method)
        $operationScore = $this->calculateOperationSimilarity($nextjsEndpoint, $phpEndpoint);
        $score += $operationScore * $weights['operation_type'];

        // 3. Path/action name similarity
        $pathScore = $this->calculatePathSimilarity($nextjsEndpoint, $phpEndpoint);
        $score += $pathScore * $weights['path_similarity'];

        // 4. Authentication similarity
        $authScore = $this->calculateAuthSimilarity($nextjsEndpoint, $phpEndpoint);
        $score += $authScore * $weights['authentication'];

        // 5. LINE account filtering similarity
        $lineScore = $this->calculateLineFilteringSimilarity($nextjsEndpoint, $phpEndpoint);
        $score += $lineScore * $weights['line_filtering'];

        return round($score, 3);
    }

    /**
     * Calculate database table similarity.
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoint PHP endpoint
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateTableSimilarity(array $nextjsEndpoint, array $phpEndpoint): float
    {
        $nextjsTables = $nextjsEndpoint['databaseTables'] ?? [];
        $phpTables = $phpEndpoint['databaseTables'] ?? [];

        if (empty($nextjsTables) && empty($phpTables)) {
            return 0.0; // Both have no tables - not similar
        }

        if (empty($nextjsTables) || empty($phpTables)) {
            return 0.0; // One has tables, other doesn't - not similar
        }

        // Calculate Jaccard similarity: intersection / union
        $intersection = array_intersect($nextjsTables, $phpTables);
        $union = array_unique(array_merge($nextjsTables, $phpTables));

        return count($union) > 0 ? count($intersection) / count($union) : 0.0;
    }

    /**
     * Calculate operation type similarity (HTTP method).
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoint PHP endpoint
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateOperationSimilarity(array $nextjsEndpoint, array $phpEndpoint): float
    {
        $nextjsMethod = strtoupper($nextjsEndpoint['method'] ?? '');
        $phpMethod = strtoupper($phpEndpoint['method'] ?? '');

        if ($nextjsMethod === $phpMethod) {
            return 1.0;
        }

        // Partial match for similar operations
        $readMethods = ['GET'];
        $writeMethods = ['POST', 'PUT', 'PATCH'];
        $deleteMethods = ['DELETE'];

        if (in_array($nextjsMethod, $readMethods) && in_array($phpMethod, $readMethods)) {
            return 0.8;
        }

        if (in_array($nextjsMethod, $writeMethods) && in_array($phpMethod, $writeMethods)) {
            return 0.8;
        }

        if (in_array($nextjsMethod, $deleteMethods) && in_array($phpMethod, $deleteMethods)) {
            return 0.8;
        }

        return 0.0;
    }

    /**
     * Calculate path/action name similarity.
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoint PHP endpoint
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculatePathSimilarity(array $nextjsEndpoint, array $phpEndpoint): float
    {
        $nextjsPath = strtolower($nextjsEndpoint['path'] ?? '');
        $phpAction = strtolower($phpEndpoint['action'] ?? '');

        // Extract meaningful words from path and action
        $nextjsWords = $this->extractWords($nextjsPath);
        $phpWords = $this->extractWords($phpAction);

        if (empty($nextjsWords) && empty($phpWords)) {
            return 0.0;
        }

        if (empty($nextjsWords) || empty($phpWords)) {
            return 0.0;
        }

        // Calculate word overlap
        $intersection = array_intersect($nextjsWords, $phpWords);
        $union = array_unique(array_merge($nextjsWords, $phpWords));

        return count($union) > 0 ? count($intersection) / count($union) : 0.0;
    }

    /**
     * Calculate authentication similarity.
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoint PHP endpoint
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateAuthSimilarity(array $nextjsEndpoint, array $phpEndpoint): float
    {
        $nextjsAuth = $nextjsEndpoint['authentication'] ?? false;
        $phpAuth = $phpEndpoint['authentication'] ?? false;

        return $nextjsAuth === $phpAuth ? 1.0 : 0.0;
    }

    /**
     * Calculate LINE account filtering similarity.
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoint PHP endpoint
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateLineFilteringSimilarity(array $nextjsEndpoint, array $phpEndpoint): float
    {
        $nextjsLine = $nextjsEndpoint['lineAccountFiltering'] ?? false;
        $phpLine = $phpEndpoint['lineAccountFiltering'] ?? false;

        return $nextjsLine === $phpLine ? 1.0 : 0.0;
    }

    /**
     * Extract meaningful words from a path or action name.
     * 
     * @param string $text Text to extract words from
     * @return array Array of words
     */
    private function extractWords(string $text): array
    {
        // Remove common prefixes and suffixes
        $text = preg_replace('#^/api/inbox/?#', '', $text);
        $text = preg_replace('#^(get|set|create|update|delete|fetch|list)_?#i', '', $text);
        
        // Split by non-alphanumeric characters
        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter out common words
        $stopWords = ['api', 'inbox', 'v2', 'the', 'a', 'an', 'and', 'or', 'but'];
        $words = array_filter($words, fn($w) => !in_array($w, $stopWords));
        
        return array_values($words);
    }

    /**
     * Get matching factors between two endpoints.
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoint PHP endpoint
     * @return array Array of matching factors
     */
    private function getMatchingFactors(array $nextjsEndpoint, array $phpEndpoint): array
    {
        $factors = [];

        // Check each similarity factor
        if ($this->calculateTableSimilarity($nextjsEndpoint, $phpEndpoint) > 0) {
            $factors[] = 'shared_database_tables';
        }

        if ($this->calculateOperationSimilarity($nextjsEndpoint, $phpEndpoint) >= 0.8) {
            $factors[] = 'same_http_method';
        }

        if ($this->calculatePathSimilarity($nextjsEndpoint, $phpEndpoint) > 0) {
            $factors[] = 'similar_naming';
        }

        if ($this->calculateAuthSimilarity($nextjsEndpoint, $phpEndpoint) === 1.0) {
            $factors[] = 'same_authentication';
        }

        if ($this->calculateLineFilteringSimilarity($nextjsEndpoint, $phpEndpoint) === 1.0) {
            $factors[] = 'same_line_filtering';
        }

        return $factors;
    }

    /**
     * Get shared database tables between two endpoints.
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoint PHP endpoint
     * @return array Array of shared table names
     */
    private function getSharedTables(array $nextjsEndpoint, array $phpEndpoint): array
    {
        $nextjsTables = $nextjsEndpoint['databaseTables'] ?? [];
        $phpTables = $phpEndpoint['databaseTables'] ?? [];

        return array_values(array_intersect($nextjsTables, $phpTables));
    }

    /**
     * Determine the operation type based on HTTP method and context.
     * 
     * @param array $nextjsEndpoint Next.js endpoint
     * @param array $phpEndpoint PHP endpoint
     * @return string|null Operation type
     */
    private function determineOperationType(array $nextjsEndpoint, array $phpEndpoint): ?string
    {
        $nextjsMethod = strtoupper($nextjsEndpoint['method'] ?? '');
        $phpMethod = strtoupper($phpEndpoint['method'] ?? '');

        // Determine operation type based on HTTP method
        $operationMap = [
            'GET' => 'read',
            'POST' => 'create',
            'PUT' => 'update',
            'PATCH' => 'update',
            'DELETE' => 'delete',
        ];

        $nextjsOp = $operationMap[$nextjsMethod] ?? null;
        $phpOp = $operationMap[$phpMethod] ?? null;

        // If both have the same operation type, return it
        if ($nextjsOp === $phpOp && $nextjsOp !== null) {
            return $nextjsOp;
        }

        // If different, return both
        if ($nextjsOp !== null && $phpOp !== null) {
            return "{$nextjsOp}/{$phpOp}";
        }

        return $nextjsOp ?? $phpOp;
    }

    /**
     * Get similarity level from score.
     * 
     * @param float $score Similarity score
     * @return string Similarity level
     */
    private function getSimilarityLevel(float $score): string
    {
        if ($score >= 0.9) {
            return 'exact';
        } elseif ($score >= 0.7) {
            return 'high';
        } elseif ($score >= 0.4) {
            return 'medium';
        } elseif ($score >= 0.3) {
            return 'low';
        } else {
            return 'no_match';
        }
    }

    /**
     * Update match statistics based on similarity score.
     * 
     * @param float $score Similarity score
     * @return void
     */
    private function updateMatchStatistics(float $score): void
    {
        $level = $this->getSimilarityLevel($score);

        switch ($level) {
            case 'exact':
                $this->matchStatistics['exact_matches']++;
                break;
            case 'high':
                $this->matchStatistics['high_similarity_matches']++;
                break;
            case 'medium':
                $this->matchStatistics['medium_similarity_matches']++;
                break;
            case 'low':
                $this->matchStatistics['low_similarity_matches']++;
                break;
        }
    }

    /**
     * Check if an endpoint is in a list of endpoints.
     * 
     * @param array $endpoint Endpoint to check
     * @param array $list List of endpoints
     * @return bool True if endpoint is in list
     */
    private function isEndpointInList(array $endpoint, array $list): bool
    {
        foreach ($list as $item) {
            if ($this->areEndpointsEqual($endpoint, $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if two endpoints are equal.
     * 
     * @param array $endpoint1 First endpoint
     * @param array $endpoint2 Second endpoint
     * @return bool True if equal
     */
    private function areEndpointsEqual(array $endpoint1, array $endpoint2): bool
    {
        // For PHP endpoints, compare by file and action
        if (isset($endpoint1['file']) && isset($endpoint2['file'])) {
            return $endpoint1['file'] === $endpoint2['file'] &&
                   $endpoint1['action'] === $endpoint2['action'];
        }

        // For Next.js endpoints, compare by path and method
        if (isset($endpoint1['path']) && isset($endpoint2['path'])) {
            return $endpoint1['path'] === $endpoint2['path'] &&
                   $endpoint1['method'] === $endpoint2['method'];
        }

        return false;
    }

    /**
     * Get match statistics.
     * 
     * @return array Statistics
     */
    public function getMatchStatistics(): array
    {
        return $this->matchStatistics;
    }

    /**
     * Get all matches.
     * 
     * @return array Array of matches
     */
    public function getMatches(): array
    {
        return $this->matches;
    }
}
