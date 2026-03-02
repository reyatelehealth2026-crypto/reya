<?php
/**
 * Property-Based Test: Expired Batch Blocking
 * 
 * **Feature: inventory-batch-tracking, Property 11: Expired batch blocking**
 * **Validates: Requirements 10.3**
 * 
 * Property: The system SHALL NOT allow picking from a batch that is expired, 
 * even if it has available stock.
 */

namespace Tests\InventoryBatch;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

require_once __DIR__ . '/../../classes/BatchService.php';

class ExpiredBatchBlockingPropertyTest extends TestCase
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
     * Property Test: getNextBatchForPicking excludes expired batches
     */
    public function testPickingExcludesExpiredBatches(): void
    {
        $productId = 301;
        
        // Create only an expired batch
        $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'ONLY-EXPIRED',
            'quantity' => 100,
            'expiry_date' => date('Y-m-d', strtotime('-1 day'))
        ]);
        
        $nextBatch = $this->service->getNextBatchForPicking($productId);
        $this->assertNull($nextBatch, "Should not find any batch for picking if only expired batches exist");
    }
    
    /**
     * Property Test: reduceQuantity throws exception for expired batch
     */
    public function testReduceQuantityFailsForExpiredBatch(): void
    {
        $productId = 302;
        $batchId = $this->service->createBatch([
            'product_id' => $productId,
            'batch_number' => 'EXPIRED-MANUAL',
            'quantity' => 100,
            'expiry_date' => date('Y-m-d', strtotime('-1 day'))
        ]);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot pick from expired batch');
        
        $this->service->reduceQuantity($batchId, 10);
    }
}
