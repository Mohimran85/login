-- Add is_class_counselor column to teacher_register table
-- This column will identify which counselors are designated as class counselors

ALTER TABLE teacher_register 
ADD COLUMN IF NOT EXISTS is_class_counselor TINYINT(1) DEFAULT 0 COMMENT 'Indicates if the teacher is a class counselor (1) or not (0)';

-- Add index for better query performance when filtering by class counselors
CREATE INDEX idx_class_counselor ON teacher_register(is_class_counselor);
