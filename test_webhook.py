#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Test Odoo Webhook Script
Usage: python test_webhook.py
"""

import json
import hmac
import hashlib
import time
import urllib.request
import urllib.error

# Configuration
WEBHOOK_URL = "https://cny.re-ya.com/api/webhook/odoo.php"
WEBHOOK_SECRET = "cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb"

# Create payload
payload = {
    "event": "order.validated",
    "data": {
        "order_id": 12345,
        "order_ref": "SO001",
        "order_name": "Sales Order 001",
        "amount_total": 1500.00,
        "state": "sale",
        "customer": {
            "partner_id": 67890,
            "name": "Test Customer"
        },
        "salesperson": {
            "partner_id": 1,
            "name": "Admin"
        },
        "expected_date": "2026-02-20"
    },
    "notify": {
        "customer": True,
        "salesperson": False
    },
    "message_template": {
        "customer": {
            "th": "ออเดอร์ {order_ref} ได้รับการยืนยันแล้ว ยอดเงิน {amount} บาท"
        },
        "salesperson": {
            "th": "ออเดอร์ใหม่ {order_ref} จากลูกค้า {customer_name}"
        }
    }
}

# Convert to JSON string
payload_json = json.dumps(payload, ensure_ascii=False, separators=(',', ':'))
payload_bytes = payload_json.encode('utf-8')

# Generate timestamp
timestamp = int(time.time())

# Generate HMAC-SHA256 signature
signature = hmac.new(
    WEBHOOK_SECRET.encode('utf-8'),
    payload_bytes,
    hashlib.sha256
).hexdigest()
signature_header = f"sha256={signature}"

# Prepare headers
headers = {
    "Content-Type": "application/json; charset=utf-8",
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "X-Odoo-Signature": signature_header,
    "X-Odoo-Timestamp": str(timestamp),
    "X-Odoo-Event": "order.validated",
    "X-Odoo-Delivery-Id": f"wh_test_{timestamp}"
}

# Display request info
print("=" * 60)
print("Testing Odoo Webhook")
print("=" * 60)
print()
print(f"URL: {WEBHOOK_URL}")
print(f"Timestamp: {timestamp}")
print(f"Current time: {time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(timestamp))}")
print(f"Signature: {signature_header}")
print()
print("Payload (raw bytes):")
print(f"Length: {len(payload_bytes)} bytes")
print(f"First 100 chars: {payload_json[:100]}")
print()
print("Payload (formatted):")
print(json.dumps(payload, ensure_ascii=False, indent=2))
print()
print("Sending webhook request...")
print()

# Send request using urllib
try:
    req = urllib.request.Request(
        WEBHOOK_URL,
        data=payload_bytes,
        headers=headers,
        method='POST'
    )
    
    with urllib.request.urlopen(req, timeout=10) as response:
        status_code = response.status
        response_data = response.read().decode('utf-8')
    
    print(f"Status Code: {status_code}")
    print()
    print("Response:")
    try:
        response_json = json.loads(response_data)
        print(json.dumps(response_json, ensure_ascii=False, indent=2))
    except:
        print(response_data)
    
    print()
    print("=" * 60)
    print("Test completed successfully!")
    print("=" * 60)
    
except urllib.error.HTTPError as e:
    print(f"HTTP Error: {e.code}")
    print()
    print("Response:")
    try:
        error_data = e.read().decode('utf-8')
        error_json = json.loads(error_data)
        print(json.dumps(error_json, ensure_ascii=False, indent=2))
    except:
        print(error_data)
    print()
    print("=" * 60)
    print("Test completed with errors")
    print("=" * 60)
    
except Exception as e:
    print(f"Error: {e}")
    print()
    print("=" * 60)
    print("Test failed")
    print("=" * 60)
