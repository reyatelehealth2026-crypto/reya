<?php
/**
 * Test Odoo Webhook Signature Verification
 * 
 * This script helps debug signature validation issues
 * by showing all the details of the signature calculation.
 * 
 * Usage: Send POST request with X-Odoo-Signature header
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$signature = $_SERVER['HTTP_X_ODOO_SIGNATURE'] ?? null;
$timestamp = $_SERVER['HTTP_X_ODOO_TIMESTAMP'] ?? null;
$payload = file_get_contents('php://input');

// Get configured secret
$secret = ODOO_WEBHOOK_SECRET;

// Calculate expected signatures
$expectedPayloadOnly = 'sha256=' . hash_hmac('sha256', $payload, $secret);
$expectedTimestampPayload = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

// Response
$response = [
    'debug_info' => [
        'method' => $method,
        'environment' => ODOO_ENVIRONMENT,
        'secret_configured' => !empty($secret),
        'secret_length' => strlen($secret ?? ''),
        'secret_preview' => substr($secret ?? '', 0, 10) . '...',
        'timestamp' => $timestamp,
        'timestamp_age_seconds' => $timestamp ? (time() - (int)$timestamp) : null,
        'payload_length' => strlen($payload),
        'payload_preview' => substr($payload, 0, 100) . '...',
    ],
    'signatures' => [
        'received' => $signature,
        'expected_payload_only' => $expectedPayloadOnly,
        'expected_timestamp_payload' => $expectedTimestampPayload,
    ],
    'validation' => [
        'payload_only_match' => hash_equals($signature ?? '', $expectedPayloadOnly),
        'timestamp_payload_match' => hash_equals($signature ?? '', $expectedTimestampPayload),
        'timestamp_valid' => $timestamp ? (abs(time() - (int)$timestamp) <= 300) : false,
    ],
    'headers' => [
        'X-Odoo-Signature' => $signature,
        'X-Odoo-Timestamp' => $timestamp,
        'X-Odoo-Event' => $_SERVER['HTTP_X_ODOO_EVENT'] ?? null,
        'X-Odoo-Delivery-Id' => $_SERVER['HTTP_X_ODOO_DELIVERY_ID'] ?? null,
        'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? null,
    ]
];

// Add recommendation
if ($response['validation']['payload_only_match']) {
    $response['result'] = 'SUCCESS - Payload-only signature is correct';
} elseif ($response['validation']['timestamp_payload_match']) {
    $response['result'] = 'SUCCESS - Timestamp.Payload signature is correct (legacy format)';
} else {
    $response['result'] = 'FAILED - Signature does not match';
    $response['recommendation'] = [
        'check_secret' => 'Verify ODOO_WEBHOOK_SECRET matches Odoo configuration',
        'check_payload' => 'Ensure payload is sent exactly as-is (no modifications)',
        'check_encoding' => 'Verify UTF-8 encoding and no extra whitespace',
        'check_timestamp' => 'Ensure timestamp is within 5 minutes',
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
