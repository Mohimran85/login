-- =============================================================
-- ROLLBACK SCRIPT — Deployment Cleanup (2026-03-11)
-- =============================================================
-- Run this ONLY if you need to restore functionality that was
-- removed during the pre-deployment cleanup pass.
-- =============================================================

-- -----------------------------------------------------------
-- ROLLBACK 1: Recreate teacher_signatures table
-- (Dropped during deployment cleanup — digital signature
--  feature was not needed for production launch)
-- -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS `teacher_signatures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `signature_type` enum('upload','drawn','text') NOT NULL DEFAULT 'upload',
  `signature_data` longtext NOT NULL COMMENT 'Base64 encoded signature or file path',
  `signature_hash` varchar(256) NOT NULL COMMENT 'Hash for verification',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_active_signature` (`teacher_id`,`is_active`),
  CONSTRAINT `teacher_signatures_ibfk_1` FOREIGN KEY (`teacher_id`)
    REFERENCES `teacher_register` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Also restore optional columns on od_requests (only if they were present before):
-- ALTER TABLE od_requests
--   ADD COLUMN IF NOT EXISTS signature_id INT NULL,
--   ADD COLUMN IF NOT EXISTS digital_signature_hash VARCHAR(256) NULL,
--   ADD COLUMN IF NOT EXISTS signature_timestamp TIMESTAMP NULL;

-- Verify:
-- SHOW TABLES LIKE 'teacher_signatures';
-- SHOW COLUMNS FROM od_requests WHERE Field LIKE '%signature%';
