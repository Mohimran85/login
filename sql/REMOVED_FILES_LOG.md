# Deployment Cleanup — Removed Files Log

## Date: 2026-03-11

## Purpose: Record of all files deleted during pre-deployment cleanup passes

## To restore: `git checkout <file>` or `git restore <file>` from git history.

---

## Pass 1 — Debug / Test Scripts

| File                              | Reason removed                                      |
| --------------------------------- | --------------------------------------------------- |
| `debug_notifications.php`         | Admin debug page — exposes notification/DB info     |
| `debug_onesignal.php`             | Admin debug page — prints partial API key info      |
| `test_onesignal.php`              | Test console — exposes OneSignal config checks      |
| `verify_env.php`                  | Prints .env key presence — not needed in production |
| `admin/test_winners.php`          | Dev diagnostic — exposes raw DB query results       |
| `admin/test_status_update.php`    | Dev diagnostic — direct DB mutation tool            |
| `admin/test_hackathon_update.php` | Dev diagnostic — full hackathon field dump          |
| `admin/ajax/test_simple.php`      | Dev AJAX test stub                                  |

## Pass 2 — Documentation / Setup / Diagnostic Files

| File                                | Reason removed                                         |
| ----------------------------------- | ------------------------------------------------------ |
| `HACKATHON_SYSTEM_README.md`        | Dev documentation — not needed at runtime              |
| `NOTIFICATION_SYSTEM_FIX.md`        | Dev fix notes — not needed at runtime                  |
| `QUICK_SETUP_GUIDE.md`              | Dev setup guide — replaced by DEPLOYMENT_CHECKLIST.md  |
| `od_request_main.txt`               | Dev scratch notes                                      |
| `todo.txt`                          | Dev task list                                          |
| `check_subscriptions.php`           | Admin diagnostic — OneSignal subscription checker      |
| `composer.phar`                     | Composer binary — not needed if vendor/ is present     |
| `admin/check_status_column.php`     | Diagnostic — hackathon status column check             |
| `admin/check_status_db.php`         | Diagnostic — DB status field inspector                 |
| `admin/status_fix_tools.php`        | Diagnostic — status fix helper tool                    |
| `admin/status_troubleshooting.php`  | Empty placeholder file                                 |
| `admin/README_STATUS_FIX.txt`       | Dev fix notes                                          |
| `admin/STATUS_FIX_DOCUMENTATION.md` | Dev fix documentation                                  |
| `admin/create_signature_table.sql`  | Migration SQL — signature table dropped (see rollback) |

## Database Tables Dropped

| Table                | Reason removed                                  |
| -------------------- | ----------------------------------------------- |
| `teacher_signatures` | Digital signature feature not needed for launch |

---

## Restore Instructions

### Restore deleted files from git:

```bash
git restore <filename>
# or for a specific commit:
git checkout <commit-hash> -- <filename>
```

### Restore database table:

```bash
mysql -u root -D event_management_system < sql/rollback_deployment.sql
```

---

## Files Kept (required at runtime)

**Root-level PHP:**
`index.php`, `student.php`, `teacher.php`, `verify_od.php`, `verify_2fa.php`,
`setup_2fa.php`, `forgot_password_dob.php`, `cron_reminders.php`, `scripts.js`,
`styles.css`, `sw.js`, `role.html`, `manifest.json`

**Key subdirectory files:**

- `admin/` — all CRUD/management pages
- `student/` — all student-facing pages
- `teacher/` — all teacher-facing pages
- `includes/` — shared libraries (db_config, security, session, TOTP, etc.)
- `api/` — save_player_id.php (OneSignal device registration)
- `vendor/` — Composer autoloaded packages
- `sql/` — DB setup scripts (needed for fresh-server deployment)
