-- Add no_of_days column to student_event_register table
-- Run this SQL script to add the number of days field

ALTER TABLE student_event_register 
ADD COLUMN IF NOT EXISTS no_of_days INT NULL AFTER end_date;

-- Calculate no_of_days for existing records
UPDATE student_event_register 
SET no_of_days = DATEDIFF(end_date, start_date) + 1 
WHERE start_date IS NOT NULL AND end_date IS NOT NULL AND no_of_days IS NULL;

-- Set remaining NULL values to default 1
UPDATE student_event_register 
SET no_of_days = 1 
WHERE no_of_days IS NULL;

-- Now make column NOT NULL with default
ALTER TABLE student_event_register 
MODIFY COLUMN no_of_days INT NOT NULL DEFAULT 1 COMMENT 'Number of days for the event (calculated from date range)';
