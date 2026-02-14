<?php
/**
 * Image & PDF Compression Utility
 * Compresses uploaded images (JPG, PNG) to WebP format and optimizes PDFs
 * Saves 60-80% disk space while maintaining quality
 */

class FileCompressor
{

    /**
     * Compress and convert image to WebP format
     * @param string $source_path - Path to original image file
     * @param string $destination_path - Path to save compressed WebP (without extension)
     * @param int $quality - Compression quality (1-100, default 80)
     * @return string|false - Path to compressed file or false on failure
     */
    public static function compressImage($source_path, $destination_path, $quality = 80)
    {
        // Check if GD library is available
        if (! extension_loaded('gd')) {
            return false;
        }

        // Get image info
        $image_info = @getimagesize($source_path);
        if ($image_info === false) {
            return false;
        }

        $mime_type = $image_info['mime'];

        // Create image resource based on type
        $image = null;
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }

        if ($image === false) {
            return false;
        }

        // Check if WebP is supported
        if (function_exists('imagewebp')) {
            // Convert to WebP for maximum compression
            $webp_path = $destination_path . '.webp';
            $success   = @imagewebp($image, $webp_path, $quality);

            if ($success && file_exists($webp_path)) {
                imagedestroy($image);
                // Delete original file to save space
                @unlink($source_path);
                return $webp_path;
            }
            // WebP failed, try JPEG fallback
        }

        // Fallback: compress as JPEG if WebP not supported or failed
        $jpg_path = $destination_path . '.jpg';
        $success  = @imagejpeg($image, $jpg_path, $quality);
        imagedestroy($image);

        if ($success && file_exists($jpg_path)) {
            @unlink($source_path);
            return $jpg_path;
        }

        return false;
    }

    /**
     * Compress PDF using Ghostscript (if available)
     * @param string $source_path - Path to original PDF
     * @param string $destination_path - Path to save compressed PDF
     * @return string|false - Path to compressed file or false
     */
    public static function compressPDF($source_path, $destination_path)
    {
        // Check if Ghostscript is available
        $gs_path = self::findGhostscript();

        if ($gs_path === false) {
            // Ghostscript not available, just copy the file
            if (copy($source_path, $destination_path)) {
                @unlink($source_path);
                return $destination_path;
            }
            return false;
        }

        // Compress PDF using Ghostscript with proper escaping
        $command = sprintf(
            '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
            escapeshellarg($gs_path),
            escapeshellarg($destination_path),
            escapeshellarg($source_path)
        );

        exec($command, $output, $return_var);

        if ($return_var === 0 && file_exists($destination_path)) {
            @unlink($source_path);
            return $destination_path;
        }

        // Compression failed, just copy
        if (copy($source_path, $destination_path)) {
            @unlink($source_path);
            return $destination_path;
        }

        return false;
    }

    /**
     * Find Ghostscript executable path
     * @return string|false
     */
    private static function findGhostscript()
    {
        // Common Ghostscript paths
        $possible_paths = [
            'gs',       // Linux/Mac
            'gswin64c', // Windows 64-bit
            'gswin32c', // Windows 32-bit
            '/usr/bin/gs',
            '/usr/local/bin/gs',
            'C:\\Program Files\\gs\\gs9.56.1\\bin\\gswin64c.exe',
            'C:\\Program Files (x86)\\gs\\gs9.56.1\\bin\\gswin32c.exe',
        ];

        foreach ($possible_paths as $path) {
            exec("\"$path\" --version 2>&1", $output, $return_var);
            if ($return_var === 0) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Smart file compression - detects type and compresses accordingly
     * @param string $uploaded_tmp_path - Temp path of uploaded file
     * @param string $final_path - Final destination path (without extension)
     * @param string $file_extension - Original file extension
     * @param int $quality - Image quality (1-100)
     * @return array - ['success' => bool, 'path' => string, 'original_size' => int, 'compressed_size' => int]
     */
    public static function compressUploadedFile($uploaded_tmp_path, $final_path, $file_extension, $quality = 80)
    {
        // Validate file exists and is readable
        if (! is_file($uploaded_tmp_path) || ! is_readable($uploaded_tmp_path)) {
            error_log("FileCompressor: Unable to read file: $uploaded_tmp_path");
            return [
                'success'         => false,
                'error'           => 'Unable to read uploaded file',
                'path'            => null,
                'original_size'   => 0,
                'compressed_size' => 0,
                'savings_percent' => 0,
            ];
        }

        $original_size = filesize($uploaded_tmp_path);
        if ($original_size === false) {
            error_log("FileCompressor: Unable to get file size: $uploaded_tmp_path");
            $original_size = 0;
        }

        $file_extension = strtolower($file_extension);

        $result = [
            'success'         => false,
            'path'            => null,
            'original_size'   => $original_size,
            'compressed_size' => 0,
            'savings_percent' => 0,
        ];

        // Handle images
        if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $compressed_path = self::compressImage($uploaded_tmp_path, $final_path, $quality);

            if ($compressed_path !== false) {
                $result['success']         = true;
                $result['path']            = $compressed_path;
                $result['compressed_size'] = filesize($compressed_path);
                // Prevent division by zero
                if ($original_size > 0) {
                    $result['savings_percent'] = round((1 - $result['compressed_size'] / $original_size) * 100, 2);
                }
                return $result;
            }
        }

        // Handle PDFs
        if ($file_extension === 'pdf') {
            $final_pdf_path  = $final_path . '.pdf';
            $compressed_path = self::compressPDF($uploaded_tmp_path, $final_pdf_path);

            if ($compressed_path !== false) {
                $result['success']         = true;
                $result['path']            = $compressed_path;
                $result['compressed_size'] = filesize($compressed_path);
                // Prevent division by zero
                if ($original_size > 0) {
                    $result['savings_percent'] = round((1 - $result['compressed_size'] / $original_size) * 100, 2);
                }
                return $result;
            }
        }

        // Fallback: copy or rename the file
        $final_file_path = $final_path . '.' . $file_extension;
        // Try rename first (for non-uploaded files), then copy
        if (is_uploaded_file($uploaded_tmp_path)) {
            $success = move_uploaded_file($uploaded_tmp_path, $final_file_path);
        } else {
            $success = rename($uploaded_tmp_path, $final_file_path);
            if (! $success) {
                $success = copy($uploaded_tmp_path, $final_file_path);
                if ($success) {
                    @unlink($uploaded_tmp_path);
                }
            }
        }

        if ($success) {
            $result['success']         = true;
            $result['path']            = $final_file_path;
            $result['compressed_size'] = $original_size;
            return $result;
        }

        return $result;
    }

    /**
     * Format file size in human-readable format
     * @param int $bytes
     * @return string
     */
    public static function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
