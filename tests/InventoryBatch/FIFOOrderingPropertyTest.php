<?php
/**
 * Property-Based Test: FIFO Ordering
 * 
 * **Feature: inventory-batch-tracking, Property 10: FIFO ordering**
 * **Validates: Requirements 9.2**
 * 
 * Property: When using FIFO method, the getNextBatchForPicking SHALL return 
 * the batch with the earliest received_at date.
 */

namespace Tests\InventoryBatch;

use PHPUnit\Framework\TestCase;
use PDO;

require_once __DIR__ . '/../../classes/BatchService.php';

class FIFOOrderingPropertyTest extends TestCase
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
     * Property Test: FIFO picks the oldest received batch
     */
    public function testFIFOPicksOldestReceived(): void
    {
        $productId = 201;
        
        $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'NEW-BATCH',
            'quantity' => 100,
            'received_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);
        
        $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'OLD-BATCH',
            'quantity' => 100,
            'received_at' => date('Y-m-d H:i:s', strtotime('-30 days'))
        ]);
        
        $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'MIDDLE-BATCH',
            'quantity' => 100,
            'received_at' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ]);
        
        $nextBatch = $this->service->getNextBatchForPicking($productId, 'FIFO');
        
        $this->assertNotNull($nextBatch);
        $this->assertEquals('OLD-BATCH', $nextBatch['batch_number']);
    }
}
