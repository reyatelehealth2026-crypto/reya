<?php

namespace Tools\Audit\Core;

/**
 * Base class for audit report data structure.
 * 
 * This class represents the complete audit report with all analysis sections,
 * compatibility matrix, action items, test cases, and deployment guide.
 */
class AuditReport
{
    /** @var string Report version */
    private $version = '1.0.0';

    /** @var string Timestamp when report was generated */
    private $generatedAt;

    /** @var string Name of person/system that ran the audit */
    private $auditor;

    /** @var array System information (Next.js and PHP versions, paths) */
    private $systems = [];

    /** @var array Executive summary with key findings */
    private $executiveSummary = [];

    /** @var array All analysis sections */
    private $sections = [];

    /** @var array Compatibility matrix */
    private $compatibilityMatrix = [];

    /** @var array Prioritized action items */
    private $actionItems = [];

    /** @var array Generated test cases */
    private $testCases = [];

    /** @var array Deployment guide */
    private $deploymentGuide = [];

    /** @var array Appendices (glossary, references, code examples) */
    private $appendices = [];

    /**
     * Create a new audit report.
     * 
     * @param string $auditor Name of person/system running the audit
     */
    public function __construct(string $auditor = 'API Compatibility Audit Tool')
    {
        $this->auditor = $auditor;
        $this->generatedAt = date('c'); // ISO 8601 format
        $this->initializeStructure();
    }

    /**
     * Initialize the report structure with empty sections.
     */
    private function initializeStructure(): void
    {
        $this->executiveSummary = [
            'overallStatus' => 'unknown',
            'criticalIssues' => 0,
            'highPriorityIssues' => 0,
            'keyFindings' => [],
            'recommendations' => []
        ];

        $this->sections = [
            'endpointInventory' => [],
            'schemaCompatibility' => [],
            'authenticationAnalysis' => [],
            'webhookAnalysis' => [],
            'phpBridgeAnalysis' => [],
            'conflictReport' => [],
            'performanceAnalysis' => [],
            'securityAudit' => []
        ];

        $this->compatibilityMatrix = [
            'version' => $this->version,
            'generatedAt' => $this->generatedAt,
            'systems' => [],
            'endpoints' => [],
            'database' => ['tables' => []],
            'authentication' => [],
            'webhooks' => [],
            'summary' => []
        ];

        $this->deploymentGuide = [
            'prerequisites' => [],
            'environmentVariables' => [],
            'deploymentSteps' => [],
            'rollbackProcedure' => [],
            'monitoring' => []
        ];

        $this->appendices = [
            'glossary' => [],
            'references' => [],
            'codeExamples' => []
        ];
    }

    /**
     * Set system information.
     * 
     * @param array $systems System information
     */
    public function setSystems(array $systems): void
    {
        $this->systems = $systems;
        $this->compatibilityMatrix['systems'] = $systems;
    }

    /**
     * Add an analysis section.
     * 
     * @param string $sectionName Section name (e.g., 'endpointInventory')
     * @param array $data Section data
     */
    public function addSection(string $sectionName, array $data): void
    {
        $this->sections[$sectionName] = $data;
    }

    /**
     * Set the compatibility matrix.
     * 
     * @param array $matrix Compatibility matrix data
     */
    public function setCompatibilityMatrix(array $matrix): void
    {
        $this->compatibilityMatrix = array_merge($this->compatibilityMatrix, $matrix);
    }

    /**
     * Add an action item.
     * 
     * @param array $actionItem Action item data
     */
    public function addActionItem(array $actionItem): void
    {
        $this->actionItems[] = $actionItem;
    }

    /**
     * Add a test case.
     * 
     * @param array $testCase Test case data
     */
    public function addTestCase(array $testCase): void
    {
        $this->testCases[] = $testCase;
    }

    /**
     * Set the executive summary.
     * 
     * @param array $summary Executive summary data
     */
    public function setExecutiveSummary(array $summary): void
    {
        $this->executiveSummary = array_merge($this->executiveSummary, $summary);
    }

    /**
     * Set the deployment guide.
     * 
     * @param array $guide Deployment guide data
     */
    public function setDeploymentGuide(array $guide): void
    {
        $this->deploymentGuide = array_merge($this->deploymentGuide, $guide);
    }

    /**
     * Add an appendix item.
     * 
     * @param string $type Appendix type ('glossary', 'references', 'codeExamples')
     * @param mixed $item Item to add
     */
    public function addAppendix(string $type, $item): void
    {
        if (isset($this->appendices[$type])) {
            if ($type === 'glossary') {
                $this->appendices[$type] = array_merge($this->appendices[$type], $item);
            } else {
                $this->appendices[$type][] = $item;
            }
        }
    }

    /**
     * Get the complete report as an array.
     * 
     * @return array Complete report data
     */
    public function toArray(): array
    {
        return [
            'metadata' => [
                'version' => $this->version,
                'generatedAt' => $this->generatedAt,
                'auditor' => $this->auditor,
                'systems' => $this->systems
            ],
            'executiveSummary' => $this->executiveSummary,
            'sections' => $this->sections,
            'compatibilityMatrix' => $this->compatibilityMatrix,
            'actionItems' => $this->actionItems,
            'testCases' => $this->testCases,
            'deploymentGuide' => $this->deploymentGuide,
            'appendices' => $this->appendices
        ];
    }

    /**
     * Get a specific section.
     * 
     * @param string $sectionName Section name
     * @return array|null Section data or null if not found
     */
    public function getSection(string $sectionName): ?array
    {
        return $this->sections[$sectionName] ?? null;
    }

    /**
     * Get the executive summary.
     * 
     * @return array Executive summary
     */
    public function getExecutiveSummary(): array
    {
        return $this->executiveSummary;
    }

    /**
     * Get the compatibility matrix.
     * 
     * @return array Compatibility matrix
     */
    public function getCompatibilityMatrix(): array
    {
        return $this->compatibilityMatrix;
    }

    /**
     * Get all action items.
     * 
     * @return array Action items
     */
    public function getActionItems(): array
    {
        return $this->actionItems;
    }

    /**
     * Get all test cases.
     * 
     * @return array Test cases
     */
    public function getTestCases(): array
    {
        return $this->testCases;
    }

    /**
     * Get the deployment guide.
     * 
     * @return array Deployment guide
     */
    public function getDeploymentGuide(): array
    {
        return $this->deploymentGuide;
    }

    /**
     * Calculate and update the executive summary based on current data.
     */
    public function calculateExecutiveSummary(): void
    {
        $criticalIssues = 0;
        $highPriorityIssues = 0;
        $keyFindings = [];

        // Count issues from conflict report
        if (isset($this->sections['conflictReport']['conflicts'])) {
            foreach ($this->sections['conflictReport']['conflicts'] as $conflict) {
                if ($conflict['severity'] === 'critical') {
                    $criticalIssues++;
                } elseif ($conflict['severity'] === 'high') {
                    $highPriorityIssues++;
                }
            }
        }

        // Count issues from security audit
        if (isset($this->sections['securityAudit']['vulnerabilities'])) {
            foreach ($this->sections['securityAudit']['vulnerabilities'] as $vuln) {
                if ($vuln['severity'] === 'critical') {
                    $criticalIssues++;
                } elseif ($vuln['severity'] === 'high') {
                    $highPriorityIssues++;
                }
            }
        }

        // Determine overall status
        $overallStatus = 'compatible';
        if ($criticalIssues > 0) {
            $overallStatus = 'incompatible';
        } elseif ($highPriorityIssues > 5) {
            $overallStatus = 'needs_work';
        }

        // Extract key findings
        if (isset($this->compatibilityMatrix['summary']['overallCompatibility'])) {
            $keyFindings[] = "Overall compatibility: " . $this->compatibilityMatrix['summary']['overallCompatibility'];
        }
        if ($criticalIssues > 0) {
            $keyFindings[] = "$criticalIssues critical issues require immediate attention";
        }
        if ($highPriorityIssues > 0) {
            $keyFindings[] = "$highPriorityIssues high-priority issues should be addressed soon";
        }

        $this->executiveSummary = [
            'overallStatus' => $overallStatus,
            'criticalIssues' => $criticalIssues,
            'highPriorityIssues' => $highPriorityIssues,
            'keyFindings' => $keyFindings,
            'recommendations' => $this->executiveSummary['recommendations'] ?? []
        ];
    }

    /**
     * Validate that the report has all required sections.
     * 
     * @return bool True if report is valid
     */
    public function validate(): bool
    {
        $requiredSections = [
            'endpointInventory',
            'schemaCompatibility',
            'authenticationAnalysis',
            'webhookAnalysis',
            'phpBridgeAnalysis',
            'conflictReport',
            'performanceAnalysis',
            'securityAudit'
        ];

        foreach ($requiredSections as $section) {
            if (empty($this->sections[$section])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get validation errors.
     * 
     * @return array Array of missing sections
     */
    public function getValidationErrors(): array
    {
        $errors = [];
        $requiredSections = [
            'endpointInventory',
            'schemaCompatibility',
            'authenticationAnalysis',
            'webhookAnalysis',
            'phpBridgeAnalysis',
            'conflictReport',
            'performanceAnalysis',
            'securityAudit'
        ];

        foreach ($requiredSections as $section) {
            if (empty($this->sections[$section])) {
                $errors[] = "Missing required section: $section";
            }
        }

        return $errors;
    }
}
