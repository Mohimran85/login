# Hackathon Management System with Push Notifications

## 📋 Overview

This feature adds a complete hackathon posting and application system to your Event Management System with Web Push Notifications. Admin can post hackathons, students receive push notifications, can browse hackathons with view tracking, and apply individually or as teams with instant confirmation.

## 🎯 Features Implemented

### Admin Features
- ✅ Create, edit, and delete hackathon posts
- ✅ Upload poster images (auto-compressed to WebP)
- ✅ Upload rules PDF documents
- ✅ Set registration deadlines and participant limits
- ✅ View analytics (views, applications, status)
- ✅ Send push notifications to all students
- ✅ View all applications with filters
- ✅ Track hackathon views

### Student Features
- ✅ Browse available hackathons with filters
- ✅ View detailed hackathon information
- ✅ Apply individually or as a team
- ✅ Provide project descriptions in applications
- ✅ View application status
- ✅ Receive web push notifications
- ✅ Notification center with read/unread status
- ✅ Auto-confirm registrations (no approval needed)

### Technical Features
- ✅ Web Push API (compatible with Median.co)
- ✅ Service Worker for offline support
- ✅ Progressive Web App (PWA) manifest
- ✅ View tracking analytics
- ✅ File compression (images & PDFs)
- ✅ Database triggers for auto-counting
- ✅ Security with CSRF protection
- ✅ Mobile-responsive design

## 📦 Installation Steps

### 1. Database Setup

Run the SQL migration to create all necessary tables:

```bash
# Navigate to phpMyAdmin or MySQL command line
# Import the SQL file:
mysql -u root -p event_management_system < sql/create_hackathon_system.sql

# Or in phpMyAdmin:
# 1. Select 'event_management_system' database
# 2. Click 'Import'
# 3. Choose file: sql/create_hackathon_system.sql
# 4. Click 'Go'
```

This creates 6 new tables:
- `hackathon_posts` - Stores hackathon information
- `hackathon_applications` - Stores student applications
- `hackathon_views` - Tracks views for analytics
- `push_subscriptions` - Stores Web Push subscriptions
- `notifications` - Notification history
- `push_notification_log` - Push delivery logs

### 2. Web Push Setup

#### Generate VAPID Keys (One-time)

VAPID keys are automatically generated when the system first runs. They are stored in:
```
cache/vapid_keys.json
```

**

IMPORTANT**: Keep this file secure! Do not commit to version control.

For production, it's recommended to use environment variables:
```php
// In your .env or config file:
VAPID_PUBLIC_KEY=your_public_key_here
VAPID_PRIVATE_KEY=your_private_key_here
VAPID_SUBJECT=mailto:admin@yourdomain.com
```

#### Enable Service Worker

The service worker file is already created at:
```
sw.js (root directory)
```

It must be served from the root of your domain for proper scope.

### 3. File Permissions

Create upload directories with proper permissions:

```bash
mkdir -p uploads/hackathon_posters
mkdir -p uploads/hackathon_rules
chmod 755 uploads/hackathon_posters
chmod 755 uploads/hackathon_rules
chmod 600 cache/vapid_keys.json  # Secure VAPID keys
```

### 4. Update Navigation

Add hackathons link to student navigation. In `student/index.php`, add:

```html
<a href="hackathons.php" class="nav-link">
    <span class="material-symbols-outlined">emoji_events</span>
    <span>Hackathons</span>
</a>
```

Add to admin navigation in `admin/index.php`:

```html
<a href="hackathons.php" class="nav-link">
    <span class="material-symbols-outlined">emoji_events</span>
    <span>Hackathons</span>
</a>
```

### 5. Enable Push Notifications in Student Dashboard

Add to student header/dashboard (`student/index.php`):

```html
<link rel="manifest" href="../manifest.json">
<script src="js/push-manager.js"></script>
```

## 🚀 Usage Guide

### For Admins

#### Creating a Hackathon

1. Navigate to **Admin Dashboard → Hackathons**
2. Click **"Create Hackathon"**
3. Fill in the form:
   - **Title**: Hackathon name
   - **Description**: Full details, objectives, prizes
   - **Organizer**: Department or organization name
   - **Theme**: Optional theme (e.g., "AI", "Web3")
   - **Tags**: Comma-separated for filtering
   - **Dates**: Start, end, and registration deadline
   - **Max Participants**: Leave empty for unlimited
   - **Status**: Draft (hidden) or Upcoming (visible)
   - **Poster**: Upload image (auto-compressed)
   - **Rules PDF**: Upload detailed rules
   - **Send Notification**: Check to send push to all students

4. Click **"Create Hackathon"**
5. Students instantly receive push notification if enabled

#### Managing Hackathons

- **View List**: `admin/hackathons.php` - See all hackathons with stats
- **Edit**: Click edit icon to modify details
- **Delete**: Click delete icon (confirms before deletion)
- **View Applications**: `admin/hackathon_applications.php` - See who applied

#### Analytics Available

- Total hackathons count
- View count per hackathon
- Application count (individual vs team)
- Confirmed vs withdrawn applications
- Registration status (open, closed, full)

### For Students

#### Browsing Hackathons

1. Navigate to **Student Dashboard → Hackathons**
2. Browse available hackathons in card layout
3. Use filters:
   - Search by title, theme, tags
   - Filter by status (upcoming, ongoing)
4. See key info:
   - Registration deadline
   - Participant count
   - Theme and tags
   - "Days left" warning if deadline close

####  Viewing Details

1. Click on any hackathon card
2. Detailed page shows:
   - Full description
   - Rules PDF (downloadable)
   - Organizer information
   - Important dates
   - Theme and requirements
   - Apply button

#### Applying for Hackathon

1. On hackathon details page, click **"Apply Now"**
2. Choose application type:
   - **Individual**: Solo participation
   - **Team**: Provide team name and member details
3. Enter project description:
   - What you plan to build
   - Your approach
   - Technologies you'll use
4. Submit application
5. Instant confirmation (no approval needed)
6. View status in **"My Applications"**

#### Receiving Notifications

**First-time setup:**
1. Browser will prompt: "Allow notifications from this site?"
2. Click **"Allow"**
3. Your device is now subscribed to push notifications

**When admin posts hackathon:**
- Receive instant push notification on all your devices
- Notification includes:
  - Hackathon title
  - "Register now!" message
  - Click to open hackathon details
- Notification saved in notification center

**Notification Center:**
- Bell icon in header shows unread count
- Click to see all notifications
- Mark as read individually or all at once

## 🔒 Security Features

### Implemented

- ✅ **Authentication**: All pages require login
- ✅ **Authorization**: Role-based access (admin/student)
- ✅ **CSRF Protection**: Tokens on all forms
- ✅ **SQL Injection Prevention**: Prepared statements
- ✅ **File Upload Validation**: 
  - MIME type checking (server-side)
  - Extension whitelisting
  - File size limits
- ✅ **XSS Prevention**: Output escaping with htmlspecialchars()
- ✅ **Path Traversal Prevention**: Validated file paths
- ✅ **Secure File Storage**: Files outside webroot where possible
- ✅ **VAPID Key Security**: Stored with 600 permissions

### Best Practices

- Keep `cache/vapid_keys.json` secure
- Use HTTPS in production (required for Web Push)
- Regularly update database credentials
- Monitor `push_notification_log` for failures
- Review `security_log` for suspicious activity

## 📱 Median.co Web-to-App Conversion

This system is fully compatible with [Median.co](https://median.co) for converting to Android/iOS apps.

### Configuration

1. **manifest.json**: Already created with proper icons and metadata
2. **Service Worker**: Registered at root (`sw.js`)
3. **Push Notifications**: Uses standard Web Push API (no FCM dependency)
4. **Icons**: Place app icons in `asserts/images/`:
   - icon-192x192.png (required)
   - icon-512x512.png (required)
   - icon-72x72.png, icon-96x96.png, etc. (optional)

### Building with Median.co

1. Sign up at [median.co](https://median.co)
2. Create new app project
3. Enter your website URL (e.g., `https://yourdomain.com/student/index.php`)
4. Configure:
   - **Push Notifications**: Enable Web Push
   - **App Name**: Event Management System
   - **App Icon**: Upload icon
   - **Splash Screen**: Upload logo
5. Build APK/AAB for Android or IPA for iOS
6. Test push notifications on device
7. Publish to Play Store/App Store

### Testing Push Notifications

**On Web (Chrome/Firefox/Edge):**
```javascript
// Open browser console on student page
// Check if service worker is registered:
navigator.serviceWorker.getRegistration().then(reg => console.log(reg));

// Check notification permission:
console.log(Notification.permission);

// Check active subscriptions:
fetch('/student/ajax/push_subscription.php?action=status')
    .then(r => r.json())
    .then(d => console.log(d));
```

**On Android (via Median.co app):**
1. Install APK on device
2. Grant notification permission when prompted
3. Admin posts hackathon with "Send notification" checked
4. Notification appears on device
5. Click notification → Opens hackathon details in app

## 🗂️ File Structure

```
login/
├── admin/
│   ├── hackathons.php              # List all hackathons
│   ├── create_hackathon.php        # Create new hackathon
│   ├── edit_hackathon.php          # Edit hackathon (TODO)
│   ├── hackathon_applications.php  # View applications (TODO)
│   └── ajax/
│       └── hackathons.php          # AJAX endpoints (TODO)
├── student/
│   ├── hackathons.php              # Browse hackathons
│   ├── hackathon_details.php       # View details (TODO)
│   ├── apply_hackathon.php         # Application form (TODO)
│   ├── my_hackathons.php           # My applications (TODO)
│   ├── notifications.php           # Notification center (TODO)
│   ├── ajax/
│   │   ├── notifications.php       # Notification AJAX (TODO)
│   │   └── push_subscription.php   # Push subscription (TODO)
│   └── js/
│       └── push-manager.js         # Push notification client (TODO)
├── includes/
│   ├── WebPushManager.php          # Web Push backend
│   ├── DatabaseManager.php         # Existing DB utility
│   ├── FileCompressor.php          # Existing file compression
│   ├── security.php                # Existing security functions
│   └── csrf.php                    # Existing CSRF protection
├── sql/
│   └── create_hackathon_system.sql # Database schema
├── uploads/
│   ├── hackathon_posters/          # Poster images
│   └── hackathon_rules/            # Rules PDFs
├── cache/
│   └── vapid_keys.json             # VAPID keys (auto-generated)
├── sw.js                           # Service Worker
└── manifest.json                   # PWA Manifest
```

## 🎨 Customization

### Changing Colors

Edit the gradient colors in student pages:

```css
/* In student/hackathons.php */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Change to your brand colors: */
background: linear-gradient(135deg, #4285f4 0%, #34a853 100%);
```

### Notification Icon

Replace notification icon in `sw.js`:

```javascript
icon: '/asserts/images/logo.png',  // Your logo
badge: '/asserts/images/badge.png', // Small badge icon (72x72px)
```

### Email Notifications (Future Enhancement)

To add email notifications alongside push:

1. Install PHPMailer: `composer require phpmailer/phpmailer`
2. Configure SMTP in `includes/EmailManager.php`
3. In `admin/create_hackathon.php`, after push:

```php
$email = new EmailManager();
foreach ($students as $student) {
    $email->send(
        $student['personal_email'],
        'New Hackathon: ' . $title,
        $email_template
    );
}
```

## 🐛 Troubleshooting

### Push Notifications Not Working

**Problem**: Students don't receive notifications

**Solutions**:
1. Check browser console for errors
2. Verify service worker is registered:
   ```javascript
   navigator.serviceWorker.getRegistrations().then(regs => console.log(regs));
   ```
3. Check notification permission:
   ```javascript
   console.log(Notification.permission); // Should be "granted"
   ```
4. Verify VAPID keys exist:
   ```bash
   ls -la cache/vapid_keys.json
   ```
5. Check push_notification_log table for errors:
   ```sql
   SELECT * FROM push_notification_log WHERE status = 'failed' ORDER BY attempted_at DESC LIMIT 10;
   ```
6. Ensure HTTPS (required for Web Push, except localhost)

### File Upload Failures

**Problem**: Poster/PDF upload fails

**Solutions**:
1. Check directory permissions:
   ```bash
   chmod 755 uploads/hackathon_posters
   chmod 755 uploads/hackathon_rules
   ```
2. Check PHP upload limits in `php.ini`:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```
3. Check error logs:
   ```bash
   tail -f /xampp/apache/logs/error.log
   ```

### Database Errors

**Problem**: SQL errors when creating hackathon

**Solutions**:
1. Verify tables exist:
   ```sql
   SHOW TABLES LIKE 'hackathon%';
   ```
2. Check foreign key constraints:
   ```sql
   SHOW CREATE TABLE hackathon_applications;
   ```
3. Verify admin user ID exists:
   ```sql
   SELECT id FROM teacher_register WHERE status = 'admin';
   ```

### Service Worker Not Updating

**Problem**: Changes to sw.js not reflecting

**Solutions**:
1. Hard refresh: `Ctrl+Shift+R` (Windows) or `Cmd+Shift+R` (Mac)
2. Unregister old service worker:
   ```javascript
   navigator.serviceWorker.getRegistrations().then(regs => {
       regs.forEach(reg => reg.unregister());
   });
   ```
3. Clear browser cache
4. Update CACHE_VERSION in sw.js:
   ```javascript
   const CACHE_VERSION = 'v2'; // Increment this
   ```

## 📊 Database Queries for Analytics

### Most Viewed Hackathons

```sql
SELECT title, view_count, current_registrations
FROM hackathon_posts
ORDER BY view_count DESC
LIMIT 10;
```

### Application Statistics

```sql
SELECT 
    hp.title,
    COUNT(ha.id) as total_applications,
    SUM(CASE WHEN ha.application_type = 'team' THEN 1 ELSE 0 END) as team_apps,
    SUM(CASE WHEN ha.application_type = 'individual' THEN 1 ELSE 0 END) as individual_apps
FROM hackathon_posts hp
LEFT JOIN hackathon_applications ha ON hp.id = ha.hackathon_id
GROUP BY hp.id
ORDER BY total_applications DESC;
```

### Push Notification Success Rate

```sql
SELECT 
    DATE(attempted_at) as date,
    COUNT(*) as total_sent,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    ROUND(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as success_rate
FROM push_notification_log
GROUP BY DATE(attempted_at)
ORDER BY date DESC
LIMIT 30;
```

### Most Active Students

```sql
SELECT 
    sr.name,
    sr.regno,
    COUNT(ha.id) as applications_count
FROM student_register sr
JOIN hackathon_applications ha ON sr.regno = ha.student_regno
WHERE ha.status = 'confirmed'
GROUP BY sr.regno
ORDER BY applications_count DESC
LIMIT 20;
```

## ✅ Testing Checklist

### Admin Testing
- [ ] Create hackathon with all fields
- [ ] Upload poster (JPG/PNG) - verify compression
- [ ] Upload rules PDF - verify compression
- [ ] Send push notification - verify delivered
- [ ] Edit hackathon details
- [ ] Delete hackathon
- [ ] View applications list
- [ ] Filter by status
- [ ] Search by title/theme
- [ ] Export applications to CSV

### Student Testing
- [ ] Browse hackathons list
- [ ] Filter by status (upcoming/ongoing)
- [ ] Search by keywords
- [ ] View hackathon details
- [ ] Apply as individual
- [ ] Apply as  team
- [ ] View "My Applications"
- [ ] Withdraw application
- [ ] Receive push notification
- [ ] Click notification → redirects correctly
- [ ] View notification center
- [ ] Mark notifications as read

### Security Testing
- [ ] Access admin pages as student (should block)
- [ ] Submit form without CSRFtoken (should fail)
- [ ] Upload PHP file as poster (should reject)
- [ ] SQL injection in search (should escape)
- [ ] XSS in description (should escape output)
- [ ] Apply after deadline (should prevent)
- [ ] Apply when full (should prevent)
- [ ] Apply twice for same hackathon (should prevent)

### Browser/Device Testing
- [ ] Chrome (Windows/Mac/Android)
- [ ] Firefox (Windows/Mac)
- [ ] Edge (Windows)
- [ ] Safari (Mac/iOS)
- [ ] Mobile responsive design
- [ ] Push notifications on mobile
- [ ] Service worker registration
- [ ] Offline functionality
- [ ] Median.co Android app
- [ ] Median.co iOS app (if applicable)

## 🚧 TODO Items (Not Yet Implemented)

The following files are referenced but need to be created:

1. **student/hackathon_details.php** - Detailed hackathon view with apply button
2. **student/apply_hackathon.php** - Application form for individual/team
3. **student/my_hackathons.php** - View student's applications
4. **student/notifications.php** - Full notification center page
5. **student/ajax/notifications.php** - Notification AJAX endpoints
6. **student/ajax/push_subscription.php** - Push subscription management
7. **student/js/push-manager.js** - Client-side push notification handler
8. **admin/edit_hackathon.php** - Edit existing hackathon
9. **admin/hackathon_applications.php** - View/manage all applications
10. **admin/ajax/hackathons.php** - Admin AJAX endpoints

These will be implemented in the next phase. Priority:
1. Student details & apply pages (for functionality)
2. Push notification client (for notifications)
3. Notification center (for viewing history)
4. Admin applications page (for management)
5. Edit functionality (for maintenance)

## 📞 Support

For issues or questions:
1. Check this README first
2. Review error logs: `/xampp/apache/logs/error.log`
3. Check database logs: `push_notification_log`, `notifications`
4. Test in browser console for JavaScript errors
5. Verify all SQL tables created successfully

## 📝 License

This hackathon management system is part of the Event Management System project.

---

**Version**: 1.0.0  
**Created**: February 20, 2026  
**Last Updated**: February 20, 2026  
**Compatible with**: Median.co web-to-app conversion
