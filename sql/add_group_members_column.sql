-- Add group_members column to od_requests table
-- This column will store comma-separated registration numbers for group OD requests
-- If empty or NULL, it's a single student OD request

ALTER TABLE od_requests 
ADD COLUMN IF NOT EXISTS group_members TEXT NULL 
COMMENT 'Comma-separated registration numbers for group OD requests';

-- Add index for better search performance on group members
-- Drop index if exists first to make idempotent
DROP INDEX IF EXISTS idx_group_members ON od_requests;
CREATE INDEX idx_group_members ON od_requests (group_members(255));
