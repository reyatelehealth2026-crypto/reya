<?php
// Debug script to test webhook signature generation
require_once __DIR__ . '/config/config.php';

$payload = '{"event":"order.validated","data":{"order_id":12345,"order_ref":"SO001","order_name":"Sales Order 001","amount_total":1500.00,"state":"sale","customer":{"partner_id":67890,"name":"Test Customer"},"salesperson":{"partner_id":1,"name":"Admin"},"expected_date":"2026-02-20"},"notify":{"customer":true,"salesperson":false},"message_template":{"customer":{"th":"Order confirmed"},"salesperson":{"th":"New order"}}}';

$secret = ODOO_WEBHOOK_SECRET;
echo "Webhook Secret: " . $secret . "\n";
echo "Payload: " . $payload . "\n";
echo "Payload length: " . strlen($payload) . "\n";

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
echo "Expected Signature: " . $expectedSignature . "\n";

// Test with timestamp
$timestamp = time();
$legacyData = $timestamp . '.' . $payload;
$legacySignature = 'sha256=' . hash_hmac('sha256', $legacyData, $secret);
echo "Legacy Signature (timestamp.payload): " . $legacySignature . "\n";
echo "Timestamp: " . $timestamp . "\n";
?>
