# PR #3 Investigation Report

## Issue Summary
PR #3 "[WIP] Validate font family and signature data inputs" was created with a comprehensive security improvement plan, but the files referenced in the PR description do not exist in the repository.

## Investigation Findings

### Files Mentioned in PR #3 (That Don't Exist)
The following files are referenced in PR #3 but are NOT present in the repository:
- `teacher/digital_signature.php`
- `teacher/get_image.php`
- `teacher/internship_approvals.php`
- `teacher/od_approvals.php`
- `teacher/registered_students.php`
- `teacher/verify_events.php`
- `teacher/view_poster.php`
- `admin/manage_counselors.php`

### Files That Actually Exist
The repository contains these actual PHP files:
- `teacher/index.php` (344 lines)
- `teacher.php` (184 lines)
- `admin/index.php` (202 lines)
- `admin/profile.php` (411 lines)
- `admin/reports.php` (300 lines)
- `admin/participants.php` (254 lines)
- `admin/add_event.php` (144 lines)
- `admin/download.php`
- `admin/export_excel.php`
- `admin/logout.php`
- `student/index.php` (344 lines)
- `student/profile.php` (781 lines)
- `student/student_register.php` (727 lines)
- `student/student_participations.php` (742 lines)
- `index.php` (154 lines)
- `student.php` (213 lines)
- `forgot_password_dob.php` (239 lines)
- `thankyou.php` (188 lines)

### Security Issues Found in Existing Files
While the specific files in PR #3 don't exist, the existing files DO have some security concerns:

1. **Hardcoded Database Credentials**: Multiple files use hardcoded credentials:
   - Host: `localhost`
   - User: `root`
   - Password: `` (empty)
   - Database: `event_management_system`

2. **Session Management**: Files check `$_SESSION['logged_in']` but may not consistently validate user roles

3. **SQL Injection Protection**: Most files use prepared statements (good), but need review

## Recommendations

### Option 1: Close PR #3 and Create New PRs
Close PR #3 and create new, accurate PRs for actual security improvements needed in the existing files:
- Create config file for database credentials using environment variables
- Add CSRF protection where needed
- Improve session management and role validation
- Add security headers

### Option 2: Update PR #3 Description
Update PR #3's description to reflect the actual files in the repository and security improvements they need.

### Option 3: Clarify Repository Mismatch
Investigate if there's a repository mismatch - perhaps PR #3 was meant for a different repository or a different branch that has these files.

## Conclusion
PR #3 cannot be implemented as described because the target files don't exist. The repository needs to be updated with accurate security improvement tasks based on the files that actually exist.
