<?php
/**
 * Property-Based Test: Location Uniqueness
 * 
 * **Feature: put-away-location, Property 3: Location uniqueness**
 * **Validates: Requirements 1.5**
 * 
 * Property: The system SHALL NOT allow creating two locations with the same 
 * location code for the same line account.
 */

namespace Tests\PutAwayLocation;

use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

require_once __DIR__ . '/../../classes/LocationService.php';

class LocationUniquenessPropertyTest extends TestCase
{
    private $pdo;
    private $service;
    private $lineAccountId = 1;
    
    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create warehouse_locations table
        $this->pdo->exec("
            CREATE TABLE warehouse_locations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                line_account_id INTEGER NOT NULL,
                location_code VARCHAR(50) NOT NULL,
                zone VARCHAR(20) NOT NULL,
                shelf INTEGER NOT NULL,
                bin INTEGER NOT NULL,
                zone_type VARCHAR(50) DEFAULT 'general',
                ergonomic_level VARCHAR(20) DEFAULT 'golden',
                capacity INTEGER DEFAULT 100,
                current_qty INTEGER DEFAULT 0,
                description TEXT,
                is_active INTEGER DEFAULT 1,
                UNIQUE(line_account_id, location_code)
            )
        ");
        
        $this->service = new \LocationService($this->pdo, $this->lineAccountId);
    }
    
    /**
     * Property Test: Creating a duplicate location code should throw an exception
     */
    public function testDuplicateLocationCodeThrowsException(): void
    {
        $locationData = [
            'zone' => 'A1',
            'shelf' => 1,
            'bin' => 1,
            'location_code' => 'A1-01-01'
        ];
        
        // First creation should succeed
        $this->service->createLocation($locationData);
        
        // Second creation with same code should fail
        $this->expectException(Exception::class);
        $this->expectExceptionCode(409); // Conflict
        
        $this->service->createLocation($locationData);
    }
    
    /**
     * Property Test: Same location code for DIFFERENT line accounts should be allowed
     * (Multi-tenancy check)
     */
    public function testSameCodeDifferentAccountsAllowed(): void
    {
        $locationCode = 'B2-05-10';
        $data = [
            'zone' => 'B2',
            'shelf' => 5,
            'bin' => 10,
            'location_code' => $locationCode
        ];
        
        // Create for account 1
        $service1 = new \LocationService($this->pdo, 1);
        $id1 = $service1->createLocation($data);
        $this->assertGreaterThan(0, $id1);
        
        // Create for account 2
        $service2 = new \LocationService($this->pdo, 2);
        $id2 = $service2->createLocation($data);
        $this->assertGreaterThan(0, $id2);
        $this->assertNotEquals($id1, $id2);
    }
    
    /**
     * Property Test: Updating a location to an existing code should fail
     */
    public function testUpdateToExistingCodeFails(): void
    {
        $id1 = $this->service->createLocation(['zone' => 'A', 'shelf' => 1, 'bin' => 1]); // A-01-01
        $id2 = $this->service->createLocation(['zone' => 'A', 'shelf' => 1, 'bin' => 2]); // A-01-02
        
        $this->expectException(Exception::class);
        $this->expectExceptionCode(409);
        
        $this->service->updateLocation($id2, ['location_code' => 'A-01-01']);
    }
}
