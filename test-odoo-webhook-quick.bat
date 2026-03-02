@echo off
REM Quick Test Odoo Webhook Script (with embedded secret)
REM Usage: test-odoo-webhook-quick.bat

echo ========================================
echo Testing Odoo Webhook (Quick Test)
echo ========================================
echo.

set WEBHOOK_SECRET=cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb

REM Create test payload using PowerShell (ensures no extra newlines)
echo Generating payload and signature...
powershell -Command "$payload = '{\"event\":\"order.validated\",\"data\":{\"order_id\":12345,\"order_ref\":\"SO001\",\"order_name\":\"Sales Order 001\",\"amount_total\":1500.00,\"state\":\"sale\",\"customer\":{\"partner_id\":67890,\"name\":\"Test Customer\"},\"salesperson\":{\"partner_id\":1,\"name\":\"Admin\"},\"expected_date\":\"2026-02-20\"},\"notify\":{\"customer\":true,\"salesperson\":false},\"message_template\":{\"customer\":{\"th\":\"Order confirmed\"},\"salesperson\":{\"th\":\"New order\"}}}'; $utf8NoBom = New-Object System.Text.UTF8Encoding($false); [System.IO.File]::WriteAllText('test-payload.json', $payload, $utf8NoBom); Write-Host 'Payload created:'; Write-Host $payload; Write-Host ''; $timestamp = [int][double]::Parse((Get-Date -UFormat %%s)); $hmac = New-Object System.Security.Cryptography.HMACSHA256; $hmac.Key = [Text.Encoding]::UTF8.GetBytes('%WEBHOOK_SECRET%'); $hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload)); $signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-', '').ToLower(); $headers = @{'X-Odoo-Signature'=$signature; 'X-Odoo-Timestamp'=$timestamp; 'X-Odoo-Delivery-Id'='test-' + $timestamp; 'X-Odoo-Event'='order.validated'; 'Content-Type'='application/json'}; Write-Host 'Signature:' $signature; Write-Host 'Timestamp:' $timestamp; Write-Host 'Sending request...'; try { $response = Invoke-RestMethod -Uri 'https://cny.re-ya.com/api/webhook/odoo.php' -Method POST -Headers $headers -Body $payload -TimeoutSec 10; $response | ConvertTo-Json -Compress } catch { $err = $_.Exception.Response.GetResponseStream(); $reader = New-Object System.IO.StreamReader($err); $reader.BaseStream.Position = 0; $reader.DiscardBufferedData(); $body = $reader.ReadToEnd(); Write-Host $body }"

echo.
echo.
echo ========================================
echo Test completed
echo ========================================
echo.
echo Cleanup...
if exist test-payload.json del test-payload.json
echo Done!
echo.
pause
