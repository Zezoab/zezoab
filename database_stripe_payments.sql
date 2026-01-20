-- Stripe Payment Processing Tables
-- Adds support for online payments via Stripe

-- Payment Methods Table (store customer payment methods)
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT UNSIGNED NOT NULL,
    `client_id` INT UNSIGNED NOT NULL,
    `stripe_payment_method_id` VARCHAR(255) NOT NULL,
    `stripe_customer_id` VARCHAR(255) NULL,
    `type` ENUM('card', 'bank_account', 'other') DEFAULT 'card',
    `card_brand` VARCHAR(50) NULL,
    `card_last4` VARCHAR(4) NULL,
    `card_exp_month` TINYINT NULL,
    `card_exp_year` SMALLINT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_business_id` (`business_id`),
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_stripe_customer_id` (`stripe_customer_id`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments Table (track all payment transactions)
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED NULL,
    `client_id` INT UNSIGNED NOT NULL,
    `stripe_payment_intent_id` VARCHAR(255) NULL,
    `stripe_charge_id` VARCHAR(255) NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'USD',
    `status` ENUM('pending', 'processing', 'succeeded', 'failed', 'refunded', 'canceled') DEFAULT 'pending',
    `payment_method` ENUM('stripe', 'cash', 'card', 'other') DEFAULT 'stripe',
    `payment_method_id` INT UNSIGNED NULL,
    `description` TEXT NULL,
    `metadata` JSON NULL,
    `failure_reason` TEXT NULL,
    `refund_amount` DECIMAL(10,2) DEFAULT 0.00,
    `refund_reason` TEXT NULL,
    `refunded_at` DATETIME NULL,
    `paid_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_business_id` (`business_id`),
    INDEX `idx_appointment_id` (`appointment_id`),
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_stripe_payment_intent_id` (`stripe_payment_intent_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Business Stripe Accounts Table (for Stripe Connect - if needed later)
CREATE TABLE IF NOT EXISTS `business_stripe_accounts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT UNSIGNED NOT NULL UNIQUE,
    `stripe_account_id` VARCHAR(255) NOT NULL,
    `stripe_account_type` ENUM('standard', 'express', 'custom') DEFAULT 'express',
    `charges_enabled` TINYINT(1) DEFAULT 0,
    `payouts_enabled` TINYINT(1) DEFAULT 0,
    `details_submitted` TINYINT(1) DEFAULT 0,
    `onboarding_completed` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_business_id` (`business_id`),
    INDEX `idx_stripe_account_id` (`stripe_account_id`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add Stripe customer ID to clients table
ALTER TABLE `clients`
ADD COLUMN `stripe_customer_id` VARCHAR(255) NULL AFTER `referral_source`,
ADD INDEX `idx_stripe_customer_id` (`stripe_customer_id`);

-- Add payment settings to businesses table
ALTER TABLE `businesses`
ADD COLUMN `stripe_publishable_key` VARCHAR(255) NULL AFTER `currency`,
ADD COLUMN `stripe_secret_key` VARCHAR(255) NULL AFTER `stripe_publishable_key`,
ADD COLUMN `accept_online_payments` TINYINT(1) DEFAULT 0 AFTER `stripe_secret_key`,
ADD COLUMN `require_payment_upfront` TINYINT(1) DEFAULT 0 AFTER `accept_online_payments`,
ADD COLUMN `deposit_percentage` DECIMAL(5,2) DEFAULT 0.00 AFTER `require_payment_upfront`;
