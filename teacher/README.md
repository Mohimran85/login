# 🖋️ Digital Signature System for Event Management

## Overview

A secure, comprehensive digital signature system integrated into the Event Management System, providing class counselors with the ability to create and manage digital signatures for OD (On Duty) letter approvals.

## 🚀 Features

### Core Functionality

- **Multiple Signature Types**: Upload image, draw signature, or create text-based signatures
- **Mobile Responsive**: Touch-friendly canvas drawing for mobile devices
- **Security Features**: SHA-256 encryption, timestamp validation, and verification codes
- **Automatic Integration**: Signatures automatically appear in approved OD letters
- **Single Active Signature**: Only one signature per teacher for security

### User Interface

- **Intuitive Tabs**: Easy switching between signature creation methods
- **Real-time Preview**: See signature before saving
- **Drag & Drop Upload**: Modern file upload experience
- **Font Selection**: Multiple professional fonts for text signatures
- **Visual Feedback**: Success/error messages with icons

## 📁 File Structure

```
login/
├── setup_digital_signature.php     # Database setup interface
├── create_signature_table.sql      # SQL schema for signature tables
├── teacher/
│   ├── digital_signature.php       # Main signature management interface
│   └── signatures/                 # Directory for uploaded signature files
└── student/
    └── download_od_letter.php       # Updated with signature integration
```

## 🔧 Installation & Setup

### Step 1: Database Setup

1. Navigate to: `http://localhost/event_management_system/login/setup_digital_signature.php`
2. Click "Setup Digital Signature Database"
3. Verify all tables are created successfully

### Step 2: Access Signature Management

1. Login as a teacher
2. Navigate to "Digital Signature" in the sidebar menu
3. Create your digital signature using one of three methods

### Step 3: Start Using Digital Signatures

- Approved OD letters will automatically include your digital signature
- Signatures are verified with unique codes for authenticity

## 🗄️ Database Schema

### teacher_signatures Table

```sql
CREATE TABLE teacher_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    signature_type ENUM('upload', 'drawn', 'text') NOT NULL,
    signature_data TEXT NOT NULL,
    signature_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teacher_register(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_signature (teacher_id, is_active)
);
```

### od_requests Table (Updated)

```sql
ALTER TABLE od_requests
ADD COLUMN signature_verification_code VARCHAR(255) DEFAULT NULL,
ADD COLUMN signature_timestamp TIMESTAMP NULL DEFAULT NULL;
```

## 🔐 Security Features

### Encryption & Hashing

- **SHA-256 Hashing**: All signatures are hashed for tamper detection
- **Verification Codes**: Unique codes generated for each OD letter
- **Timestamp Validation**: Creation time tracking for authenticity

### Access Control

- **Teacher-Specific**: Each signature is linked to a unique teacher ID
- **Single Active**: Only one signature can be active per teacher
- **Database Constraints**: Foreign key relationships prevent orphaned records

### Document Security

- **Signature Verification**: Each OD letter includes verification information
- **Tamper Detection**: Hash validation prevents signature modification
- **Audit Trail**: Timestamp tracking for all signature operations

## 📱 Usage Guide

### Creating a Signature

#### Method 1: Upload Image

1. Click "Upload Image" tab
2. Drag & drop or browse for image file (JPEG, PNG, GIF)
3. Maximum file size: 2MB
4. Click "Save Uploaded Signature"

#### Method 2: Draw Signature

1. Click "Draw Signature" tab
2. Use mouse or finger to draw on the canvas
3. Use "Clear" button to start over
4. Click "Save Drawn Signature"

#### Method 3: Text Signature

1. Click "Text Signature" tab
2. Enter your name in the text field
3. Select preferred font style
4. Preview your signature
5. Click "Save Text Signature"

### Viewing Current Signature

- Active signatures are displayed at the top of the page
- Shows signature type, creation date, and preview
- Verification code preview for security reference

### OD Letter Integration

- Signatures automatically appear in approved OD letters
- Verification codes are included for authenticity
- Digital signature replaces manual signature lines

## 🛠️ Technical Implementation

### Frontend Technologies

- **HTML5 Canvas**: For drawing signatures
- **JavaScript**: Interactive features and mobile touch support
- **CSS Grid & Flexbox**: Responsive layout design
- **Material Icons**: Professional icon set

### Backend Technologies

- **PHP 7.4+**: Server-side logic and database operations
- **MySQL**: Database storage and relationships
- **SHA-256**: Cryptographic hashing for security

### Mobile Compatibility

- **Touch Events**: Full support for mobile drawing
- **Responsive Design**: Optimized for all screen sizes
- **Viewport Scaling**: Proper mobile scaling and zoom handling

## 🔍 Troubleshooting

### Common Issues

#### "Table doesn't exist" Error

- **Solution**: Run the database setup at `/setup_digital_signature.php`
- **Cause**: Signature tables haven't been created yet

#### Signature Not Appearing in OD Letters

- **Check**: Ensure teacher has an active signature
- **Verify**: Database relationship between teacher and signature
- **Solution**: Recreate signature if necessary

#### Upload Errors

- **File Size**: Ensure image is under 2MB
- **File Type**: Only JPEG, PNG, GIF supported
- **Permissions**: Check write permissions on `signatures/` directory

#### Mobile Drawing Issues

- **Touch Support**: Ensure device supports touch events
- **Browser**: Use modern browsers (Chrome, Safari, Firefox)
- **Canvas Size**: Adjust canvas size for better mobile experience

## 📊 Performance Considerations

### Database Optimization

- **Indexes**: Added on frequently queried columns
- **Constraints**: Unique constraints prevent duplicate active signatures
- **Cleanup**: Automatic deactivation of old signatures

### File Management

- **Directory Structure**: Organized signature files by teacher ID
- **Filename Convention**: Unique filenames prevent conflicts
- **Size Limits**: 2MB limit prevents excessive storage usage

## 🔄 Future Enhancements

### Planned Features

- **Multiple Signature Templates**: Pre-designed signature styles
- **Signature History**: View and restore previous signatures
- **Bulk Import**: Import signatures for multiple teachers
- **Advanced Verification**: QR codes for enhanced verification

### Integration Possibilities

- **Email Integration**: Include signatures in email notifications
- **Certificate Generation**: Apply signatures to certificates
- **Document Templates**: Extend to other document types

## 📋 Maintenance

### Regular Tasks

- **Backup**: Regular database backups including signature data
- **Cleanup**: Remove unused signature files periodically
- **Updates**: Keep PHP and MySQL versions updated
- **Security**: Monitor for unauthorized signature modifications

### Monitoring

- **Error Logs**: Check PHP error logs for signature-related issues
- **Database Growth**: Monitor table size and optimize as needed
- **Performance**: Track page load times for signature management

## 📞 Support

### Documentation

- **User Guide**: Available in the system help section
- **API Documentation**: For developers extending the system
- **Video Tutorials**: Step-by-step signature creation guides

### Contact Information

- **Technical Support**: IT Department
- **System Administrator**: admin@sonatech.ac.in
- **Emergency Contact**: +91-427-2331129

---

_Digital Signature System v1.0 - Implemented November 2025_
_Part of the Sona College of Technology Event Management System_
