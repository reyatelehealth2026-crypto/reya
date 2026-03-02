<?php

namespace Tools\Audit\Interfaces;

/**
 * Interface for report generators that produce audit reports in various formats.
 * 
 * Report generators take analysis results and produce human-readable or
 * machine-readable reports in formats like JSON, HTML, Markdown, or CSV.
 */
interface ReportGeneratorInterface
{
    /**
     * Generate a report from audit data.
     * 
     * @param array $auditData Complete audit data from all analyzers
     * @return string Generated report content
     * @throws \Exception If report generation fails
     */
    public function generate(array $auditData): string;

    /**
     * Get the format this generator produces.
     * 
     * @return string Format identifier (e.g., "json", "html", "markdown", "csv")
     */
    public function getFormat(): string;

    /**
     * Get the recommended file extension for this format.
     * 
     * @return string File extension without dot (e.g., "json", "html", "md")
     */
    public function getFileExtension(): string;

    /**
     * Get the MIME type for this format.
     * 
     * @return string MIME type (e.g., "application/json", "text/html")
     */
    public function getMimeType(): string;

    /**
     * Validate that the generator can produce output with the given data.
     * 
     * @param array $auditData Audit data to validate
     * @return bool True if data is valid for this generator
     */
    public function validateData(array $auditData): bool;

    /**
     * Get validation errors if validateData() returns false.
     * 
     * @return array Array of error messages
     */
    public function getValidationErrors(): array;
}
