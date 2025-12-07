-- Create internship_submissions table
CREATE TABLE IF NOT EXISTS `internship_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `company_website` varchar(255) NOT NULL,
  `company_address` varchar(250) NOT NULL,
  `supervisor_name` varchar(100) NOT NULL,
  `supervisor_email` varchar(100) NOT NULL,
  `role_title` varchar(100) NOT NULL,
  `domain` varchar(50) NOT NULL,
  `mode` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `stipend_amount` decimal(10,2) DEFAULT 0.00,
  `internship_certificate` varchar(255) NOT NULL,
  `offer_letter` varchar(255) DEFAULT NULL,
  `brief_report` text NOT NULL,
  `submission_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
