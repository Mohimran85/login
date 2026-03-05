-- SQL Script to update semester values in student_event_register table
-- This converts "Odd"/"Even" semester values to numeric values (1-8)

-- ⚠️ IMPORTANT: Choose ONLY ONE option below. Do not run both!

-- OPTION 1: Update based on student's current semester from their profile
-- This updates event registrations to match the student's current semester in student_register table
/*
UPDATE student_event_register ser
JOIN student_register sr ON ser.regno = sr.regno
SET ser.semester = sr.semester
WHERE ser.semester IN ('Odd', 'Even') OR ser.semester IS NULL;
*/

-- OPTION 2: Set default values (UNCOMMENT to use)
-- Set Odd semesters to 3 and Even semesters to 4 as defaults
/*
UPDATE student_event_register
SET semester = '3'
WHERE semester = 'Odd';

UPDATE student_event_register
SET semester = '4'
WHERE semester IS NULL OR semester IN ('Odd', 'Even');

-- Verify the changes
SELECT semester, COUNT(*) as count
FROM student_event_register
GROUP BY semester
ORDER BY semester;

-- If you need to see specific records that were updated
SELECT ser.regno, sr.name, ser.event_name, ser.semester
FROM student_event_register ser
JOIN student_register sr ON ser.regno = sr.regno
ORDER BY ser.regno, ser.id;
*/
