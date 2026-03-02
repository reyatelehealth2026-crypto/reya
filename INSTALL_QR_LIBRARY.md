# QR Code Library Installation Instructions

## Quick Start

This project requires the `endroid/qr-code` library for generating PromptPay QR codes in the Odoo integration.

### Step 1: Install Composer Dependencies

```bash
cd re-ya
composer install
```

If you don't have Composer installed, download it first:
- Windows: https://getcomposer.org/Composer-Setup.exe
- Linux/Mac: https://getcomposer.org/download/

### Step 2: Verify Installation

Run the test script:

```bash
php test-qr-generation.php
```

You should see:
```
=== QR Code Generation Test ===

Test 1: Checking if endroid/qr-code is installed...
✓ Library is installed

Test 2: Initializing QRCodeGenerator...
✓ QRCodeGenerator initialized

Test 3: Generating PromptPay QR Code...
✓ QR Code generated successfully
...
```

### Step 3: Check Upload Directory

Ensure the uploads directory exists and is writable:

```bash
mkdir -p uploads/qrcodes
chmod 755 uploads/qrcodes
```

## What Was Installed?

The following has been added to your project:

1. **composer.json** - Updated with `endroid/qr-code` dependency
2. **classes/QRCodeGenerator.php** - Helper class for generating QR codes
3. **docs/ODOO_QR_CODE_SETUP.md** - Detailed setup and usage guide
4. **test-qr-generation.php** - Test script to verify installation

## Library Details

- **Package**: endroid/qr-code
- **Version**: ^4.8
- **Purpose**: Generate PromptPay QR codes from EMVCo payloads
- **Documentation**: https://github.com/endroid/qr-code

## Usage Example

```php
<?php
require_once 'vendor/autoload.php';
require_once 'classes/QRCodeGenerator.php';

$generator = new QRCodeGenerator();

// Generate PromptPay QR from Odoo webhook
$emvcoPayload = $data['qr_payment']['emvco_payload'];
$result = $generator->generatePromptPayQR($emvcoPayload, 'order_12345');

if ($result['success']) {
    // Use the QR code URL in LINE Flex Message
    $qrCodeUrl = $result['url'];
    echo "QR Code: $qrCodeUrl";
}
```

## Troubleshooting

### "composer: command not found"

Install Composer:
```bash
# Windows
# Download and run: https://getcomposer.org/Composer-Setup.exe

# Linux/Mac
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### "Class 'Endroid\QrCode\QrCode' not found"

Run composer install:
```bash
cd re-ya
composer install
```

### "Failed to create directory"

Fix permissions:
```bash
chmod 755 uploads
chmod 755 uploads/qrcodes
```

### QR Code not displaying in browser

Check web server configuration:
1. Ensure `uploads/qrcodes/` is accessible via HTTP
2. Check `.htaccess` or nginx config
3. Verify file permissions (644 for files, 755 for directories)

## Next Steps

After installation:

1. ✓ Library installed
2. ✓ QRCodeGenerator class created
3. ✓ Test script verified
4. → Continue with Odoo integration tasks
5. → Implement BDO webhook handler (task 12.1)
6. → Test PromptPay QR generation with real Odoo data

## Support

For detailed documentation, see:
- `docs/ODOO_QR_CODE_SETUP.md` - Complete setup guide
- `classes/QRCodeGenerator.php` - Class documentation
- https://github.com/endroid/qr-code - Library documentation

## Manual Installation (Alternative)

If composer is not available, you can manually download the library:

1. Download from: https://github.com/endroid/qr-code/releases
2. Extract to: `re-ya/vendor/endroid/qr-code/`
3. Update autoloader or include manually

**Note**: Using Composer is strongly recommended for dependency management.
