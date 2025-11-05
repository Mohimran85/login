-- SQL to create teacher_signatures table
-- Run this in your MySQL database

CREATE TABLE IF NOT EXISTS teacher_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    signature_type ENUM('upload', 'drawn', 'text') NOT NULL DEFAULT 'upload',
    signature_data LONGTEXT NOT NULL, -- Base64 encoded signature or file path
    signature_hash VARCHAR(256) NOT NULL, -- Hash for verification
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (teacher_id) REFERENCES teacher_register(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_signature (teacher_id, is_active)
);

-- Add signature_id column to od_requests table to link approved requests with signatures
ALTER TABLE od_requests 
ADD COLUMN signature_id INT NULL,
ADD COLUMN digital_signature_hash VARCHAR(256) NULL,
ADD COLUMN signature_timestamp TIMESTAMP NULL,
ADD FOREIGN KEY (signature_id) REFERENCES teacher_signatures(id) ON DELETE SET NULL;