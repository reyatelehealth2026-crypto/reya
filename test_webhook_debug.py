#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Debug Webhook Signature
"""

import json
import hmac
import hashlib
import time

WEBHOOK_SECRET = "cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb"

# Simple payload
payload = {
    "event": "order.validated",
    "data": {
        "order_id": 12345,
        "order_ref": "SO001"
    }
}

# Convert to JSON
payload_json = json.dumps(payload, ensure_ascii=False, separators=(',', ':'))
payload_bytes = payload_json.encode('utf-8')

timestamp = int(time.time())

print("=" * 60)
print("Signature Debug")
print("=" * 60)
print()
print(f"Secret: {WEBHOOK_SECRET}")
print(f"Timestamp: {timestamp}")
print()
print(f"Payload JSON: {payload_json}")
print(f"Payload bytes: {payload_bytes}")
print(f"Payload length: {len(payload_bytes)}")
print()

# Method 1: HMAC of payload only (primary format)
sig1 = hmac.new(
    WEBHOOK_SECRET.encode('utf-8'),
    payload_bytes,
    hashlib.sha256
).hexdigest()
print(f"Method 1 (payload only): sha256={sig1}")

# Method 2: HMAC of timestamp.payload (legacy format)
legacy_data = f"{timestamp}.{payload_json}"
legacy_bytes = legacy_data.encode('utf-8')
sig2 = hmac.new(
    WEBHOOK_SECRET.encode('utf-8'),
    legacy_bytes,
    hashlib.sha256
).hexdigest()
print(f"Method 2 (timestamp.payload): sha256={sig2}")
print()

# Generate curl command
print("=" * 60)
print("Test with curl:")
print("=" * 60)
print()

# Save payload to file
with open('test_payload_simple.json', 'w', encoding='utf-8') as f:
    f.write(payload_json)

print(f'curl -X POST "https://cny.re-ya.com/api/webhook/odoo.php" \\')
print(f'  -H "Content-Type: application/json" \\')
print(f'  -H "X-Odoo-Signature: sha256={sig1}" \\')
print(f'  -H "X-Odoo-Timestamp: {timestamp}" \\')
print(f'  -H "X-Odoo-Event: order.validated" \\')
print(f'  -H "X-Odoo-Delivery-Id: wh_test_{timestamp}" \\')
print(f'  -d @test_payload_simple.json')
print()
print("Payload saved to: test_payload_simple.json")
