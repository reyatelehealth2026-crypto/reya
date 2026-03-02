@echo off
REM Simple curl test for webhook signature
REM Uses PowerShell to calculate HMAC-SHA256

setlocal enabledelayedexpansion

echo ========================================
echo Odoo Webhook Signature Test
echo ========================================
echo.

REM Configuration
set "SECRET=cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb"
set "URL=https://cny.re-ya.com/api/webhook/test-signature.php"

REM Test payload (compact JSON, no spaces)
set "PAYLOAD={\"event\":\"order.validated\",\"data\":{\"order_id\":9999,\"order_name\":\"TEST-001\",\"order_ref\":\"SO-TEST-001\",\"customer\":{\"partner_id\":100,\"name\":\"Test Customer\"},\"amount_total\":1500.00}}"

echo Payload:
echo %PAYLOAD%
echo.

REM Get Unix timestamp
for /f %%i in ('powershell -Command "[int][double]::Parse((Get-Date -UFormat %%s))"') do set TIMESTAMP=%%i

echo Timestamp: %TIMESTAMP%
echo.

REM Calculate signature using PowerShell
echo Calculating signature...
powershell -NoProfile -Command ^
"$secret = '%SECRET%'; ^
$payload = '%PAYLOAD%'; ^
$hmac = New-Object System.Security.Cryptography.HMACSHA256; ^
$hmac.Key = [Text.Encoding]::UTF8.GetBytes($secret); ^
$hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload)); ^
$signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-','').ToLower(); ^
Write-Output $signature" > temp_signature.txt

set /p SIGNATURE=<temp_signature.txt
del temp_signature.txt

echo Signature: %SIGNATURE%
echo.

REM Send request
echo Sending test request...
echo.

curl -X POST "%URL%" ^
  -H "Content-Type: application/json; charset=utf-8" ^
  -H "X-Odoo-Signature: %SIGNATURE%" ^
  -H "X-Odoo-Timestamp: %TIMESTAMP%" ^
  -H "X-Odoo-Event: order.validated" ^
  -H "X-Odoo-Delivery-Id: test-%TIMESTAMP%" ^
  -d "%PAYLOAD%"

echo.
echo.
echo ========================================
echo Test Complete
echo ========================================
echo.
pause
