    -- Add semester column to student_register table
    ALTER TABLE student_register 
    ADD COLUMN semester VARCHAR(2) DEFAULT '3' AFTER department;

    -- Update all existing records to semester 3
    UPDATE student_register 
    SET semester = '3' 
    WHERE semester IS NULL OR semester = '';

    -- Verify the changes
    SELECT id, name, regno, department, semester FROM student_register LIMIT 10;
