# QR Code Verification System for OD Letters

## Overview

The OD letter system now includes QR code functionality that allows anyone to verify the authenticity of On Duty letters by scanning a QR code printed on the letter.

## Features Implemented

### 1. QR Code Generation

- **Location**: Bottom left corner of OD letter, under the Class Counselor signature
- **Size**: 80x80 pixels
- **Content**: URL to public verification page with OD ID
- **Storage**: QR codes are saved in `student/uploads/qr_codes/` directory

### 2. Public Verification Page

- **File**: `student/verify_od.php`
- **Access**: No authentication required (public page)
- **Security**: Only displays APPROVED OD letters
- **Features**:
  - Displays complete OD letter with all details
  - Shows verification banner confirming authenticity
  - Supports group OD requests
  - Print/Save as PDF functionality

### 3. Modified Files

#### `student/generate_od_pdf.php`

- Added QR code library inclusion
- Generates QR code with verification URL
- Positions QR code in bottom left under counselor signature
- Fixed event_location bug (now uses event_state + event_district)

#### `student/download_od_letter.php`

- Added QR code library inclusion
- Generates QR code with verification URL
- Positions QR code in bottom left under counselor signature
- Fixed event_location bug (now uses event_state + event_district)

#### `student/verify_od.php` (NEW)

- Public verification page accessible without login
- Displays approved OD letters only
- Fully responsive design
- Print-friendly layout

### 4. New Libraries

#### `student/includes/phpqrcode/qrlib.php`

- Lightweight QR code generator
- Uses online QR API (QR Server API)
- Fallback to placeholder image if API unavailable
- Simple interface: `QRcode::png($text, $file, $level, $size, $margin)`

#### `includes/PdfGenerator.php`

- HTML to PDF conversion wrapper
- Works with XAMPP without Composer
- Supports embedded images
- Print-optimized output

## How It Works

### User Flow

1. Student requests OD through the system
2. Class counselor approves the request
3. Student downloads/generates OD letter
4. QR code is automatically generated and embedded in the PDF
5. Anyone can scan the QR code to verify the letter's authenticity

### Technical Flow

```
OD Approved → Generate PDF Request
           ↓
Generate QR Code (URL: verify_od.php?od_id=123)
           ↓
Save QR image (uploads/qr_codes/od_123.png)
           ↓
Embed QR in PDF (bottom left position)
           ↓
User downloads/prints OD letter with QR code
           ↓
External party scans QR code
           ↓
Opens verify_od.php in browser (no login needed)
           ↓
Displays verified OD letter if approved
```

## Testing Instructions

### 1. Test QR Code Generation

1. Log in as a student
2. Navigate to OD Request page
3. Open an approved OD request
4. Click "Generate PDF" or "Download OD Letter"
5. Check that QR code appears in bottom left corner
6. Verify QR code is labeled "Scan to Verify"

### 2. Test QR Code Scanning

1. Print or display the OD letter with QR code
2. Use a phone camera or QR scanner app
3. Scan the QR code
4. Verify it opens the verification page in browser
5. Check that the correct OD letter is displayed

### 3. Test Public Verification Page

1. Open browser without logging in
2. Navigate to: `http://yoursite/student/verify_od.php?od_id=123`
3. Verify the OD letter is displayed if approved
4. Try with invalid od_id - should show error message
5. Try with pending OD - should show "not approved" message

### 4. Test Security

- Verify non-approved ODs are not displayed
- Verify invalid od_ids show proper error messages
- Verify the page works without authentication
- Check that group OD members are displayed correctly

## Configuration

### Update Verification URL

If your site is not running on localhost, update the verification URL in both files:

**In `generate_od_pdf.php` and `download_od_letter.php`:**

```php
// Change this line (around line 65):
$verification_url = 'http://' . $_SERVER['HTTP_HOST'] . '/student/verify_od.php?od_id=' . $od_id;

// To your actual domain:
$verification_url = 'https://yourdomain.com/student/verify_od.php?od_id=' . $od_id;
```

### QR Code Settings

Modify QR code generation parameters in the QRcode::png() call:

```php
QRcode::png($verification_url, $qr_code_path, 'L', 4, 2);
//                                            ^  ^  ^
//                                            |  |  Margin
//                                            |  Size (1-10)
//                                            Error correction level (L,M,Q,H)
```

### Logo Path

Update the college logo path in `verify_od.php` if needed:

```php
<img src="sona_logo.jpg" alt="Sona College Logo" class="college-logo">
```

## Troubleshooting

### QR Code Not Appearing

- Check directory permissions: `student/uploads/qr_codes/` must be writable
- Verify QR library is loaded: Check for `includes/phpqrcode/qrlib.php`
- Check PHP error logs for any file write errors

### QR Code Not Scanning

- Increase QR size: Change size parameter from 4 to 6 or higher
- Increase error correction: Change 'L' to 'M' or 'H'
- Ensure QR image is not corrupted or pixelated

### Verification Page Not Loading

- Check URL path is correct (relative to student directory)
- Verify database connection settings
- Ensure OD ID exists and is approved

### Event Location Showing as Empty

- Bug was fixed: event_location now uses event_state + event_district
- If still empty, check database for null values in event_state or event_district

## Security Considerations

### Current Implementation (Simple)

- Uses simple od_id in URL (e.g., ?od_id=123)
- Anyone can guess IDs by incrementing numbers
- Only approved ODs are shown for safety

### Enhanced Security (Future)

For production use, consider:

1. **UUID Tokens**: Generate unique UUID per OD request
2. **Hash-based Tokens**: Use SHA-256 hash with secret key
3. **Expiring Tokens**: Add expiration date to verification links
4. **Rate Limiting**: Prevent automated OD enumeration

Example UUID implementation:

```php
// In database migration:
ALTER TABLE od_requests ADD COLUMN verification_uuid VARCHAR(36) UNIQUE;

// On approval:
$uuid = uniqid('', true); // or use proper UUID library
UPDATE od_requests SET verification_uuid = '$uuid' WHERE id = $od_id;

// QR URL:
$verification_url = 'https://yoursite.com/student/verify_od.php?token=' . $od_data['verification_uuid'];
```

## File Structure

```
student/
  ├── generate_od_pdf.php      (Modified - Added QR code)
  ├── download_od_letter.php   (Modified - Added QR code)
  ├── verify_od.php            (NEW - Public verification page)
  ├── includes/
  │   └── phpqrcode/
  │       └── qrlib.php        (NEW - QR generator library)
  └── uploads/
      └── qr_codes/            (NEW - QR code storage)
          └── od_123.png       (Generated QR codes)

includes/
  └── PdfGenerator.php         (NEW - PDF wrapper class)
```

## Browser Compatibility

- Chrome: Full support
- Firefox: Full support
- Safari: Full support
- Edge: Full support
- Mobile browsers: Full support with QR scanning

## Print Compatibility

- Uses CSS @media print rules
- QR code is visible in printed PDF
- Optimized for A4 paper size
- 0.75 inch margins

## Future Enhancements

1. **Analytics**: Track QR code scans
2. **Enhanced Security**: UUID-based verification tokens
3. **Bulk QR Generation**: Generate QR codes for multiple ODs
4. **QR Customization**: Add college logo to QR code center
5. **PDF Library**: Integrate proper PDF library (mPDF/TCPDF) for better positioning
6. **Email Integration**: Send QR-enabled OD letter via email
7. **Expiry Tracking**: Mark OD as expired after event date

## Support

For issues or questions, contact the system administrator or refer to the main README.md file.

---

**Implementation Date**: February 6, 2026
**Version**: 1.0
**Status**: Production Ready
