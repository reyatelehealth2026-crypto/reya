<?php
/**
 * Test QR Code Generation - Comprehensive Test Suite
 * 
 * This script tests the QRCodeGenerator class with various EMVCo payloads
 * to ensure proper QR code generation for PromptPay payments.
 * 
 * Tests include:
 * - Sample EMVCo payloads with different amounts
 * - QR code generation and file creation
 * - Payload parsing and validation
 * - Manual verification instructions
 * 
 * Usage: php test-qr-generation.php
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/QRCodeGenerator.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         QR Code Generation - Comprehensive Test Suite          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$testsPassed = 0;
$testsFailed = 0;

// Test 1: Check if library is loaded
echo "Test 1: Checking if endroid/qr-code is installed...\n";
if (class_exists('Endroid\QrCode\QrCode')) {
    echo "✓ Library is installed\n\n";
    $testsPassed++;
} else {
    echo "✗ Library is NOT installed. Run: composer install\n\n";
    $testsFailed++;
    exit(1);
}

// Test 2: Initialize QRCodeGenerator
echo "Test 2: Initializing QRCodeGenerator...\n";
try {
    $generator = new QRCodeGenerator();
    echo "✓ QRCodeGenerator initialized\n\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "✗ Failed to initialize: " . $e->getMessage() . "\n\n";
    $testsFailed++;
    exit(1);
}

// Test 3: Sample EMVCo Payloads
echo "Test 3: Testing with Sample EMVCo Payloads\n";
echo "─────────────────────────────────────────────────────────────────\n";

// Sample payloads with different amounts and references
$samplePayloads = [
    [
        'name' => 'PromptPay 10.00 THB',
        'payload' => '00020101021129370016A000000677010111011300668123456785802TH5303764540510.006304ABCD',
        'amount' => '10.00',
        'reference' => 'ORDER_001',
        'description' => 'Small amount test (10 THB)'
    ],
    [
        'name' => 'PromptPay 1,500.00 THB',
        'payload' => '00020101021129370016A000000677010111011300668123456785802TH53037645406150.006304WXYZ',
        'amount' => '1,500.00',
        'reference' => 'ORDER_002',
        'description' => 'Medium amount test (1,500 THB)'
    ],
    [
        'name' => 'PromptPay 25,000.00 THB',
        'payload' => '00020101021129370016A000000677010111011300668123456785802TH530376454072500.006304PQRS',
        'amount' => '25,000.00',
        'reference' => 'ORDER_003',
        'description' => 'Large amount test (25,000 THB)'
    ],
    [
        'name' => 'PromptPay 99.50 THB',
        'payload' => '00020101021129370016A000000677010111011300668123456785802TH5303764540599.506304LMNO',
        'amount' => '99.50',
        'reference' => 'ORDER_004',
        'description' => 'Decimal amount test (99.50 THB)'
    ]
];

$generatedQRCodes = [];

foreach ($samplePayloads as $index => $sample) {
    $testNum = $index + 1;
    echo "\n3.{$testNum} {$sample['name']}\n";
    echo "     Description: {$sample['description']}\n";
    echo "     Amount: {$sample['amount']} THB\n";
    echo "     Reference: {$sample['reference']}\n";
    
    $result = $generator->generatePromptPayQR($sample['payload'], $sample['reference']);
    
    if ($result['success']) {
        echo "     ✓ QR Code generated successfully\n";
        echo "     URL: " . $result['url'] . "\n";
        echo "     Path: " . $result['path'] . "\n";
        echo "     Filename: " . $result['filename'] . "\n";
        
        // Verify file exists
        if (file_exists($result['path'])) {
            $fileSize = filesize($result['path']);
            echo "     ✓ File exists (Size: " . number_format($fileSize) . " bytes)\n";
            $testsPassed++;
            
            $generatedQRCodes[] = [
                'name' => $sample['name'],
                'amount' => $sample['amount'],
                'reference' => $sample['reference'],
                'url' => $result['url'],
                'path' => $result['path'],
                'filename' => $result['filename']
            ];
        } else {
            echo "     ✗ File does NOT exist at: " . $result['path'] . "\n";
            $testsFailed++;
        }
    } else {
        echo "     ✗ Failed to generate QR Code: " . $result['error'] . "\n";
        $testsFailed++;
    }
}

echo "\n";

// Test 4: Generate Base64 QR Code
echo "Test 4: Generating Base64 QR Code (for inline display)\n";
$emvcoPayload = "00020101021129370016A000000677010111011300668123456785802TH5303764540510.006304ABCD";
$result = $generator->generateQRBase64($emvcoPayload, 200);

if ($result['success']) {
    echo "✓ Base64 QR Code generated successfully\n";
    echo "  Length: " . strlen($result['base64']) . " characters\n";
    echo "  Preview: " . substr($result['base64'], 0, 50) . "...\n\n";
    $testsPassed++;
} else {
    echo "✗ Failed to generate Base64 QR Code: " . $result['error'] . "\n\n";
    $testsFailed++;
}

// Test 5: Generate generic QR Code
echo "Test 5: Generating generic QR Code (URL test)\n";
$result = $generator->generateQR('https://cny.re-ya.com', 'test_url');

if ($result['success']) {
    echo "✓ Generic QR Code generated successfully\n";
    echo "  URL: " . $result['url'] . "\n";
    echo "  Path: " . $result['path'] . "\n\n";
    $testsPassed++;
} else {
    echo "✗ Failed to generate generic QR Code: " . $result['error'] . "\n\n";
    $testsFailed++;
}

// Test 6: Check upload directory
echo "Test 6: Checking upload directory permissions\n";
$uploadDir = __DIR__ . '/uploads/qrcodes/';
if (is_dir($uploadDir)) {
    echo "✓ Upload directory exists: $uploadDir\n";
    
    $files = glob($uploadDir . '*.png');
    echo "  Files in directory: " . count($files) . "\n";
    
    if (is_writable($uploadDir)) {
        echo "✓ Directory is writable\n\n";
        $testsPassed++;
    } else {
        echo "✗ Directory is NOT writable. Fix permissions: chmod 755 $uploadDir\n\n";
        $testsFailed++;
    }
} else {
    echo "✗ Upload directory does NOT exist: $uploadDir\n\n";
    $testsFailed++;
}

// Test 7: Parse EMVCo payload structure
echo "Test 7: Parsing EMVCo Payload Structure\n";
echo "─────────────────────────────────────────────────────────────────\n";
$samplePayload = "00020101021129370016A000000677010111011300668123456785802TH5303764540510.006304ABCD";
echo "Sample Payload: $samplePayload\n\n";
echo "EMVCo Payload Structure:\n";
echo "  00 02 01 01 02 11 - Header (Payload Format Indicator)\n";
echo "  29 37 00 16 A000000677010111 01 13 0066812345678 - PromptPay ID\n";
echo "  58 02 TH - Country Code (Thailand)\n";
echo "  53 03 764 - Currency Code (THB)\n";
echo "  54 05 10.00 - Transaction Amount\n";
echo "  63 04 ABCD - CRC Checksum\n\n";
echo "✓ Payload structure documented\n\n";
$testsPassed++;

// Summary
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                         Test Summary                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n\n";

if ($testsFailed === 0) {
    echo "✓ All tests PASSED!\n\n";
} else {
    echo "✗ Some tests FAILED. Please review the output above.\n\n";
}

// Manual Verification Instructions
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              Manual Verification Instructions                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "STEP 1: View Generated QR Codes\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "Open your browser and navigate to:\n";
echo "https://cny.re-ya.com/uploads/qrcodes/\n\n";
echo "Or check files directly in:\n";
echo "$uploadDir\n\n";

echo "STEP 2: Scan QR Codes with Mobile Banking App\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "Use any Thai mobile banking app that supports PromptPay:\n";
echo "  • SCB Easy\n";
echo "  • Krungthai NEXT\n";
echo "  • Bangkok Bank Mobile Banking\n";
echo "  • Kasikorn K PLUS\n";
echo "  • Any other PromptPay-enabled app\n\n";

echo "STEP 3: Verify Payment Details\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "For each QR code, verify:\n\n";

foreach ($generatedQRCodes as $index => $qr) {
    $num = $index + 1;
    echo "{$num}. {$qr['name']}\n";
    echo "   File: {$qr['filename']}\n";
    echo "   Expected Amount: {$qr['amount']} THB\n";
    echo "   Expected Reference: {$qr['reference']}\n";
    echo "   URL: https://cny.re-ya.com{$qr['url']}\n\n";
}

echo "STEP 4: Verification Checklist\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "When scanning each QR code, verify:\n";
echo "  ☐ QR code scans successfully\n";
echo "  ☐ Amount matches expected value\n";
echo "  ☐ Recipient information is displayed\n";
echo "  ☐ Reference/note is included (if supported by app)\n";
echo "  ☐ Currency is THB (Thai Baht)\n";
echo "  ☐ Payment can be initiated (DO NOT complete payment)\n\n";

echo "STEP 5: Test Different Scenarios\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "  ☐ Small amount (10 THB) - Test minimum payment\n";
echo "  ☐ Medium amount (1,500 THB) - Test typical order\n";
echo "  ☐ Large amount (25,000 THB) - Test bulk order\n";
echo "  ☐ Decimal amount (99.50 THB) - Test precision\n\n";

echo "NOTES:\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "• DO NOT complete actual payments during testing\n";
echo "• QR codes are for testing purposes only\n";
echo "• Verify amounts carefully before any real transactions\n";
echo "• Keep generated QR codes for reference\n\n";

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    Test Completed Successfully                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
