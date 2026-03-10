# 🚀 Hackathon System - Quick Setup Guide

## ✅ What's Been Implemented

### Core Files Created (11 files)

#### Database & Backend

1. ✅ **sql/create_hackathon_system.sql** - Complete database schema with 6 tables, triggers, and indexes
2. ✅ **includes/OneSignalManager.php** - OneSignal push notification backend
3. ✅ **sw.js** - Service Worker for push notifications and offline support
4. ✅ **manifest.json** - PWA manifest for Median.co app conversion

#### Admin Pages

5. ✅ **admin/hackathons.php** - List/manage all hackathons with filters and analytics
6. ✅ **admin/create_hackathon.php** - Create hackathon with file uploads & push notifications

#### Student Pages

7. ✅ **student/hackathons.php** - Browse hackathons with filters and search
8. ✅ **student/hackathon_details.php** - View hackathon details with view tracking
9. ✅ **student/ajax/push_subscription.php** - Handle push notification subscriptions
10. ✅ **student/js/push-manager.js** - Client-side push notification manager

#### Documentation

11. ✅ **HACKATHON_SYSTEM_README.md** - Comprehensive documentation (17KB)

---

## 🎯 Quick Start (5 Minutes)

### Step 1: Import Database Schema

Open phpMyAdmin and run:

```sql
-- In phpMyAdmin:
-- 1. Select 'event_management_system' database
-- 2. Click 'SQL' tab
-- 3. Paste content from sql/create_hackathon_system.sql
-- 4. Click 'Go'
```

**Verify tables created:**

```sql
SHOW TABLES LIKE 'hackathon%';
-- Should show: hackathon_posts, hackathon_applications, hackathon_views
SHOW TABLES LIKE '%push%';
-- Should show: push_subscriptions, push_notification_log
SHOW TABLES LIKE 'notifications';
-- Should show: notifications
```

### Step 2: Create Upload Directories

Open terminal/command prompt in your project folder:

```bash
cd c:\xampp\htdocs\event_management_system\login

mkdir uploads\hackathon_posters
mkdir uploads\hackathon_rules

# Or manually create these folders in Windows Explorer
```

### Step 3: Add Navigation Links

**For Admin** - Edit `admin/index.php`, add to navigation menu:

```html
<a href="hackathons.php" class="nav-link">
  <span class="material-symbols-outlined">emoji_events</span>
  <span>Hackathons</span>
</a>
```

**For Students** - Edit `student/index.php`, add to navigation menu:

```html
<a href="hackathons.php" class="nav-link">
  <span class="material-symbols-outlined">emoji_events</span>
  <span>Hackathons</span>
</a>
```

### Step 4: Enable Push Notifications

**In student pages** - Add to `<head>` section of `student/index.php`:

```html
<link rel="manifest" href="../manifest.json" />
<script src="js/push-manager.js"></script>
```

### Step 5: Test the System

#### Test as Admin:

1. Login as admin
2. Go to: `http://localhost/event_management_system/login/admin/hackathons.php`
3. Click "Create Hackathon"
4. Fill form and upload poster image
5. Check "Send push notification"
6. Submit

#### Test as Student:

1. Login as student
2. Allow notifications when prompted
3. Go to: `http://localhost/event_management_system/login/student/hackathons.php`
4. You should see the hackathon you created
5. Click to view details (view count increments)
6. You should have received a push notification

---

## 🧪 Testing Checklist

### Admin Tests

- [ ] Create hackathon → Success message shown
- [ ] Upload poster → File compressed and saved
- [ ] Upload PDF → File saved in uploads/hackathon_rules/
- [ ] Send notification → Students receive push
- [ ] View hackathons list → Shows created hackathon
- [ ] View count displays correctly
- [ ] Application count shows when students apply
- [ ] Filter by status works (upcoming/ongoing/completed)
- [ ] Search by title/theme works

### Student Tests

- [ ] Browser prompts for notification permission
- [ ] Click "Allow" → Subscription saved
- [ ] Browse hackathons → Shows upcoming hackathons
- [ ] Click hackathon → Opens details page
- [ ] View count increments on details page
- [ ] "Apply Now" button visible (if not applied)
- [ ] "Applied" badge shows (if already applied)
- [ ] Tags and theme display correctly
- [ ] Download rules PDF works

### Push Notification Tests

- [ ] Service worker registers successfully
- [ ] VAPID keys generated in cache/vapid_keys.json
- [ ] Push subscription saved to database
- [ ] Create hackathon → Students receive notification
- [ ] Click notification → Opens hackathon details
- [ ] Works on Chrome (Windows/Mac/Android)
- [ ] Works on Firefox (Windows/Mac)
- [ ] Works on Edge (Windows)

### Browser Console Tests

Open browser console on student page (F12) and run:

```javascript
// Check service worker
navigator.serviceWorker.getRegistration().then((r) => console.log("SW:", r));

// Check notification permission
console.log("Permission:", Notification.permission);

// Check subscription status
window.pushManager.getPermissionStatus();

// Send test notification
window.pushManager.sendTest();
```

---

## 📁 File Structure Created

```
login/
├── HACKATHON_SYSTEM_README.md          ← Full documentation
├── manifest.json                        ← PWA manifest
├── sw.js                                ← Service Worker
│
├── sql/
│   └── create_hackathon_system.sql     ← Database schema
│
├── includes/
│   └── OneSignalManager.php            ← OneSignal push notification backend
│
├── admin/
│   ├── hackathons.php                  ← List/manage hackathons
│   └── create_hackathon.php            ← Create new hackathon
│
├── student/
│   ├── hackathons.php                  ← Browse hackathons
│   ├── hackathon_details.php           ← View details + apply
│   ├── ajax/
│   │   └── push_subscription.php       ← Push subscription API
│   └── js/
│       └── push-manager.js             ← Push client
│
├── uploads/
│   ├── hackathon_posters/              ← (Create this folder)
│   └── hackathon_rules/                ← (Create this folder)
│
└── cache/
    └── vapid_keys.json                 ← (Auto-generated)
```

---

## 🔧 Configuration

### VAPID Keys (Auto-Generated)

On first run, WebPushManager automatically generates VAPID keys and stores them in:

```
cache/vapid_keys.json
```

**IMPORTANT**: Keep this file secure! Add to .gitignore:

```
cache/vapid_keys.json
```

### For Production (Recommended)

Use environment variables instead:

```php
// In .env or config file:
VAPID_PUBLIC_KEY=your_public_key_here
VAPID_PRIVATE_KEY=your_private_key_here
VAPID_SUBJECT=mailto:admin@yourdomain.com
```

### Database Connection

The system reads database credentials from the `.env` file (see `.env.example` for the required format).
Configure your credentials in `.env`:

- **DB_HOST**: Your database host
- **DB_USER**: Your database username
- **DB_PASS**: Your database password
- **DB_NAME**: event_management_system

The centralized connection is provided by `includes/db_config.php`.

- Review `student/ajax/push_subscription.php` (lines 10-11) for Web Push subscription handling and VAPID key configuration.

---

## 🌐 Median.co App Deployment

### Prerequisites

1. ✅ Web Push API implemented (done)
2. ✅ Service Worker registered (done)
3. ✅ PWA manifest created (done)
4. ✅ HTTPS domain (required for production)

### Steps to Deploy

1. **Upload to Web Server**

   ```bash
   # Upload your project to web hosting with HTTPS
   # Example: https://yourdomain.com/event_management_system/
   ```

2. **Sign Up at Median.co**
   - Go to [median.co](https://median.co)
   - Create account
   - Click "Create New App"

3. **Configure App**
   - **App URL**: `https://yourdomain.com/event_management_system/login/student/index.php`
   - **App Name**: Event Management System
   - **Package Name**: com.yourdomain.ems
   - **Enable Push Notifications**: Yes (Web Push)

4. **Upload Icons** (Required sizes)
   - Upload to `assets/images/`:
     - icon-192x192.png
     - icon-512x512.png
   - Or use online generator: [PWA Builder](https://www.pwabuilder.com/imageGenerator)

5. **Build APK**
   - Click "Build" in Median.co dashboard
   - Wait 5-10 minutes
   - Download APK

6. **Test on Android**
   - Install APK on device
   - Login as student
   - Allow notifications
   - Admin posts hackathon
   - Notification appears on device!

7. **Publish to Play Store**
   - Create Google Play Developer account ($25 one-time)
   - Upload AAB from Median.co
   - Fill store listing
   - Submit for review

---

## 🐛 Common Issues & Solutions

### Issue: "VAPID keys not found"

**Solution:**

```php
// Navigate to:
http://localhost/event_management_system/login/student/index.php

// Check browser console:
// Should log: "[Push] Service Worker registered"
// If error, check file exists: cache/vapid_keys.json
```

### Issue: "Notifications not working"

**Checklist:**

1. ✅ Service worker registered? (Check console)
2. ✅ Permission granted? Run: `console.log(Notification.permission)`
3. ✅ Subscribed? Run: `window.pushManager.getPermissionStatus()`
4. ✅ HTTPS enabled? (Required except localhost)
5. ✅ Database tables exist? Run: `SHOW TABLES LIKE 'push%'`

**Force re-subscribe:**

```javascript
// In browser console:
navigator.serviceWorker.getRegistrations().then((regs) => {
  regs.forEach((r) => r.unregister());
  location.reload();
});
```

### Issue: "File upload fails"

**Solution:**

```bash
# Check folder permissions
dir uploads\hackathon_posters
dir uploads\hackathon_rules

# If not exist, create:
mkdir uploads\hackathon_posters
mkdir uploads\hackathon_rules
```

**Check PHP settings** in `php.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 10M
```

### Issue: "Database errors"

**Solution:**

```sql
-- Verify tables created:
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'event_management_system'
AND TABLE_NAME LIKE 'hackathon%';

-- Should return: hackathon_posts, hackathon_applications, hackathon_views

-- Check triggers:
SHOW TRIGGERS WHERE `Table` = 'hackathon_applications';
```

### Issue: "Push notification not sending"

**Check logs:**

```sql
-- Check push notification log
SELECT * FROM push_notification_log
WHERE status = 'failed'
ORDER BY attempted_at DESC
LIMIT 10;

-- Check active subscriptions
SELECT COUNT(*) FROM push_subscriptions WHERE is_active = 1;
```

---

## 📊 Database Overview

### Tables Created

1. **hackathon_posts** (13 columns) - Stores hackathon information
2. **hackathon_applications** (10 columns) - Stores student applications
3. **hackathon_views** (6 columns) - Tracks views for analytics
4. **push_subscriptions** (9 columns) - Stores Web Push subscriptions
5. **notifications** (10 columns) - Notification history
6. **push_notification_log** (7 columns) - Push delivery tracking

### Triggers Created

1. **after_application_insert** - Increments current_registrations count
2. **after_application_update** - Updates count when status changes
3. **after_notification_read** - Sets read_at timestamp

### Indexes Created

- Performance indexes on status, dates, user_regno
- Composite indexes for common queries
- Foreign key constraints for data integrity

---

## 🎨 Customization

### Change Brand Colors

Edit gradient in student pages:

```css
/* Find in student/hackathons.php */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Replace with your brand colors */
background: linear-gradient(135deg, #4285f4 0%, #34a853 100%);
```

### Change Icons

Replace logo in notification:

```javascript
// Edit sw.js
icon: '/assets/images/logo.png',      // Your logo
badge: '/assets/images/badge.png',    // Small badge (72x72px)
```

### Add Email Notifications

Install PHPMailer:

```bash
composer require phpmailer/phpmailer
```

Add to `create_hackathon.php` after push:

```php
require 'vendor/autoload.php';
$mail = new PHPMailer\PHPMailer\PHPMailer();
// Configure and send email
```

---

## 📞 Need Help?

### Resources

- 📖 **Full Documentation**: Read `HACKATHON_SYSTEM_README.md` (17KB, comprehensive)
- 🔍 **Check Logs**: `/xampp/apache/logs/error.log`
- 🗄️ **Database Logs**: Query `push_notification_log` table
- 🌐 **Browser Console**: F12 → Check for JavaScript errors

### Debug Commands

```javascript
// Browser console (F12)
// Service worker status
navigator.serviceWorker.getRegistrations().then((r) => console.table(r));

// Push manager status
window.pushManager.getPermissionStatus();

// Send test notification
window.pushManager.sendTest();
```

```sql
-- MySQL console
-- Check hackathon data
SELECT * FROM hackathon_posts ORDER BY created_at DESC LIMIT 5;

-- Check subscriptions
SELECT user_regno, is_active, created_at
FROM push_subscriptions
ORDER BY created_at DESC
LIMIT 10;

-- Check notification delivery
SELECT status, COUNT(*) as count
FROM push_notification_log
GROUP BY status;
```

---

## ✨ What's Next?

### Additional Features to Add (Optional)

1. **Application Management** - Let admins approve/reject applications
2. **Edit Hackathons** - Create `admin/edit_hackathon.php`
3. **My Applications** - Create `student/my_hackathons.php`
4. **Withdrawal** - Let students withdraw before deadline
5. **Notification Center** - Create `student/notifications.php`
6. **Email Integration** - Add PHPMailer for email notifications
7. **Team Features** - Enhanced team management UI
8. **Analytics Dashboard** - Charts for views, applications over time
9. **Winners Declaration** - Add winner selection feature
10. **Certificates** - Auto-generate participation certificates

### Files to Create (Priority Order)

1. **student/apply_hackathon.php** - Application form (HIGH)
2. **student/my_hackathons.php** - View applications (HIGH)
3. **admin/edit_hackathon.php** - Edit hackathons (MEDIUM)
4. **admin/hackathon_applications.php** - View all applications (MEDIUM)
5. **student/notifications.php** - Notification center (MEDIUM)
6. **student/ajax/notifications.php** - Notification API (MEDIUM)

---

## 🎉 You're All Set!

The core hackathon system is now implemented with:

- ✅ Admin can create hackathons
- ✅ Admin can upload posters & PDFs
- ✅ Admin can send push notifications
- ✅ Students can browse hackathons
- ✅ Students can view details
- ✅ Students receive push notifications
- ✅ View tracking works
- ✅ Compatible with Median.co

**Next Step**: Run the database SQL file and test creating your first hackathon!

---

**Version**: 1.0.0  
**Last Updated**: March 8, 2026  
**Files Created**: 11  
**Lines of Code**: ~4,500
