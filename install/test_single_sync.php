<?php
/**
 * Test Single Sync
 * 
 * Test syncing a single webhook to see exact error
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

    echo "=== Testing Single Webhook Sync ===\n\n";

    // Test invoice.paid
    echo "Test 1: invoice.paid\n";
    echo str_repeat("-", 60) . "\n";
    
    $invoicePayload = [
        "invoice_id" => 171536,
        "invoice_number" => "HS26023960",
        "order_id" => 161665,
        "order_name" => "SO2602-03655",
        "customer" => [
            "id" => 8896,
            "ref" => "PC2200143",
            "name" => "*รวมยาโอสถ สาขาดอนตูม",
            "line_user_id" => null
        ],
        "salesperson" => [
            "id" => 9,
            "name" => "นาตยา แพจู(หนึ่ง)",
            "line_user_id" => false
        ],
        "amount_total" => 998,
        "amount_tax" => 65.29,
        "amount_untaxed" => 932.71,
        "currency" => "THB",
        "invoice_date" => "2026-02-09",
        "due_date" => "2026-02-09",
        "payment_term" => "โอนก่อนส่ง",
        "pdf_url" => "/report/pdf/account.report_invoice/171536"
    ];

    try {
        $result = $syncService->syncWebhook($invoicePayload, 'invoice.paid', 999999);
        
        if ($result) {
            echo "✓ Sync returned TRUE\n";
            
            // Check if actually inserted
            $stmt = $db->prepare("SELECT * FROM odoo_invoices WHERE invoice_id = ?");
            $stmt->execute([171536]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                echo "✓ Found in database:\n";
                echo "  invoice_number: {$row['invoice_number']}\n";
                echo "  order_name: {$row['order_name']}\n";
                echo "  amount_total: {$row['amount_total']}\n";
                echo "  is_paid: " . ($row['is_paid'] ? 'TRUE' : 'FALSE') . "\n";
            } else {
                echo "✗ NOT FOUND in database!\n";
            }
        } else {
            echo "✗ Sync returned FALSE\n";
        }
    } catch (Exception $e) {
        echo "✗ EXCEPTION: {$e->getMessage()}\n";
        echo "File: {$e->getFile()}:{$e->getLine()}\n";
        echo "Trace:\n{$e->getTraceAsString()}\n";
    }

    echo "\n\n";

    // Test order.packing
    echo "Test 2: order.packing\n";
    echo str_repeat("-", 60) . "\n";
    
    $orderPayload = [
        "order_id" => 162473,
        "order_name" => "SO2602-04462",
        "old_state" => "picked",
        "new_state" => "packing",
        "old_state_display" => "จัดเสร็จแล้ว",
        "new_state_display" => "กำลังแพ็ค",
        "customer" => [
            "id" => 8480,
            "ref" => "PC210014",
            "name" => "บริษัท ใบปอฟาร์มาซี จำกัด สาขาที่ 00002",
            "line_user_id" => null,
            "phone" => "0947812647"
        ],
        "salesperson" => [
            "id" => 29,
            "name" => "วิรัชนก ฉ่ำชะเอม (เบียร์)",
            "line_user_id" => false
        ],
        "picker" => null,
        "amount_total" => 3583.5,
        "currency" => "THB",
        "order_date" => "2026-02-12",
        "expected_delivery" => "2026-02-12",
        "items_count" => 11
    ];

    try {
        $result = $syncService->syncWebhook($orderPayload, 'order.packing', 999998);
        
        if ($result) {
            echo "✓ Sync returned TRUE\n";
            
            // Check if actually inserted
            $stmt = $db->prepare("SELECT * FROM odoo_orders WHERE order_id = ?");
            $stmt->execute([162473]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                echo "✓ Found in database:\n";
                echo "  order_name: {$row['order_name']}\n";
                echo "  state: {$row['state']}\n";
                echo "  amount_total: {$row['amount_total']}\n";
                echo "  delivery_status: {$row['delivery_status']}\n";
            } else {
                echo "✗ NOT FOUND in database!\n";
            }
        } else {
            echo "✗ Sync returned FALSE\n";
        }
    } catch (Exception $e) {
        echo "✗ EXCEPTION: {$e->getMessage()}\n";
        echo "File: {$e->getFile()}:{$e->getLine()}\n";
        echo "Trace:\n{$e->getTraceAsString()}\n";
    }

    echo "\n\n";
    echo "=== Test Complete ===\n";

} catch (Exception $e) {
    echo "Fatal error: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
