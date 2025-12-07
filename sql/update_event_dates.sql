-- Update student_event_register table to replace attended_date with start_date and end_date
-- Run this SQL script to modify the date fields

-- Add new date columns
ALTER TABLE student_event_register 
ADD COLUMN start_date DATE NULL AFTER event_name,
ADD COLUMN end_date DATE NULL AFTER start_date;

-- Copy existing attended_date to start_date (if you want to preserve data)
UPDATE student_event_register 
SET start_date = attended_date, end_date = attended_date 
WHERE attended_date IS NOT NULL;

-- Drop the old attended_date column
ALTER TABLE student_event_register 
DROP COLUMN attended_date;

-- Update column comments for clarity
ALTER TABLE student_event_register 
MODIFY COLUMN start_date DATE NOT NULL COMMENT 'Event start date',
MODIFY COLUMN end_date DATE NOT NULL COMMENT 'Event end date';
