<?php

namespace Tools\Audit\Interfaces;

/**
 * Interface for all analyzer components in the audit system.
 * 
 * Analyzers are responsible for examining specific aspects of the codebase
 * (endpoints, schemas, authentication, etc.) and producing analysis results.
 */
interface AnalyzerInterface
{
    /**
     * Run the analysis and return results.
     * 
     * @return array Analysis results in a structured format
     * @throws \Exception If analysis fails critically
     */
    public function analyze(): array;

    /**
     * Get the name of this analyzer for reporting purposes.
     * 
     * @return string Analyzer name (e.g., "EndpointInventoryScanner")
     */
    public function getName(): string;

    /**
     * Get the version of this analyzer.
     * 
     * @return string Version string (e.g., "1.0.0")
     */
    public function getVersion(): string;

    /**
     * Validate that the analyzer has all required dependencies and configuration.
     * 
     * @return bool True if analyzer is ready to run
     */
    public function validate(): bool;

    /**
     * Get validation errors if validate() returns false.
     * 
     * @return array Array of error messages
     */
    public function getValidationErrors(): array;
}
