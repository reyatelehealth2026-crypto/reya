#!/usr/bin/env python3
"""
Test Odoo Webhook Signature Verification

This script tests webhook signature validation with both formats:
1. Payload only: sha256=HMAC-SHA256(payload, secret)
2. Timestamp.Payload: sha256=HMAC-SHA256(timestamp.payload, secret)
"""

import hmac
import hashlib
import json
import time
import requests

# Configuration
SECRET = 'cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
BASE_URL = 'https://cny.re-ya.com'
TEST_ENDPOINT = f'{BASE_URL}/api/webhook/test-signature.php'
WEBHOOK_ENDPOINT = f'{BASE_URL}/api/webhook/odoo.php'

# Test payload
payload = {
    "event": "order.validated",
    "data": {
        "order_id": 9999,
        "order_name": "TEST-001",
        "order_ref": "SO-TEST-001",
        "customer": {
            "partner_id": 100,
            "name": "Test Customer"
        },
        "amount_total": 1500.00
    }
}

# Convert to JSON string (no extra whitespace)
payload_json = json.dumps(payload, separators=(',', ':'), ensure_ascii=False)
payload_bytes = payload_json.encode('utf-8')

# Get current timestamp
timestamp = int(time.time())

# Calculate signatures
def calculate_signature(data, secret):
    """Calculate HMAC-SHA256 signature"""
    return 'sha256=' + hmac.new(
        secret.encode('utf-8'),
        data.encode('utf-8') if isinstance(data, str) else data,
        hashlib.sha256
    ).hexdigest()

# Format 1: Payload only
sig_payload_only = calculate_signature(payload_bytes, SECRET)

# Format 2: Timestamp.Payload
sig_timestamp_payload = calculate_signature(f"{timestamp}.{payload_json}", SECRET)

print("=" * 60)
print("Odoo Webhook Signature Test")
print("=" * 60)
print()
print(f"Secret: {SECRET[:20]}...")
print(f"Timestamp: {timestamp}")
print(f"Payload length: {len(payload_json)} bytes")
print()
print("Signatures:")
print(f"  Payload only: {sig_payload_only[:50]}...")
print(f"  Timestamp.Payload: {sig_timestamp_payload[:50]}...")
print()

# Test 1: Test signature verification endpoint
print("-" * 60)
print("Test 1: Signature Verification Endpoint")
print("-" * 60)

headers = {
    'Content-Type': 'application/json; charset=utf-8',
    'X-Odoo-Signature': sig_payload_only,
    'X-Odoo-Timestamp': str(timestamp),
    'X-Odoo-Event': 'order.validated',
    'X-Odoo-Delivery-Id': f'test-{timestamp}'
}

try:
    response = requests.post(TEST_ENDPOINT, data=payload_json, headers=headers)
    print(f"Status: {response.status_code}")
    print(f"Response:")
    print(json.dumps(response.json(), indent=2, ensure_ascii=False))
except Exception as e:
    print(f"Error: {e}")

print()

# Test 2: Test with timestamp.payload format
print("-" * 60)
print("Test 2: Legacy Format (Timestamp.Payload)")
print("-" * 60)

headers['X-Odoo-Signature'] = sig_timestamp_payload

try:
    response = requests.post(TEST_ENDPOINT, data=payload_json, headers=headers)
    print(f"Status: {response.status_code}")
    print(f"Response:")
    print(json.dumps(response.json(), indent=2, ensure_ascii=False))
except Exception as e:
    print(f"Error: {e}")

print()

# Test 3: Test actual webhook endpoint
print("-" * 60)
print("Test 3: Actual Webhook Endpoint (Payload Only)")
print("-" * 60)

headers['X-Odoo-Signature'] = sig_payload_only
headers['X-Odoo-Delivery-Id'] = f'test-actual-{timestamp}'

try:
    response = requests.post(WEBHOOK_ENDPOINT, data=payload_json, headers=headers)
    print(f"Status: {response.status_code}")
    print(f"Response:")
    print(json.dumps(response.json(), indent=2, ensure_ascii=False))
except Exception as e:
    print(f"Error: {e}")

print()
print("=" * 60)
print("Test Complete")
print("=" * 60)
