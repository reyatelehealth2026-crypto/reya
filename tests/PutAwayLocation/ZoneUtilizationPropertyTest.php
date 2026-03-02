<?php
/**
 * Property-Based Test: Zone Utilization Calculation
 * 
 * **Feature: put-away-location, Property 15: Zone utilization calculation**
 * **Validates: Requirements 5.1**
 * 
 * Property: For any zone, the utilization percentage SHALL be equal to 
 * (sum of current_qty in zone / sum of capacity in zone) * 100.
 */

namespace Tests\PutAwayLocation;

use PHPUnit\Framework\TestCase;
use PDO;

require_once __DIR__ . '/../../classes/LocationService.php';

class ZoneUtilizationPropertyTest extends TestCase
{
    private $pdo;
    private $service;
    private $lineAccountId = 1;
    
    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
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
                is_active INTEGER DEFAULT 1
            )
        ");
        
        $this->service = new \LocationService($this->pdo, $this->lineAccountId);
    }
    
    /**
     * Property Test: Zone utilization matches manual calculation
     */
    public function testZoneUtilizationCalculation(): void
    {
        $zone = 'TEST-ZONE';
        $locations = [
            ['shelf' => 1, 'bin' => 1, 'capacity' => 100, 'current_qty' => 50],
            ['shelf' => 1, 'bin' => 2, 'capacity' => 200, 'current_qty' => 150],
            ['shelf' => 2, 'bin' => 1, 'capacity' => 150, 'current_qty' => 0],
            ['shelf' => 2, 'bin' => 2, 'capacity' => 50, 'current_qty' => 50],
        ];
        
        $totalCapacity = 0;
        $totalQty = 0;
        
        foreach ($locations as $loc) {
            $this->service->createLocation([
                'zone' => $zone,
                'shelf' => $loc['shelf'],
                'bin' => $loc['bin'],
                'capacity' => $loc['capacity']
            ]);
            
            // Update quantity (since createLocation defaults to 0)
            $this->pdo->prepare("UPDATE warehouse_locations SET current_qty = ? WHERE zone = ? AND shelf = ? AND bin = ?")
                      ->execute([$loc['current_qty'], $zone, $loc['shelf'], $loc['bin']]);
            
            $totalCapacity += $loc['capacity'];
            $totalQty += $loc['current_qty'];
        }
        
        $expectedPercent = round(($totalQty / $totalCapacity) * 100, 2);
        $actualPercent = $this->service->getZoneUtilization($zone);
        
        $this->assertEquals($expectedPercent, $actualPercent, "Zone utilization calculation should be accurate");
    }
    
    /**
     * Property Test: Empty zone utilization is 0
     */
    public function testEmptyZoneUtilizationIsZero(): void
    {
        $this->assertEquals(0.0, $this->service->getZoneUtilization('NON-EXISTENT'));
    }
    
    /**
     * Property Test: Full zone utilization is 100
     */
    public function testFullZoneUtilizationIsHundred(): void
    {
        $zone = 'FULL';
        $this->service->createLocation(['zone' => $zone, 'shelf' => 1, 'bin' => 1, 'capacity' => 100]);
        $this->pdo->prepare("UPDATE warehouse_locations SET current_qty = 100 WHERE zone = ?")->execute([$zone]);
        
        $this->assertEquals(100.0, $this->service->getZoneUtilization($zone));
    }
}
