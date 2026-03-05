# OD Request System - Feature Documentation

## 🎯 Overview

The OD (On Duty) Request System has been enhanced with comprehensive features for student event management, including poster uploads, PDF generation, and professional documentation.

## ✨ Key Features

### 1. Event Poster Upload

- **File Types**: JPG, PNG, PDF
- **Size Limit**: 5MB maximum
- **Security**: Validated uploads with restricted execution
- **Storage**: `uploads/posters/` directory with `.htaccess` protection

### 2. Enhanced PDF Generation

- **Professional Letterhead**: College logo and branding
- **Complete Details**: All event information included
- **Duration Field**: Number of days for the event
- **Download Options**: Direct PDF download for approved requests

### 3. Improved User Interface

- **Thumbnail Previews**: Visual poster previews in request list
- **Poster Viewer**: Dedicated viewer with fullscreen and download options
- **Mobile Responsive**: Optimized for all devices
- **Material Design**: Modern icons and styling

### 4. Security Features

- **File Validation**: Type and size checking
- **Secure Naming**: Timestamped filenames prevent conflicts
- **Directory Protection**: `.htaccess` prevents script execution
- **Session Management**: Secure user authentication

## 📁 File Structure

```
student/
├── od_request.php          # Main OD request form and management
├── view_poster.php         # Poster viewer with download options
├── download_od_letter.php  # Enhanced PDF generator
├── generate_od_pdf.php     # Alternative PDF generator
├── uploads/
│   ├── .htaccess          # Security configuration
│   └── posters/           # Poster storage directory
└── assets/
    └── images/
        └── favicon_io/
```

## 🗄️ Database Schema

### od_requests Table

```sql
CREATE TABLE od_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    degree VARCHAR(100) NOT NULL,
    year_semester VARCHAR(50) NOT NULL,
    section VARCHAR(10) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    event_name VARCHAR(200) NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    event_days INT DEFAULT 1,
    event_location VARCHAR(200) NOT NULL,
    event_poster VARCHAR(255) DEFAULT NULL,
    event_description TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    counselor_remarks TEXT DEFAULT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 🚀 Usage Instructions

### For Students:

1. **Login** to the student portal
2. **Navigate** to OD Request section
3. **Fill Form** with complete event details
4. **Upload Poster** (optional but recommended)
5. **Submit Request** for counselor approval
6. **Download PDF** once approved
7. **Register** for event participation

### For Counselors:

1. **Review Requests** in counselor dashboard
2. **View Posters** to understand event context
3. **Approve/Reject** with remarks
4. **Track** student participations

## 🔧 Technical Implementation

### File Upload Handling

```php
// Validation
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_size = 5 * 1024 * 1024; // 5MB

// Secure naming
$file_name = time() . '_' . $student_id . '_' . $safe_name;

// Storage
$upload_path = 'uploads/posters/' . $file_name;
```

### PDF Generation

```php
// Enhanced letterhead with logo
$html = '<div style="text-align: center; margin-bottom: 30px;">
    <img src="../assets/images/sona_logo.png" style="height: 80px;">
    <h1>SONA COLLEGE OF TECHNOLOGY</h1>
    <p>Salem - 636005</p>
</div>';
```

### Poster Display

```php
// Thumbnail preview
if (in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
    echo '<img src="' . $poster_path . '"
          style="width: 60px; height: 60px; object-fit: cover;">';
}
```

## 🔒 Security Measures

1. **File Type Validation**: Only allow images and PDFs
2. **Size Limiting**: 5MB maximum upload size
3. **Script Prevention**: `.htaccess` blocks execution
4. **Secure Naming**: Timestamped, collision-free filenames
5. **Session Control**: Authenticated access only
6. **SQL Injection Prevention**: Prepared statements used

## 📱 Mobile Compatibility

- **Responsive Design**: Adapts to all screen sizes
- **Touch Optimized**: Easy interaction on mobile devices
- **Fast Loading**: Optimized images and efficient code
- **Accessible**: Screen reader friendly

## 🎨 UI/UX Enhancements

- **Material Icons**: Modern, consistent iconography
- **Color Scheme**: Professional blue (#0c3878) theme
- **Animations**: Smooth transitions and hover effects
- **Feedback**: Clear status messages and validation
- **Loading States**: Progress indicators for uploads

## 🧪 Testing

1. **Upload various file types** to test validation
2. **Submit OD requests** with and without posters
3. **View poster thumbnails** in request list
4. **Download PDF letters** for approved requests
5. **Test mobile responsiveness** on different devices

## 📞 Support

For technical issues or feature requests:

- Check PHP error logs in XAMPP
- Verify database connectivity
- Ensure uploads directory permissions
- Test file upload limits in php.ini

## 🔄 Version History

- **v1.0**: Basic OD request form
- **v1.1**: Added PDF generation
- **v1.2**: Enhanced with college branding
- **v1.3**: Added duration field
- **v1.4**: Implemented poster upload system ✨ (Current)

---

_System developed for Sona College of Technology - Event Management Portal_
