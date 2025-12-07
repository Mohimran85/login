-- Add no_of_days column to student_event_register table
-- Run this SQL script to add the number of days field

ALTER TABLE student_event_register 
ADD COLUMN no_of_days INT NULL AFTER end_date;

-- Update column comment for clarity
ALTER TABLE student_event_register 
MODIFY COLUMN no_of_days INT NOT NULL DEFAULT 1 COMMENT 'Number of days for the event (calculated from date range)';

-- Calculate no_of_days for existing records (if any)
UPDATE student_event_register 
SET no_of_days = DATEDIFF(end_date, start_date) + 1 
WHERE start_date IS NOT NULL AND end_date IS NOT NULL;
