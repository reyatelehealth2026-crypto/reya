<?php
/**
 * Installation Verification Script
 * 
 * This script verifies that the audit tool is properly installed and configured.
 * Run this after setting up the audit tool to ensure everything is working.
 * 
 * Usage: php tools/audit/verify-installation.php
 */

// Check if autoloader exists
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "❌ ERROR: Composer autoloader not found at: $autoloadPath\n";
    echo "   Please run 'composer install' in the re-ya directory.\n";
    exit(1);
}

require_once $autoloadPath;

echo "=== API Compatibility Audit Tool - Installation Verification ===\n\n";

// Test 1: Check if core classes can be loaded
echo "Test 1: Loading core classes...\n";
try {
    $report = new \Tools\Audit\Core\AuditReport('Test Auditor');
    echo "  ✓ AuditReport class loaded successfully\n";
} catch (Exception $e) {
    echo "  ❌ Failed to load AuditReport: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check if interfaces exist
echo "\nTest 2: Checking interfaces...\n";
$interfaces = [
    'Tools\Audit\Interfaces\AnalyzerInterface',
    'Tools\Audit\Interfaces\ReportGeneratorInterface',
    'Tools\Audit\Interfaces\ScannerInterface',
];

foreach ($interfaces as $interface) {
    if (interface_exists($interface)) {
        $shortName = substr($interface, strrpos($interface, '\\') + 1);
        echo "  ✓ $shortName loaded successfully\n";
    } else {
        echo "  ❌ Interface not found: $interface\n";
        exit(1);
    }
}

// Test 3: Check directory structure
echo "\nTest 3: Checking directory structure...\n";
$requiredDirs = [
    'interfaces',
    'core',
    'analyzers',
    'generators',
    'reports',
    'cache',
    'logs',
];

foreach ($requiredDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        echo "  ✓ Directory exists: $dir/\n";
    } else {
        echo "  ❌ Directory missing: $dir/\n";
        exit(1);
    }
}

// Test 4: Check if directories are writable
echo "\nTest 4: Checking directory permissions...\n";
$writableDirs = ['reports', 'cache', 'logs'];

foreach ($writableDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_writable($path)) {
        echo "  ✓ Directory writable: $dir/\n";
    } else {
        echo "  ⚠️  Directory not writable: $dir/ (may cause issues)\n";
    }
}

// Test 5: Check configuration file
echo "\nTest 5: Checking configuration...\n";
$configPath = __DIR__ . '/config.php';
$localConfigPath = __DIR__ . '/config.local.php';

if (file_exists($configPath)) {
    echo "  ✓ Default config file exists: config.php\n";
} else {
    echo "  ❌ Default config file missing: config.php\n";
    exit(1);
}

if (file_exists($localConfigPath)) {
    echo "  ✓ Local config file exists: config.local.php\n";
    
    // Try to load the config
    try {
        $config = require $localConfigPath;
        if (is_array($config)) {
            echo "  ✓ Local config file is valid PHP array\n";
            
            // Check required config keys
            $requiredKeys = ['paths', 'database', 'analysis', 'reporting'];
            foreach ($requiredKeys as $key) {
                if (isset($config[$key])) {
                    echo "  ✓ Config section exists: $key\n";
                } else {
                    echo "  ⚠️  Config section missing: $key\n";
                }
            }
        } else {
            echo "  ⚠️  Local config file does not return an array\n";
        }
    } catch (Exception $e) {
        echo "  ⚠️  Error loading local config: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ⚠️  Local config file not found: config.local.php\n";
    echo "     Copy config.php to config.local.php and customize it.\n";
}

// Test 6: Test AuditReport functionality
echo "\nTest 6: Testing AuditReport functionality...\n";
try {
    $report = new \Tools\Audit\Core\AuditReport('Verification Test');
    
    // Test setting systems
    $report->setSystems([
        'nextjs' => ['version' => '15.0', 'path' => '/test/nextjs'],
        'php' => ['version' => '8.0', 'path' => '/test/php'],
    ]);
    echo "  ✓ setSystems() works\n";
    
    // Test adding a section
    $report->addSection('endpointInventory', ['test' => 'data']);
    echo "  ✓ addSection() works\n";
    
    // Test adding an action item
    $report->addActionItem([
        'priority' => 1,
        'severity' => 'high',
        'description' => 'Test action',
    ]);
    echo "  ✓ addActionItem() works\n";
    
    // Test toArray()
    $data = $report->toArray();
    if (is_array($data) && isset($data['metadata'])) {
        echo "  ✓ toArray() returns valid structure\n";
    } else {
        echo "  ❌ toArray() returned invalid structure\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "  ❌ AuditReport functionality test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Summary
echo "\n" . str_repeat("=", 65) . "\n";
echo "✅ Installation verification completed successfully!\n\n";
echo "Next steps:\n";
echo "1. Copy config.php to config.local.php and customize it\n";
echo "2. Wait for implementation of analyzer components (Tasks 2-17)\n";
echo "3. Run your first audit when the tool is complete\n";
echo "\nFor more information, see:\n";
echo "- README.md for usage instructions\n";
echo "- INSTALLATION.md for setup details\n";
echo "- .kiro/specs/api-compatibility-audit/ for design documents\n";
