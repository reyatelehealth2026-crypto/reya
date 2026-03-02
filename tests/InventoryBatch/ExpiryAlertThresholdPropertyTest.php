<?php
/**
 * Property-Based Test: Expiry Alert Threshold
 * 
 * **Feature: inventory-batch-tracking, Property 13: Expiry alert threshold**
 * **Validates: Requirements 10.1, 8.4**
 * 
 * Property: A batch SHALL be marked as 'is_near_expiry' if and only if 
 * the current date is within the NEAR_EXPIRY_ALERT_DAYS (30 days) threshold.
 */

namespace Tests\InventoryBatch;

use PHPUnit\Framework\TestCase;
use PDO;
use DateTime;

require_once __DIR__ . '/../../classes/BatchService.php';

class ExpiryAlertThresholdPropertyTest extends TestCase
{
    private $pdo;
    private $service;
    private $lineAccountId = 1;
    
    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->pdo->exec("
            CREATE TABLE inventory_batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                line_account_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                batch_number VARCHAR(50) NOT NULL,
                lot_number VARCHAR(50),
                supplier_id INTEGER,
                quantity INTEGER NOT NULL,
                quantity_available INTEGER NOT NULL,
                cost_price DECIMAL(10,2),
                manufacture_date DATE,
                expiry_date DATE,
                received_at DATETIME,
                received_by INTEGER,
                location_id INTEGER,
                status VARCHAR(20) DEFAULT 'active',
                notes TEXT,
                disposal_date DATETIME,
                disposal_by INTEGER,
                disposal_reason TEXT
            )
        ");
        
        // Also need warehouse_locations for the JOIN in getBatch
        $this->pdo->exec("
            CREATE TABLE warehouse_locations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                location_code VARCHAR(50),
                zone VARCHAR(20),
                shelf INTEGER,
                bin INTEGER
            )
        ");
        
        $this->service = new \BatchService($this->pdo, $this->lineAccountId);
    }
    
    /**
     * Property Test: Batch is near expiry within 30 days
     */
    public function testNearExpiryAlertThreshold(): void
    {
        $today = new DateTime('today');
        
        // Test cases: [days_from_today, expected_is_near_expiry, expected_is_expired]
        $testCases = [
            [31, false, false], // Just outside threshold
            [30, true, false],  // On threshold
            [15, true, false],  // Inside threshold
            [1, true, false],   // Tomorrow
            [0, true, false],   // Today (is_near_expiry is true, is_expired is false because days_until_expiry = 0)
            [-1, false, true],  // Yesterday (is_expired is true, is_near_expiry is false)
        ];
        
        foreach ($testCases as $case) {
            [$days, $expectedNear, $expectedExpired] = $case;
            
            $expiryDate = clone $today;
            if ($days >= 0) {
                $expiryDate->modify("+{$days} days");
            } else {
                $absDays = abs($days);
                $expiryDate->modify("-{$absDays} days");
            }
            
            $batchId = $this->service->createBatch([
                'product_id' => 1,
                'batch_number' => "BATCH-{$days}",
                'quantity' => 100,
                'expiry_date' => $expiryDate->format('Y-m-d')
            ]);
            
            $batch = $this->service->getBatch($batchId);
            
            $this->assertEquals(
                $expectedNear, 
                $batch['is_near_expiry'], 
                "Batch expiring in $days days should have is_near_expiry=" . ($expectedNear ? 'true' : 'false')
            );
            
            $this->assertEquals(
                $expectedExpired, 
                $batch['is_expired'], 
                "Batch expiring in $days days should have is_expired=" . ($expectedExpired ? 'true' : 'false')
            );
        }
    }
}
