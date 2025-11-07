-- Advanced Optimization Database Indexes
-- Run this SQL script to optimize query performance

-- ===================================
-- INDEXES FOR STUDENT_REGISTER TABLE
-- ===================================

-- Index for username lookups (login functionality)
CREATE INDEX IF NOT EXISTS idx_student_username ON student_register(username);

-- Index for regno lookups (most common query)
CREATE INDEX IF NOT EXISTS idx_student_regno ON student_register(regno);

-- Composite index for username and regno (login verification)
CREATE INDEX IF NOT EXISTS idx_student_login ON student_register(username, regno);

-- ===================================
-- INDEXES FOR STUDENT_EVENT_REGISTER TABLE
-- ===================================

-- Primary index for regno (most frequent query)
CREATE INDEX IF NOT EXISTS idx_event_regno ON student_event_register(regno);

-- Index for event_type (dashboard breakdown queries)
CREATE INDEX IF NOT EXISTS idx_event_type ON student_event_register(event_type);

-- Index for attended_date (recent activities)
CREATE INDEX IF NOT EXISTS idx_event_date ON student_event_register(attended_date);

-- Index for prize queries (events won statistics)
CREATE INDEX IF NOT EXISTS idx_event_prize ON student_event_register(prize);

-- Composite index for regno and prize (optimized statistics)
CREATE INDEX IF NOT EXISTS idx_regno_prize ON student_event_register(regno, prize);

-- Composite index for regno and event_type (type breakdown)
CREATE INDEX IF NOT EXISTS idx_regno_event_type ON student_event_register(regno, event_type);

-- Composite index for regno and attended_date (recent activities)
CREATE INDEX IF NOT EXISTS idx_regno_date ON student_event_register(regno, attended_date);

-- ===================================
-- INDEXES FOR OD_REQUESTS TABLE
-- ===================================

-- Primary index for student_regno
CREATE INDEX IF NOT EXISTS idx_od_student_regno ON od_requests(student_regno);

-- Index for status (pending, approved, rejected queries)
CREATE INDEX IF NOT EXISTS idx_od_status ON od_requests(status);

-- Index for request_date (recent requests)
CREATE INDEX IF NOT EXISTS idx_od_request_date ON od_requests(request_date);

-- Index for event_date (upcoming events)
CREATE INDEX IF NOT EXISTS idx_od_event_date ON od_requests(event_date);

-- Composite index for student_regno and status (dashboard statistics)
CREATE INDEX IF NOT EXISTS idx_od_student_status ON od_requests(student_regno, status);

-- Composite index for student_regno and request_date (recent requests)
CREATE INDEX IF NOT EXISTS idx_od_student_request_date ON od_requests(student_regno, request_date);

-- ===================================
-- INDEXES FOR TEACHER_REGISTER TABLE
-- ===================================

-- Index for username lookups
CREATE INDEX IF NOT EXISTS idx_teacher_username ON teacher_register(username);

-- Index for teacher_id
CREATE INDEX IF NOT EXISTS idx_teacher_id ON teacher_register(teacher_id);

-- ===================================
-- INDEXES FOR ADMIN TABLE
-- ===================================

-- Index for username lookups
CREATE INDEX IF NOT EXISTS idx_admin_username ON admin(username);

-- ===================================
-- PERFORMANCE OPTIMIZATION SETTINGS
-- ===================================

-- Enable query cache (if not already enabled)
SET GLOBAL query_cache_type = ON;
SET GLOBAL query_cache_size = 67108864; -- 64MB

-- Optimize key buffer size
SET GLOBAL key_buffer_size = 134217728; -- 128MB

-- Optimize InnoDB settings
SET GLOBAL innodb_buffer_pool_size = 268435456; -- 256MB

-- ===================================
-- ANALYZE TABLES FOR STATISTICS
-- ===================================

ANALYZE TABLE student_register;
ANALYZE TABLE student_event_register;
ANALYZE TABLE od_requests;
ANALYZE TABLE teacher_register;
ANALYZE TABLE admin;

-- ===================================
-- CHECK INDEX USAGE (Run after implementation)
-- ===================================

-- Uncomment these queries to check if indexes are being used:
-- EXPLAIN SELECT * FROM student_event_register WHERE regno = 'your_regno';
-- EXPLAIN SELECT COUNT(*) FROM student_event_register WHERE regno = 'your_regno' AND prize IN ('First', 'Second', 'Third');
-- EXPLAIN SELECT * FROM od_requests WHERE student_regno = 'your_regno' AND status = 'pending';

-- ===================================
-- MAINTENANCE QUERIES
-- ===================================

-- Run these periodically to maintain performance:
-- OPTIMIZE TABLE student_register;
-- OPTIMIZE TABLE student_event_register;
-- OPTIMIZE TABLE od_requests;
-- OPTIMIZE TABLE teacher_register;
-- OPTIMIZE TABLE admin;

-- Check index cardinality
-- SHOW INDEX FROM student_event_register;
-- SHOW INDEX FROM od_requests;

-- Monitor slow queries
-- SET GLOBAL slow_query_log = 'ON';
-- SET GLOBAL long_query_time = 0.1; -- Log queries taking more than 100ms