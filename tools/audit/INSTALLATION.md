# Installation Instructions

## Prerequisites

- PHP 7.4 or higher
- Composer (for autoloading)
- Access to both Next.js and PHP codebases
- MySQL database access (optional, can use schema files)

## Setup Steps

### 1. Register Autoloading

After creating the audit tool structure, you need to regenerate the Composer autoload files:

```bash
cd re-ya
composer dump-autoload
```

This will register the `Tools\` namespace and make all audit tool classes available.

### 2. Verify Installation

Create a test file to verify the installation:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Tools\Audit\Core\AuditReport;
use Tools\Audit\Interfaces\AnalyzerInterface;

// Test that classes can be loaded
$report = new AuditReport('Test Auditor');
echo "✓ AuditReport class loaded successfully\n";

// Test interface exists
if (interface_exists('Tools\Audit\Interfaces\AnalyzerInterface')) {
    echo "✓ AnalyzerInterface loaded successfully\n";
}

echo "\nAudit tool installation verified!\n";
```

Save this as `re-ya/tools/audit/verify-installation.php` and run:

```bash
php tools/audit/verify-installation.php
```

Expected output:
```
✓ AuditReport class loaded successfully
✓ AnalyzerInterface loaded successfully

Audit tool installation verified!
```

### 3. Configure the Audit Tool

Copy the configuration file and customize it:

```bash
cd tools/audit
cp config.php config.local.php
```

Edit `config.local.php` and set:

- **Paths**: Update paths to your Next.js and PHP codebases
- **Database**: Set database credentials or schema file paths
- **Analysis Options**: Enable/disable specific analyses
- **Report Format**: Choose output format preferences

Example configuration:

```php
return [
    'paths' => [
        'nextjs' => __DIR__ . '/../../inboxreya/inbox',
        'php' => __DIR__ . '/../../',
        'output' => __DIR__ . '/reports',
    ],
    
    'database' => [
        'host' => 'localhost',
        'database' => 'telepharmacy',
        'username' => 'root',
        'password' => 'your_password',
        // Or use schema files:
        'use_schema_files' => true,
        'schema_files' => [
            'mysql' => __DIR__ . '/../../database/schema_complete.sql',
            'prisma' => __DIR__ . '/../../inboxreya/inbox/prisma/schema.prisma',
        ],
    ],
];
```

### 4. Create Output Directories

Ensure the output directories have write permissions:

```bash
chmod 755 tools/audit/reports
chmod 755 tools/audit/cache
chmod 755 tools/audit/logs
```

On Windows, ensure the directories are not read-only.

### 5. Test Configuration

Once configured, you can test the configuration (when the CLI tool is implemented):

```bash
php tools/audit/audit.php --check-config
```

## Directory Structure

After installation, your directory structure should look like:

```
re-ya/tools/audit/
├── README.md                       # Main documentation
├── INSTALLATION.md                 # This file
├── config.php                      # Default configuration
├── config.local.php               # Your local configuration (gitignored)
├── audit.php                       # CLI entry point (to be created)
├── verify-installation.php        # Installation verification script
├── interfaces/                     # Core interfaces
│   ├── AnalyzerInterface.php
│   ├── ReportGeneratorInterface.php
│   └── ScannerInterface.php
├── core/                          # Core classes
│   └── AuditReport.php
├── analyzers/                     # Analysis components (to be created)
├── generators/                    # Report generators (to be created)
├── reports/                       # Generated reports (output)
├── cache/                         # Analysis cache
└── logs/                          # Audit logs
```

## Troubleshooting

### "Class not found" errors

**Problem**: PHP cannot find the audit tool classes.

**Solution**: Run `composer dump-autoload` in the `re-ya` directory.

### "Permission denied" errors

**Problem**: Cannot write to reports, cache, or logs directories.

**Solution**: 
- Linux/Mac: `chmod 755 tools/audit/{reports,cache,logs}`
- Windows: Remove read-only attribute from directories

### "Cannot access codebase" errors

**Problem**: Audit tool cannot read source files.

**Solution**: 
- Verify paths in `config.local.php` are correct
- Ensure read permissions on source directories
- Use absolute paths if relative paths don't work

## Next Steps

After installation:

1. Review the [README.md](README.md) for usage instructions
2. Review the [design document](.kiro/specs/api-compatibility-audit/design.md)
3. Wait for implementation of analyzer components (Tasks 2-17)
4. Run your first audit when the tool is complete

## Development

If you're developing the audit tool:

1. Follow PSR-4 autoloading conventions
2. Place new analyzers in `analyzers/` directory
3. Place new generators in `generators/` directory
4. Implement the appropriate interface for each component
5. Write tests in `re-ya/tests/ApiCompatibilityAudit/`
6. Run tests: `./vendor/bin/phpunit tests/ApiCompatibilityAudit/`

## Support

For issues or questions:
- Check the troubleshooting section above
- Review the logs in `tools/audit/logs/`
- Consult the design document
- Contact the development team
