# Deployment Checklist — Event Management System

Generated: 2026-03-11

---

## 1. Environment Configuration

- [ ] Copy `.env.example` → `.env` on the production server
- [ ] Set correct values in `.env`:
  ```
  DB_HOST=
  DB_USER=
  DB_PASS=
  DB_NAME=event_management_system
  ONESIGNAL_APP_ID=
  ONESIGNAL_REST_API_KEY=
  APP_URL=https://yourdomain.com/login
  ```
- [ ] **Never commit `.env` to version control** (already in `.gitignore`)

---

## 2. Required PHP Extensions

Verify these are enabled in `php.ini`:

- `mysqli`
- `mbstring`
- `gd` (image processing / QR codes)
- `zip`
- `openssl` (TOTP 2FA)
- `curl` (OneSignal push notifications)
- `fileinfo`

---

## 3. Database Setup (fresh server)

Run the SQL scripts in this order:

```
sql/create_hackathon_system.sql
sql/create_internship_submissions_table.sql
sql/add_session_token.sql
sql/add_totp_columns.sql
sql/add_verification_columns.sql
sql/add_semester_column.sql
sql/add_class_counselor_column.sql
sql/add_hackathon_coordinator_column.sql
sql/add_hackathon_id_to_notifications.sql
sql/add_hackathon_link_column.sql
sql/add_group_members_column.sql
sql/add_no_of_days_column.sql
sql/add_event_photo_column.sql
sql/add_internship_approval_columns.sql
sql/add_internship_approval_status.sql
sql/run_migration_group_members.sql
sql/standardize_prize_values.sql
sql/update_event_dates.sql
sql/update_semester_values.sql
```

**Required tables in production database:**

- `student_register`
- `teacher_register`
- `od_requests`
- `student_event_register`
- `staff_event_reg`
- `internship_submissions`
- `hackathon_posts`
- `hackathon_applications`
- `hackathon_views`
- `hackathon_reminders`
- `notifications`
- `counselor_assignments`

---

## 4. Directory Permissions

Set write permissions on these folders (e.g. `chmod 755` on Linux):

| Path                        | Purpose                             |
| --------------------------- | ----------------------------------- |
| `uploads/`                  | OD request event posters            |
| `uploads/certificates/`     | Student certificates                |
| `uploads/internship/`       | Internship submission files         |
| `student/uploads/`          | Student-uploaded files              |
| `student/uploads/qr_codes/` | Generated QR code images            |
| `teacher/signatures/`       | Teacher signature image uploads     |
| `logs/`                     | Application error/cron logs         |
| `cache/`                    | Reminder run timestamps, VAPID keys |
| `assets/images/`            | Site images                         |

> On Windows (IIS/XAMPP): grant `IIS_IUSRS` or `IUSR` write access to above folders.

---

## 5. Composer Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

Dependencies needed at runtime (check `vendor/`):

- `endroid/qr-code` — QR code generation
- `phpmailer/phpmailer` — Email sending (if used)
- `paragonie/constant_time_encoding` — TOTP

---

## 6. Web Server Configuration

### Apache (`.htaccess` / `httpd.conf`)

- Enable `mod_rewrite`
- Deny direct access to sensitive folders:
  ```apache
  <DirectoryMatch "(sql|logs|cache|includes|vendor)">
    Require all denied
  </DirectoryMatch>
  ```
- Ensure `.env` is not web-accessible:
  ```apache
  <Files ".env">
    Require all denied
  </Files>
  ```

### PHP (`php.ini`)

```ini
display_errors = Off
log_errors = On
error_log = /path/to/logs/php_errors.log
session.cookie_httponly = 1
session.cookie_secure = 1      ; only if HTTPS
session.use_strict_mode = 1
upload_max_filesize = 10M
post_max_size = 12M
```

---

## 7. Cron Job (Hackathon Reminders)

Add to server crontab to send scheduled hackathon reminders:

```
* * * * * php /path/to/login/cron_reminders.php >> /path/to/login/logs/cron.log 2>&1
```

---

## 8. HTTPS / SSL

- [ ] SSL certificate installed on production domain
- [ ] All HTTP redirected to HTTPS in Apache/Nginx config
- [ ] Update `APP_URL` in `.env` to `https://`

---

## 9. Security Pre-Launch Checklist

- [ ] `.env` not accessible via browser
- [ ] `sql/`, `logs/`, `cache/`, `includes/`, `vendor/` blocked from direct web access
- [ ] No `*.phar`, `*.sql`, `*.txt`, `*.md` files in web root that expose schema or config
- [ ] `display_errors` is `Off` in `php.ini`
- [ ] Confirm no test/debug scripts are present (all removed in this cleanup pass)
- [ ] TOTP (2FA) keys stored only in DB, not in files
- [ ] File upload directory has no PHP execution enabled

---

## 10. Post-Deployment Verification

- [ ] Login page loads: `https://yourdomain/login/`
- [ ] Student registration works
- [ ] Teacher login & verify events works
- [ ] OD request submission & approval works
- [ ] Hackathon listing loads for students
- [ ] Push notifications (OneSignal) fire on hackathon apply
- [ ] Cron reminders log entries appear in `logs/` after one minute
- [ ] Certificate download generates correct PDF/ZIP
