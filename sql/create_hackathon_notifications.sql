-- ============================================================================
-- HACKATHON NOTIFICATIONS TABLE
-- Stores notifications for students when new hackathons are posted
-- ============================================================================
CREATE TABLE IF NOT EXISTS hackathon_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hackathon_id INT NOT NULL,
    student_regno VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (hackathon_id) REFERENCES hackathon_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (student_regno) REFERENCES student_register(regno) ON DELETE CASCADE,
    
    -- Prevent duplicate notifications for same hackathon/student
    UNIQUE KEY unique_notification (hackathon_id, student_regno),
    
    INDEX idx_student_regno (student_regno),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
