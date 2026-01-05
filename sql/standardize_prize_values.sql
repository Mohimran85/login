-- Standardize prize values in student_event_register table
-- This will convert all prize variations to lowercase: first, secound, third

UPDATE student_event_register 
SET prize = 'first' 
WHERE LOWER(TRIM(prize)) IN ('first', 'first prize', '1st', '1st prize');

UPDATE student_event_register 
SET prize = 'secound' 
WHERE LOWER(TRIM(prize)) IN ('second', 'secound', 'second prize', '2nd', '2nd prize');

UPDATE student_event_register 
SET prize = 'third' 
WHERE LOWER(TRIM(prize)) IN ('third', 'third prize', '3rd', '3rd prize');

-- Keep participation as is (lowercase)
UPDATE student_event_register 
SET prize = 'participation' 
WHERE LOWER(TRIM(prize)) IN ('participation', 'participated');

-- Show results
SELECT DISTINCT prize, COUNT(*) as count 
FROM student_event_register 
WHERE prize IS NOT NULL AND prize != ''
GROUP BY prize;
