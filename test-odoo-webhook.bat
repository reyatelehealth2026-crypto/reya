@echo off
REM Test Odoo Webhook Script for Windows
REM Usage: test-odoo-webhook.bat [webhook_secret]
REM Example: test-odoo-webhook.bat your_secret_key_here

echo ========================================
echo Testing Odoo Webhook
echo ========================================
echo.

REM Check if webhook secret is provided
if "%~1"=="" (
    echo ERROR: Webhook secret is required!
    echo.
    echo Usage: test-odoo-webhook.bat [webhook_secret]
    echo Example: test-odoo-webhook.bat your_secret_key_here
    echo.
    echo You can find ODOO_WEBHOOK_SECRET in config/config.php
    pause
    exit /b 1
)

set WEBHOOK_SECRET=%~1

REM Get current Unix timestamp
powershell -Command "$timestamp = [int][double]::Parse((Get-Date -UFormat %%s)); Write-Host $timestamp" > temp_timestamp.txt
set /p TIMESTAMP=<temp_timestamp.txt
del temp_timestamp.txt

REM Create test payload (compact JSON for signature)
echo {"event":"order.validated","data":{"order_id":12345,"order_ref":"SO001","order_name":"Sales Order 001","amount_total":1500.00,"state":"sale","customer":{"partner_id":67890,"name":"Test Customer"},"salesperson":{"partner_id":1,"name":"Admin"},"expected_date":"2026-02-20"},"notify":{"customer":true,"salesperson":false},"message_template":{"customer":{"th":"ออเดอร์ {order_ref} ได้รับการยืนยันแล้ว ยอดเงิน {amount} บาท"},"salesperson":{"th":"ออเดอร์ใหม่ {order_ref} จากลูกค้า {customer_name}"}}} > test-payload.json

echo Payload created: test-payload.json
echo.

REM Display payload
echo Payload content:
type test-payl.json
echo.
echo.

REM Generate HMAC-SHA256 signature using PowerShell
echo Generating signature...
powershell -Command "$payload = Get-Content -Path 'test-payload.json' -Raw; $hmac = New-Object System.Security.Cryptography.HMACSHA256; $hmac.Key = [Text.Encoding]::UTF8.GetBytes('%WEBHOOK_SECRET%'); $hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload)); $signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-', '').ToLower(); Write-Host $signature" > temp_signature.txt
set /p SIGNATURE=<temp_signature.txt
del temp_signature.txt

echo Signature: %SIGNATURE%
echo.

REM Execute curl request
echo Sending webhook request...
echo URL: https://cny.re-ya.com/api/webhook/odoo.php
echo Event: order.validated
echo Timestamp: %TIMESTAMP%
echo Delivery-Id: wh_test_%TIMESTAMP%
echo.

curl -X POST "https://cny.re-ya.com/api/webhook/odoo.php" ^
  -H "Content-Type: application/json" ^
  -H "X-Odoo-Signature: %SIGNATURE%" ^
  -H "X-Odoo-Timestamp: %TIMESTAMP%" ^
  -H "X-Odoo-Event: order.validated" ^
  -H "X-Odoo-Delivery-Id: wh_test_%TIMESTAMP%" ^
  -d @test-payload.json

echo.
echo.
echo ========================================
echo Test completed
echo ========================================
echo.
echo Cleanup temp files...
if exist test-payload.json del test-payload.json
echo Done!
echo.
pause
