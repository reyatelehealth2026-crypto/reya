<?php
/**
 * Property-Based Test: FEFO Ordering
 * 
 * **Feature: inventory-batch-tracking, Property 9: FEFO ordering**
 * **Validates: Requirements 9.1, 9.3**
 * 
 * Property: When using FEFO method, the getNextBatchForPicking SHALL return 
 * the batch with the earliest expiry date that is not expired.
 */

namespace Tests\InventoryBatch;

use PHPUnit\Framework\TestCase;
use PDO;
use DateTime;

require_once __DIR__ . '/../../classes/BatchService.php';

class FEFOOrderingPropertyTest extends TestCase
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
     * Property Test: FEFO picks the earliest non-expired batch
     */
    public function testFEFOPicksEarliestNonExpired(): void
    {
        $productId = 101;
        
        // Create batches with different expiry dates
        $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'LATE-EXPIRY',
            'quantity' => 100,
            'expiry_date' => date('Y-m-d', strtotime('+100 days'))
        ]);
        
        $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'EARLY-EXPIRY',
            'quantity' => 100,
            'expiry_date' => date('Y-m-d', strtotime('+10 days'))
        ]);
        
        $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'EXPIRED',
            'quantity' => 100,
            'expiry_date' => date('Y-m-d', strtotime('-5 days'))
        ]);
        
        // FEFO should pick EARLY-EXPIRY
        $nextBatch = $this->service->getNextBatchForPicking($productId, 'FEFO');
        
        $this->assertNotNull($nextBatch);
        $this->assertEquals('EARLY-EXPIRY', $nextBatch['batch_number']);
    }
    
    /**
     * Property Test: FEFO falls back to FIFO for same expiry
     */
    public function testFEFOFallsBackToFIFOForSameExpiry(): void
    {
        $productId = 102;
        $expiryDate = date('Y-m-d', strtotime('+30 days'));
        
        // Create two batches with same expiry but different receive dates
        $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'LATER-RECEIVED',
            'quantity' => 100,
            'expiry_date' => $expiryDate,
            'received_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);
        
        $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'EARLIER-RECEIVED',
            'quantity' => 100,
            'expiry_date' => $expiryDate,
            'received_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ]);
        
        $nextBatch = $this->service->getNextBatchForPicking($productId, 'FEFO');
        
        $this->assertNotNull($nextBatch);
        $this->assertEquals('EARLIER-RECEIVED', $nextBatch['batch_number']);
    }
}
