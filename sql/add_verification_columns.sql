-- Add verification columns to student_event_register table
-- This enables counselor validation workflow similar to OD approvals

USE event_management_system;

-- Add verification_status column (Pending, Approved, Rejected)
ALTER TABLE student_event_register 
ADD COLUMN IF NOT EXISTS verification_status VARCHAR(20) DEFAULT 'Pending' AFTER prize_amount;

-- Add verified_by column (faculty_id of counselor who verified)
ALTER TABLE student_event_register 
ADD COLUMN IF NOT EXISTS verified_by INT AFTER verification_status;

-- Add verified_date column (timestamp of verification)
ALTER TABLE student_event_register 
ADD COLUMN IF NOT EXISTS verified_date DATETIME AFTER verified_by;

-- Add rejection_reason column (reason if rejected)
ALTER TABLE student_event_register 
ADD COLUMN IF NOT EXISTS rejection_reason TEXT AFTER verified_date;

-- Add created_at column if it doesn't exist
ALTER TABLE student_event_register 
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Create index for faster queries on verification_status
CREATE INDEX IF NOT EXISTS idx_verification_status ON student_event_register(verification_status);

-- Create index for faster queries on regno (used in JOIN)
CREATE INDEX IF NOT EXISTS idx_regno ON student_event_register(regno);

SELECT 'Verification columns added successfully!' AS message;
