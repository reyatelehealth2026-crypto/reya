<?php
/**
 * QR Code Generator Stub
 * Simplified version for testing without actual QR library
 */

class QRCodeGenerator
{
    public function generatePromptPayQR($emvcoPayload, $reference)
    {
        // Return mock QR code result
        $filename = 'qr_' . md5($reference) . '.png';
        $path = __DIR__ . '/../uploads/qr/' . $filename;
        $url = '/uploads/qr/' . $filename;

        // Create directory if not exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Create a simple placeholder image (1x1 transparent PNG)
        $img = imagecreatetruecolor(300, 300);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        imagefill($img, 0, 0, $white);
        imagestring($img, 5, 50, 140, 'QR Code Placeholder', $black);

        imagepng($img, $path);
        imagedestroy($img);

        return [
            'success' => true,
            'url' => $url,
            'path' => $path,
            'filename' => $filename
        ];
    }
}
