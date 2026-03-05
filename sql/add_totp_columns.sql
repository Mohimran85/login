-- Add TOTP Two-Factor Authentication columns to user tables
-- Run this migration to enable 2FA support

-- Add 2FA columns to student_register
ALTER TABLE student_register
    ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(128) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS totp_recovery_codes TEXT DEFAULT NULL;

-- Add 2FA columns to teacher_register
ALTER TABLE teacher_register
    ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(128) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS totp_recovery_codes TEXT DEFAULT NULL;
