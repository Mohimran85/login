-- Update student_event_register table to replace attended_date with start_date and end_date
-- Run this SQL script to modify the date fields

-- Add new date columns
ALTER TABLE student_event_register 
ADD COLUMN IF NOT EXISTS start_date DATE NULL AFTER event_name,
ADD COLUMN IF NOT EXISTS end_date DATE NULL AFTER start_date;

-- Copy existing attended_date to start_date (if you want to preserve data)
UPDATE student_event_register 
SET start_date = attended_date, end_date = attended_date 
WHERE attended_date IS NOT NULL;

-- Set default value for NULL attended_date records
UPDATE student_event_register 
SET start_date = CURRENT_DATE, end_date = CURRENT_DATE 
WHERE start_date IS NULL;

-- Verify no NULL values remain before making NOT NULL
-- SELECT COUNT(*) FROM student_event_register WHERE start_date IS NULL OR end_date IS NULL;

-- Drop the old attended_date column
ALTER TABLE student_event_register 
DROP COLUMN IF EXISTS attended_date;

-- Update column comments for clarity and make NOT NULL
ALTER TABLE student_event_register 
MODIFY COLUMN start_date DATE NOT NULL COMMENT 'Event start date',
MODIFY COLUMN end_date DATE NOT NULL COMMENT 'Event end date';
