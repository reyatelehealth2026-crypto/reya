@echo off
chcp 65001 >nul
REM Simple Odoo Webhook Test
echo ========================================
echo Testing Odoo Webhook (Simple Version)
echo ========================================
echo.

set WEBHOOK_SECRET=cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb

REM Get Unix timestamp
powershell -Command "[int][double]::Parse((Get-Date -UFormat %%s))" > temp_ts.txt
set /p TIMESTAMP=<temp_ts.txt
del temp_ts.txt

REM Create simple payload file
powershell -Command "$json = @{event='order.validated';data=@{order_id=12345;order_ref='SO001';order_name='Sales Order 001';amount_total=1500.00;state='sale';customer=@{partner_id=67890;name='Test Customer'};salesperson=@{partner_id=1;name='Admin'};expected_date='2026-02-20'};notify=@{customer=$true;salesperson=$false};message_template=@{customer=@{th='Order confirmed'};salesperson=@{th='New order'}}} | ConvertTo-Json -Depth 10 -Compress | Out-File -FilePath 'payload.json' -Encoding UTF8 -NoNewline"

echo Payload created
echo.

REM Generate signature
powershell -Command "$payload = [System.IO.File]::ReadAllText('payload.json', [System.Text.Encoding]::UTF8); $hmac = New-Object System.Security.Cryptography.HMACSHA256; $hmac.Key = [Text.Encoding]::UTF8.GetBytes('%WEBHOOK_SECRET%'); $hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload)); $signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-', '').ToLower(); Write-Host $signature" > temp_sig.txt
set /p SIGNATURE=<temp_sig.txt
del temp_sig.txt

echo Signature: %SIGNATURE%
echo Timestamp: %TIMESTAMP%
echo.
echo Payload:
type payload.json
echo.
echo.

REM Send request
echo Sending webhook...
curl -X POST "https://cny.re-ya.com/api/webhook/odoo.php" ^
  -H "Content-Type: application/json; charset=utf-8" ^
  -H "X-Odoo-Signature: %SIGNATURE%" ^
  -H "X-Odoo-Timestamp: %TIMESTAMP%" ^
  -H "X-Odoo-Event: order.validated" ^
  -H "X-Odoo-Delivery-Id: wh_test_%TIMESTAMP%" ^
  --data-binary "@payload.json"

echo.
echo.
echo ========================================
echo Test completed
echo ========================================
echo.
if exist payload.json del payload.json
pause
