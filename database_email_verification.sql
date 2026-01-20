-- Email Verification System
-- Add email verification columns to businesses table

ALTER TABLE `businesses`
ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email`,
ADD COLUMN `email_verification_token` VARCHAR(64) NULL DEFAULT NULL AFTER `email_verified`,
ADD COLUMN `email_verification_sent_at` DATETIME NULL DEFAULT NULL AFTER `email_verification_token`;

-- Add index for faster lookups
ALTER TABLE `businesses`
ADD INDEX `idx_verification_token` (`email_verification_token`);

-- Note: Existing businesses will have email_verified = 0
-- You may want to manually set email_verified = 1 for existing accounts:
-- UPDATE businesses SET email_verified = 1 WHERE created_at < '2024-01-01';
