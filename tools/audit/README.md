# API Compatibility Audit Tool

A comprehensive analysis tool that examines the interoperability between the Next.js Inbox system and the PHP Backend system. This audit produces detailed documentation covering API endpoints, database schemas, authentication mechanisms, webhook handling, and provides actionable recommendations for ensuring both systems can coexist safely.

## Overview

The API Compatibility Audit Tool analyzes:

- **API Endpoints**: Catalogs all endpoints in both systems and identifies overlaps
- **Database Schema**: Compares Prisma models with MySQL tables for compatibility
- **Authentication**: Analyzes session mechanisms and cross-system authentication
- **Webhooks**: Examines LINE webhook handlers and identifies conflicts
- **PHP Bridge**: Validates Next.js to PHP API integration
- **Conflicts**: Detects all types of conflicts between systems
- **Performance**: Identifies bottlenecks and optimization opportunities
- **Security**: Audits for common vulnerabilities

## Directory Structure

```
tools/audit/
├── README.md                       # This file
├── config.php                      # Configuration file
├── audit.php                       # CLI entry point (to be created)
├── interfaces/                     # Core interfaces
│   ├── AnalyzerInterface.php      # Base analyzer interface
│   ├── ReportGeneratorInterface.php # Report generator interface
│   └── ScannerInterface.php       # Scanner interface
├── core/                          # Core classes
│   └── AuditReport.php            # Base report data structure
├── analyzers/                     # Analysis components (to be created)
│   ├── EndpointInventoryScanner.php
│   ├── SchemaCompatibilityAnalyzer.php
│   ├── AuthenticationAnalyzer.php
│   ├── WebhookAnalyzer.php
│   ├── PhpBridgeValidator.php
│   ├── ConflictDetector.php
│   ├── PerformanceAnalyzer.php
│   └── SecurityAuditor.php
├── generators/                    # Report generators (to be created)
│   ├── JsonReportGenerator.php
│   ├── HtmlReportGenerator.php
│   └── MarkdownReportGenerator.php
├── reports/                       # Generated reports (output directory)
├── cache/                         # Analysis cache
└── logs/                          # Audit logs
```

## Installation

1. Ensure PHP 7.4+ is installed
2. Install dependencies (if any):
   ```bash
   composer install
   ```

3. Copy and configure the config file:
   ```bash
   cp config.php config.local.php
   # Edit config.local.php with your paths and settings
   ```

## Configuration

Edit `config.local.php` to set:

- **Paths**: Locations of Next.js and PHP codebases
- **Database**: Connection details or schema file paths
- **Analysis Options**: Which analyses to run
- **Report Format**: Output format preferences
- **Severity Thresholds**: Conflict severity criteria

Key configuration sections:

```php
'paths' => [
    'nextjs' => '/path/to/inboxreya/inbox',
    'php' => '/path/to/re-ya',
    'output' => __DIR__ . '/reports',
],

'database' => [
    'host' => 'localhost',
    'database' => 'telepharmacy',
    // ... or use schema files
],

'analysis' => [
    'endpoint_inventory' => true,
    'schema_compatibility' => true,
    // ... enable/disable specific analyses
],
```

## Usage

### Run Full Audit

```bash
php audit.php --config=config.local.php
```

### Run Specific Analysis

```bash
# Endpoint inventory only
php audit.php --analysis=endpoints

# Schema compatibility only
php audit.php --analysis=schema

# Security audit only
php audit.php --analysis=security
```

### Specify Output Format

```bash
# JSON format (default)
php audit.php --format=json --output=audit-report.json

# HTML format
php audit.php --format=html --output=audit-report.html

# Markdown format
php audit.php --format=markdown --output=audit-report.md

# All formats
php audit.php --format=all
```

### Advanced Options

```bash
# Specify custom paths
php audit.php --nextjs-path=./inboxreya/inbox --php-path=./re-ya

# Enable verbose logging
php audit.php --verbose

# Use cached results
php audit.php --use-cache

# Clear cache before running
php audit.php --clear-cache
```

## Output Formats

### JSON Format
Machine-readable format suitable for:
- Further processing by other tools
- Integration with CI/CD pipelines
- Programmatic analysis

### HTML Format
Human-readable format with:
- Interactive collapsible sections
- Charts and visualizations
- Syntax-highlighted code examples
- Executive summary dashboard

### Markdown Format
Documentation format suitable for:
- Version control (Git)
- Documentation sites
- Code review comments
- Team collaboration

## Report Sections

Each audit report includes:

1. **Executive Summary**
   - Overall compatibility status
   - Critical and high-priority issue counts
   - Key findings and recommendations

2. **Endpoint Inventory**
   - Complete catalog of Next.js and PHP endpoints
   - Request/response specifications
   - Authentication requirements

3. **Schema Compatibility**
   - Table and column mappings
   - Data type compatibility
   - Index and foreign key analysis

4. **Authentication Analysis**
   - Session mechanism comparison
   - Cross-system authentication validation
   - Security concerns

5. **Webhook Analysis**
   - Handler comparison
   - Conflict detection
   - Recommendation for primary handler

6. **PHP Bridge Analysis**
   - Next.js to PHP API calls
   - Configuration validation
   - Error handling assessment

7. **Conflict Report**
   - All detected conflicts
   - Severity assessment
   - Resolution strategies

8. **Performance Analysis**
   - N+1 query detection
   - Missing indexes
   - Optimization opportunities

9. **Security Audit**
   - Vulnerability detection
   - Risk assessment
   - Remediation steps

10. **Compatibility Matrix**
    - Endpoint mappings
    - Authoritative system recommendations
    - Shared resource identification

11. **Action Items**
    - Prioritized task list
    - Effort estimates
    - Implementation steps

12. **Test Cases**
    - Integration test scenarios
    - Expected results
    - Test execution guide

13. **Deployment Guide**
    - Prerequisites
    - Environment variables
    - Deployment steps
    - Rollback procedures

## Continuous Integration

### GitHub Actions Example

```yaml
name: API Compatibility Audit

on:
  pull_request:
    paths:
      - 'inboxreya/inbox/src/app/api/**'
      - 'api/inbox*.php'

jobs:
  audit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run Audit
        run: php tools/audit/audit.php --format=json --output=audit.json
      - name: Check for Critical Issues
        run: |
          CRITICAL=$(jq '.executiveSummary.criticalIssues' audit.json)
          if [ "$CRITICAL" -gt 0 ]; then
            echo "Found $CRITICAL critical issues"
            exit 1
          fi
```

## Development

### Adding a New Analyzer

1. Create a class implementing `AnalyzerInterface`:

```php
<?php
namespace Tools\Audit\Analyzers;

use Tools\Audit\Interfaces\AnalyzerInterface;

class MyAnalyzer implements AnalyzerInterface
{
    public function analyze(): array
    {
        // Your analysis logic
        return $results;
    }
    
    public function getName(): string
    {
        return 'MyAnalyzer';
    }
    
    // ... implement other interface methods
}
```

2. Register the analyzer in the orchestrator
3. Add configuration options in `config.php`
4. Write tests for the analyzer

### Adding a New Report Format

1. Create a class implementing `ReportGeneratorInterface`:

```php
<?php
namespace Tools\Audit\Generators;

use Tools\Audit\Interfaces\ReportGeneratorInterface;

class MyFormatGenerator implements ReportGeneratorInterface
{
    public function generate(array $auditData): string
    {
        // Generate report in your format
        return $reportContent;
    }
    
    public function getFormat(): string
    {
        return 'myformat';
    }
    
    // ... implement other interface methods
}
```

2. Register the generator in the report factory
3. Add format-specific configuration options

## Testing

Run the test suite:

```bash
# All tests
composer test

# Specific test suite
./vendor/bin/phpunit tests/ApiCompatibilityAudit/

# With coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## Troubleshooting

### Common Issues

**Issue**: "Cannot access Next.js codebase"
- **Solution**: Check that the `paths.nextjs` configuration points to the correct directory
- **Solution**: Ensure read permissions on the directory

**Issue**: "Database connection failed"
- **Solution**: Verify database credentials in configuration
- **Solution**: Use schema files instead: set `database.use_schema_files = true`

**Issue**: "Parser error in file X"
- **Solution**: Check file syntax is valid
- **Solution**: File will be skipped, audit continues with available data

**Issue**: "Out of memory"
- **Solution**: Increase PHP memory limit: `php -d memory_limit=512M audit.php`
- **Solution**: Reduce `performance.max_files_per_scan` in configuration

### Debug Mode

Enable verbose logging:

```bash
php audit.php --verbose --log-level=debug
```

Check logs:

```bash
tail -f tools/audit/logs/audit.log
```

## Requirements

- PHP 7.4 or higher
- MySQL 5.7+ / MariaDB 10.2+ (if using database connection)
- Read access to both codebases
- Sufficient disk space for reports and cache

## License

Part of the LINE Telepharmacy Platform project.

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the logs in `tools/audit/logs/`
3. Consult the design document: `.kiro/specs/api-compatibility-audit/design.md`
4. Contact the development team

## Version History

- **1.0.0** (Current): Initial release with core analysis capabilities
  - Endpoint inventory scanning
  - Schema compatibility analysis
  - Authentication flow mapping
  - Webhook handler comparison
  - PHP bridge validation
  - Conflict detection
  - Performance analysis
  - Security auditing
  - Multiple report formats (JSON, HTML, Markdown)
