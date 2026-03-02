<?php

namespace Tools\Audit\Interfaces;

/**
 * Interface for scanner components that discover and catalog code elements.
 * 
 * Scanners are specialized analyzers that focus on discovering specific
 * elements in the codebase (endpoints, database queries, API calls, etc.).
 */
interface ScannerInterface extends AnalyzerInterface
{
    /**
     * Scan the specified path and return discovered elements.
     * 
     * @param string $path Path to scan (file or directory)
     * @return array Array of discovered elements
     * @throws \Exception If scanning fails
     */
    public function scan(string $path): array;

    /**
     * Get the file patterns this scanner looks for.
     * 
     * @return array Array of glob patterns (e.g., ["*.php", "route.ts"])
     */
    public function getFilePatterns(): array;

    /**
     * Check if this scanner can handle the given file.
     * 
     * @param string $filePath Path to file
     * @return bool True if scanner can process this file
     */
    public function canScanFile(string $filePath): bool;

    /**
     * Scan a single file and return discovered elements.
     * 
     * @param string $filePath Path to file
     * @return array Array of discovered elements from this file
     * @throws \Exception If file scanning fails
     */
    public function scanFile(string $filePath): array;

    /**
     * Get statistics about the last scan operation.
     * 
     * @return array Statistics (files_scanned, elements_found, errors, etc.)
     */
    public function getScanStatistics(): array;
}
