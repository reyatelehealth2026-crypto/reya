#!/bin/bash

# Simple POST test for webhook signature
SECRET='cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
PAYLOAD='{"event":"order.validated","data":{"order_id":9999,"order_name":"TEST-001"}}'
TIMESTAMP=$(date +%s)

# Calculate signature
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

echo "Testing webhook signature..."
echo "Timestamp: $TIMESTAMP"
echo "Signature: sha256=$SIGNATURE"
echo ""

# Send POST request
curl -X POST "https://cny.re-ya.com/api/webhook/test-signature.php" \
  -H "Content-Type: application/json" \
  -H "X-Odoo-Signature: sha256=$SIGNATURE" \
  -H "X-Odoo-Timestamp: $TIMESTAMP" \
  -H "X-Odoo-Event: order.validated" \
  -H "X-Odoo-Delivery-Id: test-$TIMESTAMP" \
  -d "$PAYLOAD"

echo ""
echo "Done!"
