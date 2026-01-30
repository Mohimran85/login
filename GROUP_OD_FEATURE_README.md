# Group OD Request Feature Implementation

## Overview

This update adds support for **Group OD (On-Duty) Requests**, allowing multiple students to be part of a single OD request. All group members can view and download the OD letter once approved.

## Changes Made

### 1. Database Changes

**File:** [sql/add_group_members_column.sql](sql/add_group_members_column.sql)

- Added `group_members` TEXT column to `od_requests` table
- Stores comma-separated registration numbers for group members
- Added index for better search performance

**Migration:** The column is automatically added when creating OD requests (backward compatible)

### 2. Teacher Approval Page Updates

**File:** [teacher/od_approvals.php](teacher/od_approvals.php)

**Features Added:**

- Displays group members with their names in a styled card
- Shows avatar icons for each group member
- SQL query updated to fetch `group_members` column
- Database connection kept open for group member queries
- Responsive grid layout for group members

**UI Enhancements:**

- Group members section with distinct styling (cyan/teal theme)
- Hover effects on group member cards
- Mobile-responsive design

### 3. Student OD Request Form Updates

**File:** [student/od_request.php](student/od_request.php)

**Features Added:**

- **Add Group Member button** - allows adding multiple registration numbers
- Dynamic form fields for group members with remove functionality
- Input validation for registration numbers
- Group members saved as comma-separated values
- SQL query updated to show ODs where student is main requester OR group member

**UI Enhancements:**

- Styled group member input fields with icons
- Smooth animations for add/remove actions
- Group indicator badge in OD request list showing member count

**JavaScript Functions:**

- `addGroupMember()` - adds new registration number input field
- `removeGroupMember(id)` - removes a group member field with animation

### 4. OD Letter Access Updates

**File:** [student/download_od_letter.php](student/download_od_letter.php)

**Features Added:**

- Updated SQL query to allow access if student is:
  - Main requester (student_regno) OR
  - Group member (in group_members column)
- Uses `FIND_IN_SET()` function to check membership

### 5. Backward Compatibility

**File:** [od_request_base.php](od_request_base.php)

- Added migration check for `group_members` column
- Automatically creates column if it doesn't exist

## How to Use

### For Students:

1. **Creating a Group OD Request:**
   - Go to OD Request page
   - Fill in event details
   - Click "Add Group Member" button
   - Enter registration numbers of group members
   - Click "Remove" to delete any member
   - Submit the request

2. **Viewing Group OD Requests:**
   - All OD requests (individual and group) appear in "My OD Requests"
   - Group ODs show a blue badge with group icon and member count
   - Group members can see the OD in their list

3. **Downloading OD Letter:**
   - Both main requester and group members can download the approved OD letter
   - Access is automatic for all group members

### For Teachers/Counselors:

1. **Reviewing Group OD Requests:**
   - Group OD requests show "Group Members" section
   - Displays count and list of all group members with names
   - Each member shown with avatar icon and registration number

2. **Approving/Rejecting:**
   - Approval/rejection works the same as individual ODs
   - All group members get access to the approved OD letter

## Technical Details

### Database Schema Addition

```sql
ALTER TABLE od_requests
ADD COLUMN group_members TEXT NULL
COMMENT 'Comma-separated registration numbers for group OD requests';
```

### SQL Query for Group Access

```sql
-- Allow access if student is main requester OR group member
WHERE odr.id = ?
AND (odr.student_regno = ?
     OR FIND_IN_SET(?, odr.group_members))
AND odr.status = 'approved'
```

### Data Storage Format

- Group members stored as: `"REG001,REG002,REG003"`
- Empty or NULL for individual OD requests
- No limit on number of group members

## Testing Checklist

- [x] Run SQL migration script
- [ ] Create group OD request with multiple members
- [ ] Verify group members appear in teacher approval page
- [ ] Verify all group members can view the OD request
- [ ] Verify all group members can download OD letter after approval
- [ ] Test individual OD requests still work (backward compatibility)
- [ ] Test mobile responsiveness of group member UI

## Files Modified

1. `sql/add_group_members_column.sql` (NEW)
2. `teacher/od_approvals.php`
3. `student/od_request.php`
4. `student/download_od_letter.php`
5. `od_request_base.php`

## Next Steps

1. Run the SQL migration:

   ```sql
   source sql/add_group_members_column.sql
   ```

   Or the system will auto-migrate on next OD request

2. Test the functionality thoroughly

3. Optional enhancements:
   - Add email notifications to all group members
   - Show group member names in student OD list (requires additional query)
   - Add bulk registration number input (paste multiple at once)
   - Validate registration numbers against student_register table

## Support

If you encounter any issues, check:

- Database connection is active
- `group_members` column exists in `od_requests` table
- JavaScript console for any errors in browser
- PHP error logs for server-side issues
