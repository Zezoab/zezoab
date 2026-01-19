-- Staff Schedule Enhancement
-- Adds flexible scheduling patterns for staff members

-- Add schedule pattern to staff table
ALTER TABLE `staff` ADD COLUMN `schedule_pattern` enum('weekly','biweekly','custom') DEFAULT 'weekly' COMMENT 'Weekly schedule pattern';
ALTER TABLE `staff` ADD COLUMN `biweekly_week` int(1) DEFAULT 1 COMMENT 'Which week in biweekly pattern (1 or 2)';

-- Staff availability exceptions (time off, special hours, blocked dates)
CREATE TABLE IF NOT EXISTS `staff_availability_exceptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `exception_date` date NOT NULL,
  `exception_type` enum('unavailable','custom_hours') DEFAULT 'unavailable',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `exception_date` (`exception_date`),
  FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Staff recurring schedules (for complex patterns)
CREATE TABLE IF NOT EXISTS `staff_recurring_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `schedule_name` varchar(100) NOT NULL,
  `recurrence_type` enum('daily','weekly','biweekly','monthly','custom') DEFAULT 'weekly',
  `day_of_week` int(1) DEFAULT NULL COMMENT '0=Sunday, 6=Saturday',
  `week_of_month` int(1) DEFAULT NULL COMMENT '1-4 for week of month, or 0 for all',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `start_date` date DEFAULT NULL COMMENT 'When this schedule starts',
  `end_date` date DEFAULT NULL COMMENT 'When this schedule ends (NULL = indefinite)',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
