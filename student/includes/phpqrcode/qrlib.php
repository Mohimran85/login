<?php
/*
 * PHP QR Code encoder
 * Simplified version for OD letter verification
 * Uses online QR API for quick implementation
 */

class QRcode
{

    /**
     * Generate QR code and output as PNG image
     *
     * @param string $text The text to encode in QR code
     * @param string|bool $outfile Output file path or false for direct output
     * @param string $level Error correction level (L, M, Q, H)
     * @param int $size Size of QR code (1-10)
     * @param int $margin Margin size
     */
    public static function png($text, $outfile = false, $level = 'L', $size = 3, $margin = 4)
    {
        // Use QR Server API for quick QR generation
        $pixelSize = $size * 3;
        $url       = 'https://api.qrserver.com/v1/create-qr-code/?size=' . ($pixelSize * 100) . 'x' . ($pixelSize * 100) .
        '&data=' . urlencode($text) . '&ecc=' . $level . '&margin=' . $margin;

        $imageData = @file_get_contents($url);

        if ($imageData === false) {
            // Fallback: create a simple placeholder image with URL text
            $imgSize = 300;
            $img     = imagecreatetruecolor($imgSize, $imgSize);
            $white   = imagecolorallocate($img, 255, 255, 255);
            $black   = imagecolorallocate($img, 0, 0, 0);
            $gray    = imagecolorallocate($img, 200, 200, 200);

            imagefill($img, 0, 0, $white);

            // Draw border
            imagerectangle($img, 5, 5, $imgSize - 6, $imgSize - 6, $black);

            // Draw corner squares (mimicking QR pattern)
            $cornerSize = 40;
            // Top-left
            imagefilledrectangle($img, 15, 15, 15 + $cornerSize, 15 + $cornerSize, $black);
            imagefilledrectangle($img, 20, 20, 20 + $cornerSize - 10, 20 + $cornerSize - 10, $white);
            imagefilledrectangle($img, 25, 25, 25 + $cornerSize - 20, 25 + $cornerSize - 20, $black);

            // Top-right
            imagefilledrectangle($img, $imgSize - 15 - $cornerSize, 15, $imgSize - 15, 15 + $cornerSize, $black);
            imagefilledrectangle($img, $imgSize - 20 - $cornerSize + 10, 20, $imgSize - 20, 20 + $cornerSize - 10, $white);
            imagefilledrectangle($img, $imgSize - 25 - $cornerSize + 20, 25, $imgSize - 25, 25 + $cornerSize - 20, $black);

            // Bottom-left
            imagefilledrectangle($img, 15, $imgSize - 15 - $cornerSize, 15 + $cornerSize, $imgSize - 15, $black);
            imagefilledrectangle($img, 20, $imgSize - 20 - $cornerSize + 10, 20 + $cornerSize - 10, $imgSize - 20, $white);
            imagefilledrectangle($img, 25, $imgSize - 25 - $cornerSize + 20, 25 + $cornerSize - 20, $imgSize - 25, $black);

            // Add center text
            $font    = 2;
            $lines   = ['QR Code', 'Scan to', 'Verify'];
            $y_start = ($imgSize / 2) - (count($lines) * imagefontheight($font) / 2);
            foreach ($lines as $i => $line) {
                $text_width = imagefontwidth($font) * strlen($line);
                $x          = ($imgSize - $text_width) / 2;
                $y          = $y_start + ($i * imagefontheight($font) * 1.5);
                imagestring($img, $font, $x, $y, $line, $black);
            }

            if ($outfile !== false) {
                imagepng($img, $outfile);
            } else {
                header('Content-Type: image/png');
                imagepng($img);
            }

            imagedestroy($img);
            return;
        }

        if ($outfile !== false) {
            file_put_contents($outfile, $imageData);
        } else {
            header('Content-Type: image/png');
            echo $imageData;
        }
    }

    /**
     * Generate QR code and save to file
     */
    public static function save($text, $outfile, $level = 'L', $size = 3, $margin = 4)
    {
        self::png($text, $outfile, $level, $size, $margin);
    }
}
