-- Add hackathon coordinator flag to teacher_register
-- This allows any teacher/counselor/admin to also manage hackathons
ALTER TABLE teacher_register
ADD COLUMN is_hackathon_coordinator TINYINT(1) NOT NULL DEFAULT 0
AFTER status;
