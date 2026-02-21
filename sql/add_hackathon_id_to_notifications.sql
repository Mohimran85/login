-- Add hackathon_id column to notifications table for better tracking
-- This migration links notifications to specific hackathons

ALTER TABLE notifications 
ADD COLUMN hackathon_id INT DEFAULT NULL AFTER user_regno,
ADD FOREIGN KEY (hackathon_id) REFERENCES hackathon_posts(id) ON DELETE CASCADE,
ADD INDEX idx_hackathon_id (hackathon_id);
