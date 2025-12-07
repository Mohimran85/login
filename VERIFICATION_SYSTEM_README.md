# Event Registration Verification System

## Overview

This system implements a counselor/teacher approval workflow for student event registrations, similar to the OD (On Duty) approval system. All student event registrations now require verification by a counselor before being officially approved.

## Features Implemented

### 1. **Database Schema Updates**

New columns added to `student_event_register` table:

- `verification_status` (VARCHAR(20), DEFAULT 'Pending') - Status of verification
- `verified_by` (INT) - Faculty ID of counselor who verified
- `verified_date` (DATETIME) - Timestamp of verification
- `rejection_reason` (TEXT) - Reason if registration is rejected
- `created_at` (TIMESTAMP) - When registration was submitted

Indexes added for performance:

- `idx_verification_status` on verification_status column
- `idx_regno` on regno column

### 2. **Teacher/Counselor Portal - Event Verification Page**

**File:** `teacher/verify_events.php`

#### Functionality:

- **View Pending Registrations**: Counselors can see all pending student event registrations
- **Filter Options**:
  - Event Category (Workshop, Symposium, Conference, Hackathon, Seminar, Paper Presentation, Webinar, Competition, Cultural)
  - Status (Pending, Approved, Rejected)
- **Actions**:
  - **Approve**: Marks registration as "Approved" with counselor ID and timestamp
  - **Reject**: Opens modal to enter rejection reason, marks as "Rejected"
- **View Documents**: Counselors can view:
  - Event certificates (PDF)
  - Event photos (images)
  - Event posters (PDF)

#### Database Changes:

- Updated queries to use `student_event_register` table (was using non-existent `event_certificates` table)
- Fixed JOIN to use `regno` instead of `student_id`
- Uses `COALESCE(verification_status, 'Pending')` to handle NULL values

### 3. **Student Portal - Verification Status Display**

**File:** `student/student_participations.php`

#### New Features:

- **Verification Badge**: Each event registration now displays a color-coded status badge:
  - 🏆 **Pending** (Yellow) - Awaiting counselor review
  - ✅ **Approved** (Green) - Verified by counselor
  - ❌ **Rejected** (Red) - Rejected with reason

#### Badge Styling:

```css
.verification-pending
  -
  Yellow
  background
  (#fff3cd)
  .verification-approved
  -
  Green
  background
  (#d4edda)
  .verification-rejected
  -
  Red
  background
  (#f8d7da);
```

## Workflow

### Student Submits Event Registration:

1. Student fills form in `student/student_register.php`
2. Registration is saved with `verification_status = 'Pending'`
3. Student can view status in "My Participations" page

### Counselor Reviews Registration:

1. Counselor accesses `teacher/verify_events.php`
2. Views pending registrations with filters
3. Reviews event certificates and documents
4. Takes action:
   - **Approve**: Instant approval
   - **Reject**: Provide reason for rejection

### Student Views Status:

1. Student checks `student/student_participations.php`
2. Sees verification badge on each event:
   - Pending: Waiting for review
   - Approved: Can proceed with event
   - Rejected: Can see reason and resubmit

## Files Modified

### 1. `teacher/verify_events.php`

- Updated approval/rejection handlers to use `student_event_register` table
- Changed `cert_id` to `event_id` throughout
- Updated SQL queries to join with `student_register` using `regno`
- Added support for viewing event photos
- Updated modal text from "certificate" to "event registration"

### 2. `student/student_participations.php`

- Added `COALESCE(verification_status, 'Pending')` to SELECT query
- Added verification status badge display
- Added CSS styles for verification badges

### 3. `sql/add_verification_columns.sql` (NEW)

- SQL script to add verification columns
- Creates indexes for performance
- Safe to run multiple times (uses IF NOT EXISTS)

## Installation

### Run the SQL script:

```bash
cd c:\xampp\mysql\bin
Get-Content "c:\xampp\htdocs\event_management_system\login\sql\add_verification_columns.sql" | .\mysql.exe -u root
```

Or import via phpMyAdmin:

1. Open phpMyAdmin
2. Select `event_management_system` database
3. Go to Import tab
4. Select `sql/add_verification_columns.sql`
5. Click "Go"

## Usage

### For Students:

1. Register for events as usual via `student/student_register.php`
2. Check verification status in `student/student_participations.php`
3. Wait for counselor approval (status shows as "Pending")
4. Once approved, status changes to "Approved" ✅
5. If rejected, status shows "Rejected" ❌ and reason is provided

### For Counselors:

1. Access `teacher/verify_events.php` from Teacher Portal
2. Use filters to view specific event categories or statuses
3. Click "View Cert" to review student's certificate/documents
4. Click "View Photo" to see event photo (if uploaded)
5. Click "Approve" to instantly approve
6. Click "Reject" to open modal and provide rejection reason
7. View approved/rejected history by changing status filter

## Security Features

- Prepared statements prevent SQL injection
- Session validation checks user role
- Only counselors/teachers can access verification page
- HTMLSPECIALCHARS prevents XSS attacks
- File uploads validated (PDF for certificates, images for photos)

## Future Enhancements

- Email notifications to students on approval/rejection
- Bulk approval feature for counselors
- Analytics dashboard showing approval rates
- Student resubmission feature after rejection
- Rejection reason display to students
- History log of all verifications

## Support

For issues or questions, contact the system administrator.

---

**Last Updated:** December 7, 2025
**Version:** 1.0
