-- Hackathon Management System Database Schema
-- Created: 2026-02-20
-- Purpose: Add hackathon posting, application, and push notification features

-- ============================================================================
-- 1. HACKATHON POSTS TABLE
-- Stores hackathon information posted by admins
-- ============================================================================
CREATE TABLE IF NOT EXISTS hackathon_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    organizer VARCHAR(255) NOT NULL,
    poster_url VARCHAR(255) DEFAULT NULL,
    rules_pdf VARCHAR(255) DEFAULT NULL,
    theme VARCHAR(100) DEFAULT NULL,
    tags VARCHAR(255) DEFAULT NULL COMMENT 'Comma-separated tags',
    
    -- Dates
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    registration_deadline DATETIME NOT NULL,
    
    -- Participation limits
    max_participants INT DEFAULT NULL COMMENT 'NULL = unlimited',
    current_registrations INT DEFAULT 0,
    
    -- Analytics
    view_count INT DEFAULT 0,
    
    -- Status
    status ENUM('draft', 'upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    
    -- Audit fields
    created_by INT NOT NULL COMMENT 'Admin user ID from teacher_register',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES teacher_register(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_registration_deadline (registration_deadline),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- 2. HACKATHON APPLICATIONS TABLE
-- Stores student applications for hackathons (individual or team)
-- ============================================================================
CREATE TABLE IF NOT EXISTS hackathon_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hackathon_id INT NOT NULL,
    student_regno VARCHAR(50) NOT NULL,
    
    -- Application type
    application_type ENUM('individual', 'team') NOT NULL DEFAULT 'individual',
    team_name VARCHAR(255) DEFAULT NULL,
    team_members JSON DEFAULT NULL COMMENT 'Array of {name, regno} objects',
    
    -- Project details
    project_description TEXT NOT NULL COMMENT 'What student/team plans to do',
    
    -- Status
    status ENUM('confirmed', 'withdrawn', 'completed') DEFAULT 'confirmed',
    
    -- Timestamps
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    withdrawn_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (hackathon_id) REFERENCES hackathon_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (student_regno) REFERENCES student_register(regno) ON DELETE CASCADE,
    
    -- Prevent duplicate applications
    UNIQUE KEY unique_application (hackathon_id, student_regno),
    
    INDEX idx_student_regno (student_regno),
    INDEX idx_status (status),
    INDEX idx_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- 3. HACKATHON VIEWS TABLE
-- Tracks when students view hackathon details (for analytics)
-- ============================================================================
CREATE TABLE IF NOT EXISTS hackathon_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hackathon_id INT NOT NULL,
    student_regno VARCHAR(50) NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6',
    user_agent TEXT DEFAULT NULL,
    
    FOREIGN KEY (hackathon_id) REFERENCES hackathon_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (student_regno) REFERENCES student_register(regno) ON DELETE CASCADE,
    
    INDEX idx_hackathon_id (hackathon_id),
    INDEX idx_student_regno (student_regno),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- 4. NOTIFICATIONS TABLE
-- Stores notification history for in-app notification center
-- ============================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_regno VARCHAR(50) NOT NULL COMMENT 'Target user registration number',
    
    -- Notification content
    notification_type ENUM('hackathon', 'event', 'od', 'system', 'general') NOT NULL DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) DEFAULT NULL COMMENT 'URL to redirect when clicked',
    
    -- Display metadata
    icon VARCHAR(255) DEFAULT NULL,
    
    -- Status
    is_read TINYINT(1) DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When push notification was sent',
    read_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (user_regno) REFERENCES student_register(regno) ON DELETE CASCADE,
    
    INDEX idx_user_regno (user_regno),
    INDEX idx_is_read (is_read),
    INDEX idx_notification_type (notification_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- SAMPLE DATA (Optional - for testing)
-- ============================================================================

-- Insert a sample hackathon (assuming admin with id=1 exists)
-- Uncomment below to insert test data:
/*
INSERT INTO hackathon_posts (title, description, organizer, start_date, end_date, registration_deadline, max_participants, theme, tags, created_by, status) 
VALUES (
    'AI Innovation Challenge 2026',
    'Build innovative AI solutions to solve real-world problems. Students can participate individually or in teams of up to 4 members. Winners will receive prizes and recognition.',
    'Department of Computer Science',
    '2026-03-15',
    '2026-03-17',
    '2026-03-10 23:59:59',
    100,
    'Artificial Intelligence',
    'AI,Machine Learning,Innovation,Technology',
    1,
    'upcoming'
);
*/

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================

-- Additional composite indexes for common queries
CREATE INDEX idx_hackathon_status_deadline ON hackathon_posts(status, registration_deadline);
CREATE INDEX idx_applications_hackathon_status ON hackathon_applications(hackathon_id, status);
CREATE INDEX idx_notifications_user_read ON notifications(user_regno, is_read, created_at DESC);

-- ============================================================================
-- TRIGGERS
-- ============================================================================

-- Trigger to update current_registrations count when application is created
DELIMITER $$
CREATE TRIGGER after_application_insert
AFTER INSERT ON hackathon_applications
FOR EACH ROW
BEGIN
    IF NEW.status = 'confirmed' THEN
        UPDATE hackathon_posts 
        SET current_registrations = current_registrations + 1 
        WHERE id = NEW.hackathon_id;
    END IF;
END$$

-- Trigger to update current_registrations count when application status changes
CREATE TRIGGER after_application_update
AFTER UPDATE ON hackathon_applications
FOR EACH ROW
BEGIN
    -- If status changed from confirmed to withdrawn
    IF OLD.status = 'confirmed' AND NEW.status = 'withdrawn' THEN
        UPDATE hackathon_posts 
        SET current_registrations = current_registrations - 1 
        WHERE id = NEW.hackathon_id;
    END IF;
    
    -- If status changed from withdrawn to confirmed
    IF OLD.status = 'withdrawn' AND NEW.status = 'confirmed' THEN
        UPDATE hackathon_posts 
        SET current_registrations = current_registrations + 1 
        WHERE id = NEW.hackathon_id;
    END IF;
END$$

-- Trigger to set read_at timestamp when notification is marked as read
CREATE TRIGGER after_notification_read
BEFORE UPDATE ON notifications
FOR EACH ROW
BEGIN
    IF OLD.is_read = 0 AND NEW.is_read = 1 THEN
        SET NEW.read_at = CURRENT_TIMESTAMP;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Check if all tables were created successfully
SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'event_management_system' 
AND TABLE_NAME IN ('hackathon_posts', 'hackathon_applications', 'hackathon_views', 'notifications')
ORDER BY TABLE_NAME;

-- Check all indexes
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'event_management_system'
AND TABLE_NAME IN ('hackathon_posts', 'hackathon_applications', 'hackathon_views', 'notifications')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Check all triggers
SHOW TRIGGERS WHERE `Table` IN ('hackathon_applications', 'notifications');
