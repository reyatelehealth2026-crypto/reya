<?php
/**
 * Quick Test Fix
 * 
 * Test the fixed OdooSyncService with sample payloads
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/OdooSyncService.php';

use Modules\Core\Database;

try {
    $db = Database::getInstance()->getConnection();
    $syncService = new OdooSyncService($db);

    echo "=== Quick Fix Test ===\n\n";

    // Test 1: Invoice
    echo "Test 1: Syncing invoice.paid...\n";
    $invoicePayload = [
        "invoice_id" => 999991,
        "invoice_number" => "TEST-INV-001",
        "order_id" => 999991,
        "order_name" => "TEST-SO-001",
        "customer" => [
            "id" => 8896,  // This IS the partner_id
            "ref" => "PC2200143",
            "name" => "Test Customer",
            "line_user_id" => null
        ],
        "salesperson" => [
            "id" => 9,
            "name" => "Test Salesperson",
            "line_user_id" => false
        ],
        "amount_total" => 1000,
        "currency" => "THB",
        "invoice_date" => "2026-02-09",
        "due_date" => "2026-02-09"
    ];

    $result = $syncService->syncWebhook($invoicePayload, 'invoice.paid', 999991);
    echo $result ? "✓ SUCCESS\n" : "✗ FAILED\n";

    if ($result) {
        $stmt = $db->prepare("SELECT invoice_number, partner_id, amount_total, is_paid FROM odoo_invoices WHERE invoice_id = ?");
        $stmt->execute([999991]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "  → invoice_number: {$row['invoice_number']}\n";
            echo "  → partner_id: {$row['partner_id']}\n";
            echo "  → amount_total: {$row['amount_total']}\n";
            echo "  → is_paid: " . ($row['is_paid'] ? 'TRUE' : 'FALSE') . "\n";
        }
    }

    echo "\n";

    // Test 2: Order
    echo "Test 2: Syncing order.packing...\n";
    $orderPayload = [
        "order_id" => 999992,
        "order_name" => "TEST-SO-002",
        "new_state" => "packing",
        "new_state_display" => "กำลังแพ็ค",
        "customer" => [
            "id" => 8480,  // This IS the partner_id
            "ref" => "PC210014",
            "name" => "Test Customer 2",
            "line_user_id" => null
        ],
        "salesperson" => [
            "id" => 29,
            "name" => "Test Salesperson 2",
            "line_user_id" => false
        ],
        "amount_total" => 3583.5,
        "currency" => "THB",
        "order_date" => "2026-02-12"
    ];

    $result = $syncService->syncWebhook($orderPayload, 'order.packing', 999992);
    echo $result ? "✓ SUCCESS\n" : "✗ FAILED\n";

    if ($result) {
        $stmt = $db->prepare("SELECT order_name, partner_id, state, amount_total FROM odoo_orders WHERE order_id = ?");
        $stmt->execute([999992]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "  → order_name: {$row['order_name']}\n";
            echo "  → partner_id: {$row['partner_id']}\n";
            echo "  → state: {$row['state']}\n";
            echo "  → amount_total: {$row['amount_total']}\n";
        }
    }

    echo "\n";

    // Cleanup test data
    echo "Cleaning up test data...\n";
    $db->exec("DELETE FROM odoo_invoices WHERE invoice_id = 999991");
    $db->exec("DELETE FROM odoo_orders WHERE order_id = 999992");
    echo "✓ Cleanup complete\n\n";

    echo "=== Test Complete ===\n";
    echo "If both tests show SUCCESS with correct partner_id, the fix is working!\n";

} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
