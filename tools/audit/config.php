<?php

/**
 * Configuration file for API Compatibility Audit Tool
 * 
 * This file contains all configuration parameters for running the audit.
 * Copy this file to config.local.php and customize for your environment.
 */

return [
    /**
     * Paths Configuration
     */
    'paths' => [
        // Path to Next.js Inbox codebase
        'nextjs' => __DIR__ . '/../../inboxreya/inbox',
        
        // Path to PHP Backend codebase
        'php' => __DIR__ . '/../../',
        
        // Output directory for audit reports
        'output' => __DIR__ . '/reports',
    ],

    /**
     * Database Configuration
     */
    'database' => [
        // Database connection for schema extraction
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'telepharmacy',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        
        // Alternative: Use schema files instead of live database
        'use_schema_files' => false,
        'schema_files' => [
            'mysql' => __DIR__ . '/../../database/schema_complete.sql',
            'prisma' => __DIR__ . '/../../inboxreya/inbox/prisma/schema.prisma',
        ],
    ],

    /**
     * Analysis Configuration
     */
    'analysis' => [
        // Which analyses to run (set to false to skip)
        'endpoint_inventory' => true,
        'schema_compatibility' => true,
        'authentication_analysis' => true,
        'webhook_analysis' => true,
        'php_bridge_analysis' => true,
        'conflict_detection' => true,
        'performance_analysis' => true,
        'security_audit' => true,
        
        // Endpoint scanning configuration
        'endpoints' => [
            'nextjs_api_path' => 'src/app/api/inbox',
            'php_api_files' => [
                'api/inbox.php',
                'api/inbox-v2.php',
            ],
        ],
        
        // Schema analysis configuration
        'schema' => [
            'prisma_schema_path' => 'prisma/schema.prisma',
            'check_indexes' => true,
            'check_foreign_keys' => true,
            'check_data_types' => true,
        ],
        
        // Authentication analysis configuration
        'authentication' => [
            'nextauth_config_path' => 'src/auth.ts',
            'php_session_files' => [
                'includes/auth_check.php',
                'api/inbox.php',
            ],
        ],
        
        // Webhook analysis configuration
        'webhooks' => [
            'nextjs_webhook_path' => 'src/app/api/webhook',
            'php_webhook_path' => 'webhook.php',
        ],
        
        // Performance analysis configuration
        'performance' => [
            'detect_n_plus_one' => true,
            'detect_missing_indexes' => true,
            'detect_redundant_calls' => true,
            'analyze_caching' => true,
        ],
        
        // Security audit configuration
        'security' => [
            'check_sql_injection' => true,
            'check_xss' => true,
            'check_csrf' => true,
            'check_auth_bypass' => true,
            'check_data_exposure' => true,
            'check_idor' => true,
        ],
    ],

    /**
     * Report Generation Configuration
     */
    'reporting' => [
        // Default output format ('json', 'html', 'markdown', 'all')
        'default_format' => 'json',
        
        // Report file naming
        'filename_prefix' => 'audit-report',
        'include_timestamp' => true,
        
        // Report content options
        'include_code_examples' => true,
        'include_test_cases' => true,
        'include_deployment_guide' => true,
        
        // HTML report options
        'html' => [
            'include_charts' => true,
            'collapsible_sections' => true,
            'syntax_highlighting' => true,
        ],
        
        // Markdown report options
        'markdown' => [
            'table_of_contents' => true,
            'code_blocks' => true,
        ],
    ],

    /**
     * Severity Thresholds
     */
    'severity' => [
        // Conflict severity thresholds
        'critical' => [
            'data_corruption_risk' => true,
            'security_vulnerability' => true,
            'authentication_bypass' => true,
        ],
        'high' => [
            'race_condition' => true,
            'data_inconsistency' => true,
            'performance_degradation' => true,
        ],
        'medium' => [
            'duplicate_functionality' => true,
            'missing_validation' => true,
        ],
        'low' => [
            'code_style' => true,
            'documentation' => true,
        ],
    ],

    /**
     * Recommendation Configuration
     */
    'recommendations' => [
        // Prioritization weights (0-1)
        'weights' => [
            'severity' => 0.4,
            'impact' => 0.3,
            'effort' => 0.2,
            'dependencies' => 0.1,
        ],
        
        // Effort estimation
        'effort_levels' => [
            'low' => '< 1 day',
            'medium' => '1-3 days',
            'high' => '> 3 days',
        ],
    ],

    /**
     * Testing Configuration
     */
    'testing' => [
        // Test case generation
        'generate_integration_tests' => true,
        'generate_unit_tests' => false,
        'test_framework' => 'phpunit',
        
        // Property-based testing
        'property_tests' => [
            'enabled' => true,
            'iterations' => 100,
        ],
    ],

    /**
     * Logging Configuration
     */
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'file' => __DIR__ . '/logs/audit.log',
        'console' => true,
    ],

    /**
     * Performance Configuration
     */
    'performance' => [
        // Maximum files to scan per directory
        'max_files_per_scan' => 1000,
        
        // Maximum file size to parse (in bytes)
        'max_file_size' => 1048576, // 1MB
        
        // Timeout for individual analysis (in seconds)
        'analysis_timeout' => 300, // 5 minutes
        
        // Enable caching of analysis results
        'cache_enabled' => true,
        'cache_dir' => __DIR__ . '/cache',
    ],

    /**
     * Auditor Information
     */
    'auditor' => [
        'name' => 'API Compatibility Audit Tool',
        'version' => '1.0.0',
        'contact' => '',
    ],
];
