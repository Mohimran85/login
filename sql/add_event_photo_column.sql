-- Add event_photo column to student_event_register table
-- Run this SQL script to add the optional event photo field

ALTER TABLE student_event_register 
ADD COLUMN IF NOT EXISTS event_photo VARCHAR(255) NULL AFTER certificates;

-- Update column comment for clarity
ALTER TABLE student_event_register 
MODIFY COLUMN event_photo VARCHAR(255) NULL COMMENT 'Optional: Photo from the event attended';
