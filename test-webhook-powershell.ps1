# Test Odoo Webhook Signature with PowerShell
# Run this script: .\test-webhook-powershell.ps1

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Odoo Webhook Signature Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Configuration
$secret = 'cny_reya_webhook_3226e48c7c053b4af44011651f2d3cfb'
$url = 'https://cny.re-ya.com/api/webhook/test-signature.php'

# Create payload (use single quotes to avoid variable expansion)
$payload = '{"event":"order.validated","data":{"order_id":9999,"order_name":"TEST-001","order_ref":"SO-TEST-001","customer":{"partner_id":100,"name":"Test Customer"},"amount_total":1500.00}}'

Write-Host "Payload:" -ForegroundColor Yellow
Write-Host $payload
Write-Host ""

# Get Unix timestamp (correct method for Windows)
$timestamp = [int]([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())
Write-Host "Timestamp: $timestamp" -ForegroundColor Yellow
Write-Host ""

# Calculate HMAC-SHA256 signature
Write-Host "Calculating signature..." -ForegroundColor Yellow
$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [Text.Encoding]::UTF8.GetBytes($secret)
$hash = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload))
$signature = 'sha256=' + [BitConverter]::ToString($hash).Replace('-','').ToLower()

Write-Host "Signature: $signature" -ForegroundColor Green
Write-Host ""

# Prepare headers
$headers = @{
    'Content-Type' = 'application/json; charset=utf-8'
    'X-Odoo-Signature' = $signature
    'X-Odoo-Timestamp' = $timestamp.ToString()
    'X-Odoo-Event' = 'order.validated'
    'X-Odoo-Delivery-Id' = "test-$timestamp"
}

Write-Host "Sending POST request..." -ForegroundColor Yellow
Write-Host ""

try {
    # Send POST request using Invoke-RestMethod
    $response = Invoke-RestMethod -Uri $url -Method Post -Headers $headers -Body $payload -ContentType 'application/json; charset=utf-8'
    
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "Response:" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 10
    Write-Host ""
    
    # Check result
    if ($response.validation.payload_only_match -eq $true) {
        Write-Host "✅ SUCCESS! Signature validation passed!" -ForegroundColor Green
    } else {
        Write-Host "❌ FAILED! Signature validation failed!" -ForegroundColor Red
    }
    
} catch {
    Write-Host "❌ Error occurred:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test Complete" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
