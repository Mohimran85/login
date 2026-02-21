-- Add hackathon_link column to hackathon_posts table
-- Purpose: Store external registration or information links for hackathons
-- Created: 2026-02-21

ALTER TABLE hackathon_posts 
ADD COLUMN hackathon_link VARCHAR(500) DEFAULT NULL COMMENT 'External registration or information link' 
AFTER rules_pdf;
