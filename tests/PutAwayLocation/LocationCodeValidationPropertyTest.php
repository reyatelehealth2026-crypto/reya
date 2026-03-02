<?php
/**
 * Property-Based Test: Location Code Validation
 * 
 * **Feature: put-away-location, Property 1: Location code format validation**
 * **Validates: Requirements 1.1**
 * 
 * Property: For any string, the validateLocationCode method SHALL return true 
 * if and only if the string follows the Zone-Shelf-Bin format (e.g., A1-03-02).
 */

namespace Tests\PutAwayLocation;

use PHPUnit\Framework\TestCase;
use PDO;

require_once __DIR__ . '/../../classes/LocationService.php';

class LocationCodeValidationPropertyTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        // Mock PDO for LocationService constructor
        $pdo = $this->createMock(PDO::class);
        $this->service = new \LocationService($pdo, 1);
    }
    
    /**
     * Property Test: Valid location codes should pass validation
     */
    public function testValidLocationCodesPass(): void
    {
        $validCodes = [
            'A1-01-01',
            'B-10-20',
            'RX-02-05',
            'COLD-01-01',
            'ZONE123-99-99',
            'A-1-1',
            'A-01-1',
            'A-1-01'
        ];
        
        foreach ($validCodes as $code) {
            $this->assertTrue(
                $this->service->validateLocationCode($code),
                "Location code '$code' should be valid"
            );
            
            // Should also be case-insensitive in practice (service converts to upper)
            $this->assertTrue(
                $this->service->validateLocationCode(strtolower($code)),
                "Lowercase location code '" . strtolower($code) . "' should be valid"
            );
        }
    }
    
    /**
     * Property Test: Invalid location codes should fail validation
     */
    public function testInvalidLocationCodesFail(): void
    {
        $invalidCodes = [
            'A10101',      // Missing dashes
            'A1-01',        // Missing bin
            '01-01-01',     // Missing zone letter
            'A1--01-01',    // Double dash
            'A1-01-01-01',  // Extra component
            'A1-AA-01',     // Non-numeric shelf
            'A1-01-BB',     // Non-numeric bin
            '-01-01',       // Empty zone
            'A1-01-',       // Empty bin
            'A1- -01',      // Space in shelf
            'A 1-01-01',    // Space in zone
            'A1-01-01!',    // Special character
            '',             // Empty string
        ];
        
        foreach ($invalidCodes as $code) {
            $this->assertFalse(
                $this->service->validateLocationCode($code),
                "Location code '$code' should be invalid"
            );
        }
    }
    
    /**
     * Property Test: Randomly generated valid codes should always pass
     */
    public function testRandomValidCodesPass(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $zone = $this->generateRandomZone();
            $shelf = rand(1, 99);
            $bin = rand(1, 99);
            
            $code = sprintf('%s-%02d-%02d', $zone, $shelf, $bin);
            
            $this->assertTrue(
                $this->service->validateLocationCode($code),
                "Randomly generated valid code '$code' should pass"
            );
        }
    }
    
    private function generateRandomZone(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $zone = '';
        $len = rand(1, 4);
        for ($i = 0; $i < $len; $i++) {
            $zone .= $letters[rand(0, strlen($letters) - 1)];
        }
        if (rand(0, 1)) {
            $zone .= rand(1, 9);
        }
        return $zone;
    }
}
