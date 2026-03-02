<?php

/**
 * Test Script for Schema Compatibility Analyzer
 * 
 * Tests PrismaSchemaParser, MySqlSchemaExtractor, SchemaComparator, and IndexAnalyzer
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/bootstrap.php';

use Tools\Audit\Analyzers\PrismaSchemaParser;
use Tools\Audit\Analyzers\MySqlSchemaExtractor;
use Tools\Audit\Analyzers\SchemaComparator;
use Tools\Audit\Analyzers\IndexAnalyzer;
use Modules\Core\Database;

echo "=== Schema Compatibility Analyzer Test ===\n\n";

// Test 1: PrismaSchemaParser
echo "Test 1: PrismaSchemaParser\n";
echo str_repeat("-", 50) . "\n";

// Create a sample Prisma schema for testing
$sampleSchema = <<<PRISMA
model LineUser {
  id            Int       @id @default(autoincrement())
  lineUserId    String    @unique @map("line_user_id")
  displayName   String?   @map("display_name")
  pictureUrl    String?   @map("picture_url")
  statusMessage String?   @map("status_message")
  createdAt     DateTime  @default(now()) @map("created_at")
  updatedAt     DateTime  @updatedAt @map("updated_at")
  
  conversations Conversation[]
  messages      Message[]
  
  @@map("line_users")
  @@index([lineUserId])
}

model Conversation {
  id            Int       @id @default(autoincrement())
  lineAccountId Int       @map("line_account_id")
  lineUserId    Int       @map("line_user_id")
  status        String    @default("active")
  lastMessageAt DateTime? @map("last_message_at")
  createdAt     DateTime  @default(now()) @map("created_at")
  
  lineUser      LineUser  @relation(fields: [lineUserId], references: [id], onDelete: Cascade)
  messages      Message[]
  
  @@map("conversations")
  @@index([lineAccountId, status])
  @@index([lineUserId])
}

model Message {
  id             Int       @id @default(autoincrement())
  conversationId Int       @map("conversation_id")
  lineUserId     Int?      @map("line_user_id")
  messageType    String    @map("message_type")
  content        String    @db.Text
  sentAt         DateTime  @default(now()) @map("sent_at")
  
  conversation   Conversation @relation(fields: [conversationId], references: [id], onDelete: Cascade)
  lineUser       LineUser?    @relation(fields: [lineUserId], references: [id])
  
  @@map("messages")
  @@index([conversationId])
  @@index([lineUserId])
}

enum MessageType {
  TEXT
  IMAGE
  VIDEO
  AUDIO
  FILE
}
PRISMA;

// Write sample schema to temp file
$tempSchemaPath = __DIR__ . '/cache/test_schema.prisma';
if (!is_dir(__DIR__ . '/cache')) {
    mkdir(__DIR__ . '/cache', 0755, true);
}
file_put_contents($tempSchemaPath, $sampleSchema);

$prismaParser = new PrismaSchemaParser($tempSchemaPath);
$parseResult = $prismaParser->parse();

if ($parseResult['success']) {
    echo "✓ Prisma schema parsed successfully\n";
    echo "  Models found: " . count($parseResult['models']) . "\n";
    echo "  Enums found: " . count($parseResult['enums']) . "\n";
    
    // Display model details
    foreach ($parseResult['models'] as $modelName => $model) {
        echo "\n  Model: {$modelName}\n";
        echo "    Table: {$model['tableName']}\n";
        echo "    Fields: " . count($model['fields']) . "\n";
        echo "    Indexes: " . count($model['indexes']) . "\n";
        
        // Show a few fields
        $fieldCount = 0;
        foreach ($model['fields'] as $fieldName => $field) {
            if ($fieldCount++ < 3) {
                echo "      - {$fieldName}: {$field['type']} -> {$field['columnName']}\n";
            }
        }
    }
} else {
    echo "✗ Failed to parse Prisma schema\n";
    foreach ($parseResult['errors'] as $error) {
        echo "  Error: {$error}\n";
    }
}

echo "\n";

// Test 2: MySqlSchemaExtractor
echo "Test 2: MySqlSchemaExtractor\n";
echo str_repeat("-", 50) . "\n";

try {
    $db = Database::getInstance()->getConnection();
    $mysqlExtractor = new MySqlSchemaExtractor($db);
    
    // Extract specific tables related to inbox
    $tablesToExtract = ['line_users', 'conversations', 'messages'];
    $extractResult = $mysqlExtractor->extract($tablesToExtract);
    
    if ($extractResult['success']) {
        echo "✓ MySQL schema extracted successfully\n";
        echo "  Tables extracted: " . count($extractResult['tables']) . "\n";
        
        foreach ($extractResult['tables'] as $tableName => $table) {
            echo "\n  Table: {$tableName}\n";
            echo "    Columns: " . count($table['columns']) . "\n";
            echo "    Indexes: " . count($table['indexes']) . "\n";
            echo "    Foreign Keys: " . count($table['foreignKeys']) . "\n";
            
            // Show primary key
            if ($table['primaryKey']) {
                echo "    Primary Key: [" . implode(', ', $table['primaryKey']) . "]\n";
            }
        }
    } else {
        echo "✗ Failed to extract MySQL schema\n";
        foreach ($extractResult['errors'] as $error) {
            echo "  Error: {$error}\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "  Skipping MySQL-dependent tests\n";
    $db = null;
}

echo "\n";

// Test 3: SchemaComparator
if ($parseResult['success'] && isset($extractResult) && $extractResult['success']) {
    echo "Test 3: SchemaComparator\n";
    echo str_repeat("-", 50) . "\n";
    
    $comparator = new SchemaComparator($prismaParser, $mysqlExtractor);
    $compareResult = $comparator->compare();
    
    if ($compareResult['success']) {
        echo "✓ Schema comparison completed successfully\n";
    } else {
        echo "⚠ Schema comparison completed with issues\n";
    }
    
    $summary = $compareResult['summary'];
    echo "  Total Models: {$summary['totalModels']}\n";
    echo "  Compatible: {$summary['compatibleModels']}\n";
    echo "  Incompatible: {$summary['incompatibleModels']}\n";
    echo "  Total Issues: {$summary['totalIssues']}\n";
    
    echo "\n  Issues by Severity:\n";
    foreach ($summary['issuesBySeverity'] as $severity => $count) {
        if ($count > 0) {
            echo "    {$severity}: {$count}\n";
        }
    }
    
    if (!empty($summary['issuesByType'])) {
        echo "\n  Issues by Type:\n";
        foreach ($summary['issuesByType'] as $type => $count) {
            echo "    {$type}: {$count}\n";
        }
    }
    
    // Show some issues
    if (!empty($compareResult['issues'])) {
        echo "\n  Sample Issues:\n";
        $issueCount = 0;
        foreach ($compareResult['issues'] as $issue) {
            if ($issueCount++ < 5) {
                echo "    [{$issue['severity']}] {$issue['type']}: {$issue['description']}\n";
            }
        }
        if (count($compareResult['issues']) > 5) {
            echo "    ... and " . (count($compareResult['issues']) - 5) . " more issues\n";
        }
    }
    
    echo "\n";
}

// Test 4: IndexAnalyzer
if (isset($db) && $db !== null) {
    echo "Test 4: IndexAnalyzer\n";
    echo str_repeat("-", 50) . "\n";
    
    $indexAnalyzer = new IndexAnalyzer($mysqlExtractor);
    
    // Analyze PHP backend paths
    $pathsToAnalyze = [
        __DIR__ . '/../../api/inbox.php',
        __DIR__ . '/../../api/inbox-v2.php',
        __DIR__ . '/../../classes/InboxService.php'
    ];
    
    // Filter to existing paths
    $existingPaths = array_filter($pathsToAnalyze, 'file_exists');
    
    if (!empty($existingPaths)) {
        $analyzeResult = $indexAnalyzer->analyze($existingPaths);
        
        if ($analyzeResult['success']) {
            echo "✓ Index analysis completed successfully\n";
        } else {
            echo "⚠ Index analysis completed with errors\n";
            foreach ($analyzeResult['errors'] as $error) {
                echo "  Error: {$error}\n";
            }
        }
        
        $summary = $analyzeResult['summary'];
        echo "  Tables Analyzed: {$summary['tablesAnalyzed']}\n";
        echo "  Total Recommendations: {$summary['totalRecommendations']}\n";
        
        if (!empty($summary['recommendationsByPriority'])) {
            echo "\n  Recommendations by Priority:\n";
            foreach ($summary['recommendationsByPriority'] as $priority => $count) {
                if ($count > 0) {
                    echo "    {$priority}: {$count}\n";
                }
            }
        }
        
        // Show some recommendations
        if (!empty($analyzeResult['recommendations'])) {
            echo "\n  Sample Recommendations:\n";
            $recCount = 0;
            foreach ($analyzeResult['recommendations'] as $rec) {
                if ($recCount++ < 5) {
                    echo "    [{$rec['priority']}] {$rec['table']}: {$rec['description']}\n";
                    echo "      SQL: {$rec['sql']}\n";
                }
            }
            if (count($analyzeResult['recommendations']) > 5) {
                echo "    ... and " . (count($analyzeResult['recommendations']) - 5) . " more recommendations\n";
            }
        }
    } else {
        echo "⚠ No files found to analyze\n";
        echo "  Searched paths:\n";
        foreach ($pathsToAnalyze as $path) {
            echo "    - {$path}\n";
        }
    }
    
    echo "\n";
}

// Cleanup
if (file_exists($tempSchemaPath)) {
    unlink($tempSchemaPath);
}

echo "\n=== Test Complete ===\n";
