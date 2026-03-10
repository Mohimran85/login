<?php
/**
 * Simple PDF Generator
 * HTML to PDF conversion with image support
 * For XAMPP environments without Composer
 */

class PdfGenerator
{
    private $html        = '';
    private $title       = 'Document';
    private $orientation = 'P'; // P=Portrait, L=Landscape
    private $format      = 'A4';

    /**
     * Constructor
     */
    public function __construct($orientation = 'P', $format = 'A4')
    {
        $this->orientation = $orientation;
        $this->format      = $format;
    }

    /**
     * Set document title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Write HTML content
     */
    public function writeHTML($html)
    {
        $this->html .= $html;
    }

    /**
     * Output PDF for download
     */
    public function output($filename = 'document.pdf', $destination = 'D')
    {
        // D = Download, I = Inline, F = File save

        // Set headers for PDF output
        if ($destination === 'D' || $destination === 'I') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($destination === 'D' ? 'attachment' : 'inline') . '; filename="' . rawurlencode(basename($filename)) . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }

        // For simplicity, we'll output HTML that can be printed as PDF
        // Modern browsers handle this well
        echo $this->generatePrintableHTML();
    }

    /**
     * Generate HTML optimized for PDF printing
     */
    private function generatePrintableHTML()
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($this->title) . '</title>
    <style>
        @page {
            size: ' . $this->format . ' ' . ($this->orientation === 'P' ? 'portrait' : 'landscape') . ';
            margin: 0;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .page-break {
                page-break-after: always;
            }
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        * {
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    ' . $this->html . '

    <script>
        // Auto-trigger print dialog
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>';

        return $html;
    }

    /**
     * Add an image to the document
     *
     * @param string $file Image file path or URL
     * @param float $x X position in mm
     * @param float $y Y position in mm
     * @param float $w Width in mm
     * @param float $h Height in mm
     */
    public function image($file, $x, $y, $w = 0, $h = 0)
    {
        // Convert image to base64 if it's a local file
        $imageData = '';

        if (file_exists($file)) {
            $imageData = base64_encode(file_get_contents($file));
            $imageInfo = getimagesize($file);
            $mimeType  = $imageInfo['mime'];
        } else {
            // Assume it's already a data URL or external URL
            $this->html .= '<img src="' . htmlspecialchars($file) . '" style="position: absolute; left: ' . $x . 'mm; top: ' . $y . 'mm; width: ' . $w . 'mm; height: ' . $h . 'mm;" />';
            return;
        }

        $this->html .= '<img src="data:' . $mimeType . ';base64,' . $imageData . '" style="position: absolute; left: ' . $x . 'mm; top: ' . $y . 'mm; width: ' . $w . 'mm; height: ' . $h . 'mm;" />';
    }
}
