-- Advanced Features Database Schema
-- Features that improve upon Square's limitations

-- ============================================
-- 1. RECURRING APPOINTMENTS (Better than Square)
-- ============================================
CREATE TABLE IF NOT EXISTS `recurring_appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `recurrence_pattern` enum('daily','weekly','biweekly','monthly','custom') DEFAULT 'weekly',
  `recurrence_days` varchar(50) DEFAULT NULL COMMENT 'Comma-separated days (0=Sun, 1=Mon, etc.)',
  `preferred_time` time NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `business_id` (`business_id`),
  KEY `client_id` (`client_id`),
  KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. WAITLIST MANAGEMENT (Better than Square)
-- ============================================
CREATE TABLE IF NOT EXISTS `waitlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL COMMENT 'Preferred staff, NULL for any',
  `preferred_date` date DEFAULT NULL COMMENT 'Preferred date, NULL for any',
  `preferred_time_start` time DEFAULT NULL,
  `preferred_time_end` time DEFAULT NULL,
  `priority` int(11) DEFAULT 5 COMMENT '1=highest, 10=lowest',
  `status` enum('waiting','notified','booked','expired','cancelled') DEFAULT 'waiting',
  `notes` text,
  `notified_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `business_id` (`business_id`),
  KEY `status` (`status`),
  KEY `priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. PACKAGES & MEMBERSHIPS (Better than Square)
-- ============================================
CREATE TABLE IF NOT EXISTS `packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `package_type` enum('session_pack','membership','unlimited') DEFAULT 'session_pack',
  `price` decimal(10,2) NOT NULL,
  `sessions_included` int(11) DEFAULT NULL COMMENT 'Number of sessions, NULL for unlimited',
  `validity_days` int(11) DEFAULT 365 COMMENT 'Days package is valid',
  `services_included` text COMMENT 'Comma-separated service IDs, NULL for all',
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `business_id` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `client_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `sessions_remaining` int(11) NOT NULL,
  `purchase_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('active','expired','used') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. LOYALTY & REWARDS SYSTEM (Better than Square)
-- ============================================
CREATE TABLE IF NOT EXISTS `loyalty_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `points` int(11) DEFAULT 0,
  `lifetime_points` int(11) DEFAULT 0,
  `tier` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_client_business` (`business_id`,`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `loyalty_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `points_change` int(11) NOT NULL COMMENT 'Positive for earning, negative for spending',
  `transaction_type` enum('earned','redeemed','bonus','expired','adjusted') DEFAULT 'earned',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `loyalty_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `points_required` int(11) NOT NULL,
  `reward_type` enum('discount_percentage','discount_fixed','free_service','gift') DEFAULT 'discount_percentage',
  `reward_value` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `business_id` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. MULTI-SERVICE BOOKINGS (Square limitation fix)
-- ============================================
CREATE TABLE IF NOT EXISTS `appointment_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `duration` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `order_index` int(11) DEFAULT 0 COMMENT 'Order of service in multi-service booking',
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. GROUP CLASSES/APPOINTMENTS (Square limitation fix)
-- ============================================
CREATE TABLE IF NOT EXISTS `group_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `class_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_participants` int(11) DEFAULT 10,
  `current_participants` int(11) DEFAULT 0,
  `price_per_person` decimal(10,2) NOT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `business_id` (`business_id`),
  KEY `class_date` (`class_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `class_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `attended` tinyint(1) DEFAULT 0,
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_client` (`class_id`,`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. CUSTOMIZABLE MESSAGE TEMPLATES (Square can't edit messages)
-- ============================================
CREATE TABLE IF NOT EXISTS `message_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `template_type` enum('booking_confirmation','reminder_24h','reminder_2h','cancellation','rescheduled','follow_up','waitlist_notify') NOT NULL,
  `channel` enum('email','sms','both') DEFAULT 'email',
  `subject` varchar(255) DEFAULT NULL COMMENT 'For email',
  `message_body` text NOT NULL,
  `variables` text COMMENT 'Available variables: {client_name}, {date}, {time}, {service}, {staff}, etc.',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `business_id` (`business_id`),
  KEY `template_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. GIFT CERTIFICATES (Better than Square)
-- ============================================
CREATE TABLE IF NOT EXISTS `gift_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `initial_value` decimal(10,2) NOT NULL,
  `remaining_value` decimal(10,2) NOT NULL,
  `purchaser_name` varchar(255) DEFAULT NULL,
  `purchaser_email` varchar(255) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `message` text,
  `purchase_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','redeemed','expired','cancelled') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`business_id`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gift_certificate_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `certificate_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `amount_used` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `certificate_id` (`certificate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. DYNAMIC PRICING (Advanced feature)
-- ============================================
CREATE TABLE IF NOT EXISTS `pricing_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL COMMENT 'NULL for all services',
  `rule_type` enum('peak_hours','off_peak','day_of_week','seasonal','last_minute') DEFAULT 'peak_hours',
  `time_start` time DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `days_of_week` varchar(50) DEFAULT NULL COMMENT 'Comma-separated: 0-6',
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `adjustment_type` enum('percentage','fixed') DEFAULT 'percentage',
  `adjustment_value` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `business_id` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Insert default message templates
-- ============================================
INSERT INTO `message_templates` (`business_id`, `template_type`, `channel`, `subject`, `message_body`, `variables`) VALUES
(1, 'booking_confirmation', 'email', 'Booking Confirmed - {service} on {date}',
'Hi {client_name},\n\nYour appointment has been confirmed!\n\n📅 Date: {date}\n⏰ Time: {time}\n💼 Service: {service}\n👤 With: {staff}\n💵 Price: {price}\n\nSee you soon!\n\n{business_name}',
'{client_name}, {date}, {time}, {service}, {staff}, {price}, {business_name}'),

(1, 'reminder_24h', 'both', 'Reminder: Appointment Tomorrow',
'Hi {client_name},\n\nJust a reminder about your appointment tomorrow:\n\n⏰ {time} - {service} with {staff}\n\nSee you soon!',
'{client_name}, {date}, {time}, {service}, {staff}');

-- ============================================
-- Loyalty Points Configuration
-- ============================================
-- Businesses can configure: earn 1 point per $1 spent
-- Tiers: Bronze (0-99), Silver (100-499), Gold (500-999), Platinum (1000+)
