-- Add approval status and counselor remarks to internship_submissions table
-- Use ADD COLUMN IF NOT EXISTS to preserve existing data

ALTER TABLE `internship_submissions` 
ADD COLUMN IF NOT EXISTS `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER `brief_report`;

ALTER TABLE `internship_submissions` 
ADD COLUMN IF NOT EXISTS `counselor_remarks` TEXT DEFAULT NULL AFTER `approval_status`;

ALTER TABLE `internship_submissions` 
ADD COLUMN IF NOT EXISTS `approved_by` INT DEFAULT NULL AFTER `counselor_remarks`;

ALTER TABLE `internship_submissions` 
ADD COLUMN IF NOT EXISTS `approval_date` DATETIME DEFAULT NULL AFTER `approved_by`;
