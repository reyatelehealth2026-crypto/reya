# Odoo Integration - QR Code Setup Guide

## Overview
This guide explains how to install and configure the QR Code library for PromptPay payment QR codes in the Odoo integration.

## Library Selection
We use **endroid/qr-code** version 4.8+ for the following reasons:
- Most popular and well-maintained PHP QR code library
- Easy to use with clean API
- Supports various output formats (PNG, SVG, Base64)
- Works perfectly with EMVCo PromptPay payloads
- PSR-compliant and modern PHP 8.0+ compatible

## Installation Steps

### 1. Install via Composer

```bash
cd re-ya
composer install
```

This will install the `endroid/qr-code` library as specified in `composer.json`.

### 2. Verify Installation

Check that the library is installed:

```bash
composer show endroid/qr-code
```

You should see version 4.8 or higher.

### 3. Create Upload Directory

The QR codes will be saved to `re-ya/uploads/qrcodes/`. This directory is automatically created by the `QRCodeGenerator` class, but you can create it manually:

```bash
mkdir -p uploads/qrcodes
chmod 755 uploads/qrcodes
```

### 4. Configure Web Server

Ensure the `uploads/qrcodes/` directory is accessible via web:
- URL: `https://cny.re-ya.com/uploads/qrcodes/`
- Directory: `/path/to/re-ya/uploads/qrcodes/`

Add to `.htaccess` if needed:

```apache
<Directory "/path/to/re-ya/uploads/qrcodes">
    Options -Indexes
    AllowOverride None
    Require all granted
</Directory>
```

## Usage Examples

### Basic Usage

```php
<?php
require_once __DIR__ . '/classes/QRCodeGenerator.php';

$generator = new QRCodeGenerator();

// Generate PromptPay QR from EMVCo payload
$emvcoPayload = "00020101021129370016A000000677010111011300668123456785802TH5303764540510.006304ABCD";
$result = $generator->generatePromptPayQR($emvcoPayload, 'order_12345');

if ($result['success']) {
    echo "QR Code URL: " . $result['url'];
    echo "QR Code Path: " . $result['path'];
} else {
    echo "Error: " . $result['error'];
}
```

### Generate Base64 QR (for inline display)

```php
<?php
$result = $generator->generateQRBase64($emvcoPayload, 300);

if ($result['success']) {
    echo '<img src="' . $result['base64'] . '" alt="PromptPay QR Code">';
}
```

### Cleanup Old QR Codes

```php
<?php
// Delete QR codes older than 7 days
$deleted = $generator->cleanupOldQRCodes(7);
echo "Deleted $deleted old QR codes";
```

## Integration with Odoo Webhook

When receiving a BDO confirmed webhook from Odoo:

```php
<?php
// In OdooWebhookHandler::handleBdoConfirmed()
$qrPayment = $data['qr_payment'] ?? null;

if ($qrPayment && !empty($qrPayment['emvco_payload'])) {
    $generator = new QRCodeGenerator();
    $qrResult = $generator->generatePromptPayQR(
        $qrPayment['emvco_payload'],
        'bdo_' . $data['bdo_id']
    );
    
    if ($qrResult['success']) {
        // Send LINE message with QR code
        $flexMessage = OdooFlexTemplates::bdoPaymentRequest($data, $qrResult['url']);
        $this->sendLineMessage($lineUserId, $flexMessage);
    }
}
```

## QR Code Specifications

### PromptPay QR Code
- **Format**: PNG
- **Size**: 300x300 pixels (default)
- **Error Correction**: High (30%)
- **Margin**: 10 pixels
- **Encoding**: UTF-8

### EMVCo Payload Format
The EMVCo payload from Odoo contains:
- Merchant ID (PromptPay ID)
- Amount
- Reference number
- Checksum

Example:
```
00020101021129370016A000000677010111011300668123456785802TH5303764540510.006304ABCD
```

## Troubleshooting

### Error: "Class 'Endroid\QrCode\QrCode' not found"
**Solution**: Run `composer install` to install dependencies.

### Error: "Failed to create directory"
**Solution**: Check directory permissions:
```bash
chmod 755 uploads
chmod 755 uploads/qrcodes
```

### QR Code not displaying
**Solution**: Check web server configuration and ensure the uploads directory is accessible via HTTP.

### QR Code not scannable
**Solution**: 
- Verify the EMVCo payload is correct
- Increase QR code size (e.g., 400x400)
- Check error correction level

## Cron Job for Cleanup

Add to crontab to cleanup old QR codes daily:

```bash
# Cleanup QR codes older than 7 days (runs daily at 2 AM)
0 2 * * * cd /path/to/re-ya && php -r "require 'classes/QRCodeGenerator.php'; \$g = new QRCodeGenerator(); \$g->cleanupOldQRCodes(7);"
```

## Security Considerations

1. **Directory Listing**: Disable directory listing for uploads/qrcodes/
2. **File Permissions**: Set appropriate permissions (755 for directories, 644 for files)
3. **Cleanup**: Regularly delete old QR codes to save disk space
4. **Validation**: Always validate EMVCo payload before generating QR codes

## Testing

Test QR code generation:

```bash
cd re-ya
php -r "
require 'vendor/autoload.php';
require 'classes/QRCodeGenerator.php';
\$g = new QRCodeGenerator();
\$r = \$g->generatePromptPayQR('00020101021129370016A000000677010111011300668123456785802TH5303764540510.006304ABCD', 'test_123');
echo json_encode(\$r, JSON_PRETTY_PRINT);
"
```

## References

- [endroid/qr-code Documentation](https://github.com/endroid/qr-code)
- [PromptPay QR Code Specification](https://www.bot.or.th/Thai/PaymentSystems/StandardPS/Documents/ThaiQRCode_Payment_Standard.pdf)
- [EMVCo QR Code Specification](https://www.emvco.com/emv-technologies/qrcodes/)

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the endroid/qr-code documentation
3. Contact the development team
