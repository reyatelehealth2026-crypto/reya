# Odoo Orders API - Quick Reference

**Endpoint:** `/re-ya/api/odoo-orders.php`  
**Method:** POST  
**Content-Type:** application/json

## Overview

The Odoo Orders API provides comprehensive order management functionality for LINE users, including:
- Listing orders with filters and pagination
- Viewing detailed order information
- Tracking order status timeline
- Searching orders by various criteria

---

## Actions

### 1. List Orders

Get a list of orders with optional filters and pagination.

**Request:**
```json
{
  "action": "list",
  "line_user_id": "U1234567890abcdef",
  "state": "sale",           // Optional: Filter by state
  "date_from": "2026-01-01", // Optional: Start date
  "date_to": "2026-12-31",   // Optional: End date
  "limit": 20,               // Optional: Results per page (default: 20)
  "offset": 0                // Optional: Pagination offset (default: 0)
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 123,
        "name": "SO001",
        "date_order": "2026-02-01",
        "state": "sale",
        "amount_total": 1500.00,
        "customer": {
          "id": 456,
          "name": "คุณสมชาย ใจดี"
        }
      }
    ],
    "total": 50,
    "limit": 20,
    "offset": 0
  }
}
```

**State Values:**
- `draft` - ร่าง
- `sent` - ส่งใบเสนอราคาแล้ว
- `sale` - ยืนยันแล้ว
- `done` - เสร็จสิ้น
- `cancel` - ยกเลิก

---

### 2. Order Detail

Get detailed information about a specific order.

**Request:**
```json
{
  "action": "detail",
  "line_user_id": "U1234567890abcdef",
  "order_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "SO001",
    "date_order": "2026-02-01",
    "state": "sale",
    "amount_untaxed": 1350.00,
    "amount_tax": 150.00,
    "amount_total": 1500.00,
    "payment_status": "paid",
    "customer": {
      "id": 456,
      "name": "คุณสมชาย ใจดี",
      "phone": "081-234-5678"
    },
    "shipping_address": {
      "street": "123 ถนนสุขุมวิท",
      "city": "กรุงเทพฯ",
      "zip": "10110"
    },
    "salesperson": {
      "id": 789,
      "name": "คุณสมหญิง"
    },
    "order_lines": [
      {
        "product_id": 100,
        "product_name": "ยาพารา 500mg",
        "quantity": 10,
        "price_unit": 100.00,
        "price_subtotal": 1000.00
      }
    ]
  }
}
```

---

### 3. Order Tracking

Get the tracking timeline for an order.

**Request:**
```json
{
  "action": "tracking",
  "line_user_id": "U1234567890abcdef",
  "order_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "order_id": 123,
    "order_name": "SO001",
    "current_state": "in_delivery",
    "timeline": [
      {
        "state": "validated",
        "label": "ยืนยันออเดอร์",
        "timestamp": "2026-02-01 10:00:00",
        "completed": true
      },
      {
        "state": "picking",
        "label": "กำลังจัดสินค้า",
        "timestamp": "2026-02-01 11:00:00",
        "completed": true
      },
      {
        "state": "packed",
        "label": "แพ็คเสร็จแล้ว",
        "timestamp": "2026-02-01 12:00:00",
        "completed": true
      },
      {
        "state": "in_delivery",
        "label": "กำลังจัดส่ง",
        "timestamp": "2026-02-01 14:00:00",
        "completed": false,
        "current": true
      },
      {
        "state": "delivered",
        "label": "จัดส่งสำเร็จ",
        "completed": false
      }
    ],
    "delivery_info": {
      "driver_name": "คุณสมศักดิ์",
      "vehicle": "กข-1234",
      "phone": "089-999-9999"
    }
  }
}
```

---

### 4. Search Orders

Search orders by query string and filters.

**Request:**
```json
{
  "action": "search",
  "line_user_id": "U1234567890abcdef",
  "query": "SO001",          // Search term
  "state": "sale",           // Optional: Filter by state
  "date_from": "2026-01-01", // Optional: Start date
  "date_to": "2026-12-31",   // Optional: End date
  "limit": 20,               // Optional: Results per page
  "offset": 0                // Optional: Pagination offset
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 123,
        "name": "SO001",
        "date_order": "2026-02-01",
        "state": "sale",
        "amount_total": 1500.00
      }
    ],
    "total": 1,
    "query": "SO001"
  }
}
```

---

## Error Responses

All errors return HTTP 400 with this format:

```json
{
  "success": false,
  "error": "Error message in Thai"
}
```

**Common Errors:**
- `Missing required field: action` - ไม่ระบุ action
- `Missing required field: line_user_id` - ไม่ระบุ LINE user ID
- `Missing required field: order_id` - ไม่ระบุ order ID (สำหรับ detail/tracking)
- `Invalid action: xxx` - action ไม่ถูกต้อง
- `กรุณาเชื่อมต่อบัญชี Odoo ก่อนใช้งาน` - User ยังไม่ได้เชื่อมต่อบัญชี
- `ไม่พบออเดอร์` - ไม่พบ order ที่ระบุ
- `ออเดอร์นี้ไม่ใช่ของคุณ` - Order ไม่ใช่ของ user นี้

---

## Usage Examples

### PHP Example

```php
<?php
$apiUrl = 'https://cny.re-ya.com/api/odoo-orders.php';

// List orders
$response = callAPI($apiUrl, [
    'action' => 'list',
    'line_user_id' => $lineUserId,
    'state' => 'sale',
    'limit' => 10
]);

if ($response['success']) {
    foreach ($response['data']['orders'] as $order) {
        echo "Order: {$order['name']} - {$order['amount_total']} บาท\n";
    }
}

function callAPI($url, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
```

### JavaScript Example

```javascript
// List orders
async function getOrders(lineUserId) {
  const response = await fetch('/api/odoo-orders.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      action: 'list',
      line_user_id: lineUserId,
      state: 'sale',
      limit: 10
    })
  });
  
  const data = await response.json();
  
  if (data.success) {
    return data.data.orders;
  } else {
    throw new Error(data.error);
  }
}

// Get order detail
async function getOrderDetail(lineUserId, orderId) {
  const response = await fetch('/api/odoo-orders.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      action: 'detail',
      line_user_id: lineUserId,
      order_id: orderId
    })
  });
  
  const data = await response.json();
  
  if (data.success) {
    return data.data;
  } else {
    throw new Error(data.error);
  }
}
```

---

## Testing

Run the test suite:

```bash
php test-odoo-orders-api.php
```

The test suite covers:
- ✓ List orders (basic)
- ✓ List orders with state filter
- ✓ List orders with date range
- ✓ List orders with pagination
- ✓ Order detail
- ✓ Order detail validation
- ✓ Order tracking
- ✓ Search orders
- ✓ Search with multiple filters
- ✓ Invalid action handling
- ✓ Missing parameter validation
- ✓ HTTP method validation

---

## Integration with LIFF

This API is designed to be called from LIFF pages:

1. **Orders List Page** (`/liff/odoo-orders.php`)
   - Calls `action: list` with filters
   - Displays paginated results
   - Links to detail page

2. **Order Detail Page** (`/liff/odoo-order-detail.php`)
   - Calls `action: detail` with order_id
   - Shows order lines and payment status
   - Links to tracking page

3. **Order Tracking Page** (`/liff/odoo-order-tracking.php`)
   - Calls `action: tracking` with order_id
   - Displays timeline visualization
   - Shows delivery info

---

## Notes

- All dates are in `YYYY-MM-DD` format
- All amounts are in Thai Baht (THB)
- Timestamps are in `YYYY-MM-DD HH:MM:SS` format (Asia/Bangkok timezone)
- The API automatically finds the LINE account for the user
- User must be linked to an Odoo partner account before using this API
- Rate limit: 60 requests per minute per LINE account

---

## Related Documentation

- [Odoo API Client](./ODOO_API_CLIENT.md)
- [User Linking API](./ODOO_USER_LINK_API.md)
- [Payment Status API](./ODOO_PAYMENT_STATUS_API_QUICK_REFERENCE.md)
- [Slip Upload API](./ODOO_SLIP_UPLOAD_API_QUICK_REFERENCE.md)
