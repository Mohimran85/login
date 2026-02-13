# Security Recommendations for Existing Codebase

## Critical Issues (High Priority)

### 1. Hardcoded Database Credentials
**Impact**: High - Credentials are exposed in source code

**Affected Files**: Nearly all PHP files
- `teacher/index.php` (line 10)
- `admin/index.php` (line 17)
- `admin/profile.php`
- `student/profile.php`
- And many others...

**Current Code**:
```php
$conn = new mysqli("localhost", "root", "", "event_management_system");
```

**Recommended Fix**:
1. Create a `config.php` file (outside web root if possible)
2. Use environment variables (fail if not set):
```php
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

if (!$db_host || !$db_user || !$db_name) {
    error_log('Database configuration environment variables not set');
    die('Configuration error. Please contact support.');
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
```

### 2. Insufficient Session Validation
**Impact**: Medium - Users might access unauthorized areas

**Affected Files**:
- `teacher/index.php` - Only checks `logged_in` flag, not user role
- `admin/index.php` - Checks `logged_in` but not admin role specifically

**Current Code** (teacher/index.php):
```php
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}
```

**Issue**: Doesn't verify the user is actually a teacher

**Recommended Fix**:
```php
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ! isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}
```

### 3. Missing CSRF Protection
**Impact**: Medium - Forms vulnerable to Cross-Site Request Forgery

**Affected Files**: All forms in admin/, teacher/, and student/ directories

**Recommended Fix**:
1. Generate CSRF token on session start:
```php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

2. Add to all forms:
```html
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
```

3. Validate on form submission:
```php
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Invalid CSRF token');
}
```

## Medium Priority Issues

### 4. Error Information Disclosure
**Impact**: Low-Medium - Error messages might reveal system details

**Current**: Database connection errors show full error messages
```php
die("Connection failed: " . $conn->connect_error);
```

**Recommended**: Log errors server-side, show generic message to users:
```php
error_log("Database connection failed: " . $conn->connect_error);
die("A system error occurred. Please contact support.");
```

### 5. Cache Headers
**Impact**: Low - Sensitive pages might be cached

**Current**: Some files have cache control headers (admin/index.php), others don't

**Recommended**: Add to all authenticated pages:
```php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
```

### 6. SQL Injection Protection
**Status**: Generally Good - Most queries use prepared statements

**Action**: Audit all database queries to ensure none use string concatenation

### 7. XSS Protection
**Impact**: Medium - User input displayed without proper escaping

**Recommendation**: 
- Use `htmlspecialchars()` when outputting user data
- Set appropriate Content-Security-Policy headers

## Low Priority Issues

### 8. Session Fixation
**Recommendation**: Regenerate session ID after login:
```php
session_regenerate_id(true);
```

### 9. Password Hashing
**Action**: Verify passwords are stored using `password_hash()` with PASSWORD_DEFAULT

### 10. HTTPS Enforcement
**Recommendation**: Force HTTPS for all connections (accounting for reverse proxies):
```php
// Check if request is over HTTPS, accounting for proxies
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

if (!$is_https) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}
```

## Implementation Priority

### Phase 1 (Immediate)
1. Move database credentials to environment variables/config file
2. Add proper role-based access control to all protected pages
3. Add CSRF protection to all forms

### Phase 2 (Short-term)
4. Improve error handling (no information disclosure)
5. Add security headers (CSP, X-Frame-Options, etc.)
6. Audit for XSS vulnerabilities

### Phase 3 (Medium-term)
7. Implement session fixation protection
8. Review password hashing implementation
9. Add security logging

## Note on PR #3
The original PR #3 description references files that don't exist in this repository. This document provides security recommendations for the files that actually exist. Consider closing PR #3 and creating new, accurate PRs for these improvements.
