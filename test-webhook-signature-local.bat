@echo off
REM Test Webhook Signature Verification Locally
REM This script tests the signature verification endpoint

echo ========================================
echo Testing Odoo Webhook Signature
echo ========================================
echo.

REM Configuration
set SECRET=cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb
set TIMESTAMP=%date:~-4%%date:~3,2%%date:~0,2%%time:~0,2%%time:~3,2%%time:~6,2%
set TIMESTAMP=%TIMESTAMP: =0%

REM Create test payload
set PAYLOAD={"event":"order.validated","data":{"order_id":9999,"order_name":"TEST-001","order_ref":"SO-TEST-001","customer":{"partner_id":100,"name":"Test Customer"},"amount_total":1500.00}}

echo Payload: %PAYLOAD%
echo.

REM Calculate signature using PowerShell
echo Calculating signature...
powershell -Command "$secret='%SECRET%'; $payload='%PAYLOAD%'; $hmac = New-Object System.Security.Cryptography.HMACSHA256; $hmac.Key = [Text.Encoding]::UTF8.GetBytes($secret); $hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload)); $signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-','').ToLower(); Write-Host $signature" > temp_sig.txt
set /p SIGNATURE=<temp_sig.txt
del temp_sig.txt

echo Signature: %SIGNATURE%
echo.

REM Get current Unix timestamp
for /f %%i in ('powershell -Command "[int][double]::Parse((Get-Date -UFormat %%s))"') do set UNIX_TIMESTAMP=%%i

echo Timestamp: %UNIX_TIMESTAMP%
echo.

REM Test with local endpoint
echo Testing signature verification...
echo.

curl -X POST "https://cny.re-ya.com/api/webhook/test-signature.php" ^
  -H "Content-Type: application/json; charset=utf-8" ^
  -H "X-Odoo-Signature: %SIGNATURE%" ^
  -H "X-Odoo-Timestamp: %UNIX_TIMESTAMP%" ^
  -H "X-Odoo-Event: order.validated" ^
  -H "X-Odoo-Delivery-Id: test-%UNIX_TIMESTAMP%" ^
  -d "%PAYLOAD%"

echo.
echo.
echo ========================================
echo Test Complete
echo ========================================
pause
