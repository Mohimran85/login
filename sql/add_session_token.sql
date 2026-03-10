-- Single-device session lock: adds session_token column to both user tables.
-- This migration is also auto-applied at runtime via ensureSessionTokenColumn() in index.php.

ALTER TABLE `student_register`
    ADD COLUMN IF NOT EXISTS `session_token` VARCHAR(64) DEFAULT NULL;

ALTER TABLE `teacher_register`
    ADD COLUMN IF NOT EXISTS `session_token` VARCHAR(64) DEFAULT NULL;
