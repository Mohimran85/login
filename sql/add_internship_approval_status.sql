-- Add approval status and counselor remarks to internship_submissions table
-- First, drop columns if they exist (to avoid duplicate column error)
ALTER TABLE `internship_submissions` 
DROP COLUMN IF EXISTS `approval_status`,
DROP COLUMN IF EXISTS `counselor_remarks`,
DROP COLUMN IF EXISTS `approved_by`,
DROP COLUMN IF EXISTS `approval_date`;

-- Now add the columns fresh
ALTER TABLE `internship_submissions` ADD COLUMN `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER `brief_report`;
ALTER TABLE `internship_submissions` ADD COLUMN `counselor_remarks` TEXT DEFAULT NULL AFTER `approval_status`;
ALTER TABLE `internship_submissions` ADD COLUMN `approved_by` INT DEFAULT NULL AFTER `counselor_remarks`;
ALTER TABLE `internship_submissions` ADD COLUMN `approval_date` DATETIME DEFAULT NULL AFTER `approved_by`;
