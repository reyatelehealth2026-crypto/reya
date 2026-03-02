# PowerShell script to test webhook signature generation
$secret = "cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb"
$payload = '{"event":"order.validated","data":{"order_id":12345,"order_ref":"SO001","order_name":"Sales Order 001","amount_total":1500.00,"state":"sale","customer":{"partner_id":67890,"name":"Test Customer"},"salesperson":{"partner_id":1,"name":"Admin"},"expected_date":"2026-02-20"},"notify":{"customer":true,"salesperson":false},"message_template":{"customer":{"th":"Order confirmed"},"salesperson":{"th":"New order"}}}'

Write-Host "Webhook Secret: $secret"
Write-Host "Payload: $payload"
Write-Host "Payload length: $($payload.Length)"

$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [Text.Encoding]::UTF8.GetBytes($secret)
$hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload))
$signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-', '').ToLower()

Write-Host "Expected Signature: $signature"

# Test with timestamp
$timestamp = [int][double]::Parse((Get-Date -UFormat %s))
$legacyData = "$timestamp.$payload"
$hmac2 = New-Object System.Security.Cryptography.HMACSHA256
$hmac2.Key = [Text.Encoding]::UTF8.GetBytes($secret)
$hash2 = $hmac2.ComputeHash([Text.Encoding]::UTF8.GetBytes($legacyData))
$legacySignature = 'sha256=' + [BitConverter]::ToString($hash2).Replace('-', '').ToLower()

Write-Host "Legacy Signature (timestamp.payload): $legacySignature"
Write-Host "Timestamp: $timestamp"
