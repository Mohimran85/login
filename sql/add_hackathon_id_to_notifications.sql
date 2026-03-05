-- Add hackathon_id column to notifications table for better tracking
-- This migration links notifications to specific hackathons

ALTER TABLE notifications 
ADD COLUMN IF NOT EXISTS hackathon_id INT DEFAULT NULL AFTER user_regno;

-- Add index separately (only if not already present)
CREATE INDEX IF NOT EXISTS idx_hackathon_id ON notifications(hackathon_id);
