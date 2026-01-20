# 🚀 cPanel Deployment Guide for Chores.to

## Step-by-Step Instructions

### ✅ **Step 1: Download the Deployment Package**

The file is located at: `/root/chores_deployment.zip` (106KB)

Download this file to your local computer.

---

### ✅ **Step 2: Backup Your Current Site** (IMPORTANT!)

1. Login to **NameCheap cPanel**
2. Go to **File Manager**
3. Navigate to your website directory (probably `public_html`)
4. Select all files → Right-click → **Compress** → Create `backup_before_update.zip`
5. Download this backup to your computer (just in case!)

---

### ✅ **Step 3: Upload New Files**

1. In **cPanel File Manager**, stay in your website root directory
2. Click **Upload** button (top right)
3. Select `chores_deployment.zip` from your computer
4. Wait for upload to complete
5. Close the upload dialog
6. Find `chores_deployment.zip` in the file list
7. Right-click the zip file → **Extract**
8. Select "Extract" again to confirm
9. After extraction, **delete** the zip file (right-click → Delete)

**Result:** All new/updated files are now on your server! ✅

---

### ✅ **Step 4: Create the .env File**

**IMPORTANT:** The `.env` file contains your database password and is NOT included in the zip for security.

1. In **File Manager**, click **+ File** (top left)
2. Name it exactly: `.env` (with the dot!)
3. Click **Create New File**
4. Find `.env` in the list, right-click → **Edit**
5. Paste this EXACT content:

```
# BookingPro Environment Configuration
# NEVER commit this file to version control!

# Database Configuration
DB_HOST=localhost
DB_NAME=ausshgzu_chores
DB_USER=ausshgzu_choreuser
DB_PASS=utp674"N&=-wcfT

# Site Configuration
SITE_NAME=Chores
SITE_URL=https://chores.to
ADMIN_EMAIL=craigbinn@gmail.com

# Security
SESSION_TIMEOUT=7200
PASSWORD_MIN_LENGTH=8
ENCRYPTION_KEY=b60861393886728681e72086c60b76faf8d87a52a4e188628dd9620754ba2528

# Email Configuration
SMTP_ENABLED=false
SMTP_HOST=smtp.yourdomain.com
SMTP_PORT=587
SMTP_USER=noreply@yourdomain.com
SMTP_PASS=your_smtp_password
SMTP_FROM_EMAIL=noreply@chores.to
SMTP_FROM_NAME=Chores

# Payment Integration (Optional - Stripe)
STRIPE_ENABLED=false
STRIPE_PUBLISHABLE_KEY=pk_test_xxxxx
STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=

# Features
ALLOW_REGISTRATION=true
REQUIRE_EMAIL_VERIFICATION=false
DEMO_MODE=false

# Environment (development, production)
APP_ENV=production
```

6. Click **Save Changes** (top right)
7. Close the editor
8. Right-click `.env` → **Permissions**
9. Set to: **600** (Owner: Read + Write, Group: nothing, Public: nothing)
10. Click **Change Permissions**

---

### ✅ **Step 5: Create Required Directories**

Still in **File Manager**:

1. Click **+ Folder** → Create folder named: `logs`
2. Click **+ Folder** → Create folder named: `backups`
3. Right-click `logs` folder → **Permissions** → Set to **755**
4. Right-click `backups` folder → **Permissions** → Set to **755**

---

### ✅ **Step 6: Run Database Migrations**

Now we need to add new tables to your database.

1. In cPanel, go back to home
2. Open **phpMyAdmin**
3. Select your database: `ausshgzu_chores` (left sidebar)
4. Click **SQL** tab at the top

#### Migration 1: Email Verification

Copy and paste this SQL, then click **Go**:

```sql
-- Add email verification columns
ALTER TABLE `businesses`
ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email`,
ADD COLUMN `email_verification_token` VARCHAR(64) NULL DEFAULT NULL AFTER `email_verified`,
ADD COLUMN `email_verification_sent_at` DATETIME NULL DEFAULT NULL AFTER `email_verification_token`,
ADD INDEX `idx_verification_token` (`email_verification_token`);
```

**Expected result:** "3 columns added" message ✅

#### Migration 2: Stripe Payment Tables (Part 1)

Copy and paste this SQL, then click **Go**:

```sql
-- Payment Methods Table
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `business_id` int(11) NOT NULL,
    `client_id` int(11) NOT NULL,
    `stripe_payment_method_id` VARCHAR(255) NOT NULL,
    `stripe_customer_id` VARCHAR(255) DEFAULT NULL,
    `type` ENUM('card', 'bank_account', 'other') DEFAULT 'card',
    `card_brand` VARCHAR(50) DEFAULT NULL,
    `card_last4` VARCHAR(4) DEFAULT NULL,
    `card_exp_month` TINYINT DEFAULT NULL,
    `card_exp_year` SMALLINT DEFAULT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_business_id` (`business_id`),
    KEY `idx_client_id` (`client_id`),
    KEY `idx_stripe_customer_id` (`stripe_customer_id`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Expected result:** "Table created" message ✅

#### Migration 3: Stripe Payment Tables (Part 2)

Copy and paste this SQL, then click **Go**:

```sql
-- Payments Table
CREATE TABLE IF NOT EXISTS `payments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `business_id` int(11) NOT NULL,
    `appointment_id` int(11) DEFAULT NULL,
    `client_id` int(11) NOT NULL,
    `stripe_payment_intent_id` VARCHAR(255) DEFAULT NULL,
    `stripe_charge_id` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'USD',
    `status` ENUM('pending', 'processing', 'succeeded', 'failed', 'refunded', 'canceled') DEFAULT 'pending',
    `payment_method` ENUM('stripe', 'cash', 'card', 'other') DEFAULT 'stripe',
    `payment_method_id` int(11) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `metadata` JSON DEFAULT NULL,
    `failure_reason` TEXT DEFAULT NULL,
    `refund_amount` DECIMAL(10,2) DEFAULT 0.00,
    `refund_reason` TEXT DEFAULT NULL,
    `refunded_at` DATETIME DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_business_id` (`business_id`),
    KEY `idx_appointment_id` (`appointment_id`),
    KEY `idx_client_id` (`client_id`),
    KEY `idx_stripe_payment_intent_id` (`stripe_payment_intent_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Expected result:** "Table created" message ✅

#### Migration 4: Business Stripe Accounts

Copy and paste this SQL, then click **Go**:

```sql
-- Business Stripe Accounts Table
CREATE TABLE IF NOT EXISTS `business_stripe_accounts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `business_id` int(11) NOT NULL,
    `stripe_account_id` VARCHAR(255) NOT NULL,
    `stripe_account_type` ENUM('standard', 'express', 'custom') DEFAULT 'express',
    `charges_enabled` TINYINT(1) DEFAULT 0,
    `payouts_enabled` TINYINT(1) DEFAULT 0,
    `details_submitted` TINYINT(1) DEFAULT 0,
    `onboarding_completed` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `business_id` (`business_id`),
    KEY `idx_stripe_account_id` (`stripe_account_id`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Expected result:** "Table created" message ✅

#### Migration 5: Add Columns to Existing Tables

Copy and paste this SQL, then click **Go**:

```sql
-- Add Stripe customer ID to clients table
ALTER TABLE `clients`
ADD COLUMN `stripe_customer_id` VARCHAR(255) DEFAULT NULL AFTER `referral_source`,
ADD INDEX `idx_stripe_customer_id` (`stripe_customer_id`);
```

**Expected result:** "1 column added" message ✅

#### Migration 6: Add Stripe Settings to Businesses

Copy and paste this SQL, then click **Go**:

```sql
-- Add payment settings to businesses table
ALTER TABLE `businesses`
ADD COLUMN `stripe_publishable_key` VARCHAR(255) DEFAULT NULL AFTER `currency`,
ADD COLUMN `stripe_secret_key` VARCHAR(255) DEFAULT NULL AFTER `stripe_publishable_key`,
ADD COLUMN `require_payment_upfront` TINYINT(1) DEFAULT 0 AFTER `accept_online_payments`,
ADD COLUMN `deposit_percentage` DECIMAL(5,2) DEFAULT 0.00 AFTER `require_payment_upfront`;
```

**Expected result:** "4 columns added" message ✅

**Note:** If you get an error about `accept_online_payments` not existing, that's OK - it means that column already exists in your database.

---

### ✅ **Step 7: Test Your Site**

1. Open your website: **https://chores.to**
2. Try logging in
3. Check if the site loads without errors

**Check for errors:**
- In cPanel → **File Manager** → Open `logs/error.log`
- If the file exists, check for any critical errors

---

### ✅ **Step 8: Install Stripe SDK** (Required for Payments)

To enable Stripe payments, you need to install the Stripe PHP SDK.

**Option A: Via cPanel Terminal (Recommended)**

1. In cPanel, search for "Terminal"
2. Open **Terminal**
3. Run these commands:

```bash
cd public_html
composer require stripe/stripe-php
```

**Option B: If No Terminal Access**

Download Stripe SDK manually:
1. Go to: https://github.com/stripe/stripe-php/releases
2. Download the latest release ZIP
3. Extract it
4. Upload the `stripe-php` folder to your server's `includes/` directory via File Manager

Then edit `includes/StripePayment.php` and add at the top:
```php
require_once __DIR__ . '/stripe-php/init.php';
```

---

### ✅ **Step 9: Configure Stripe** (Optional - When Ready)

When you're ready to accept payments:

1. Sign up at: https://dashboard.stripe.com
2. Get your API keys from: https://dashboard.stripe.com/apikeys
3. Edit `.env` file on your server (via cPanel File Manager)
4. Update these lines:

```env
STRIPE_ENABLED=true
STRIPE_PUBLISHABLE_KEY=pk_live_YOUR_ACTUAL_KEY
STRIPE_SECRET_KEY=sk_live_YOUR_ACTUAL_KEY
```

5. Set up webhook in Stripe Dashboard:
   - URL: `https://chores.to/stripe-webhook.php`
   - Events: `payment_intent.succeeded`, `payment_intent.payment_failed`
   - Copy webhook secret and add to `.env`

---

### ✅ **Step 10: Enable Email Verification** (Optional)

To require users to verify their email before using the platform:

1. Edit `.env` file on your server
2. Change this line:

```env
REQUIRE_EMAIL_VERIFICATION=true
```

3. Configure SMTP settings in `.env` for reliable email delivery (optional but recommended)

---

## 🎉 **You're Done!**

Your platform now has:
- ✅ All critical security fixes
- ✅ Email verification system (optional to enable)
- ✅ Stripe payment processing (ready to enable)
- ✅ Automated backups (ready to configure)
- ✅ CSRF protection on all forms
- ✅ Session security
- ✅ Input validation

---

## 🆘 **Troubleshooting**

**Issue: "Call to undefined function env()"**
→ Make sure you uploaded all files from the zip
→ Check that `includes/env-loader.php` exists

**Issue: "Database connection failed"**
→ Check `.env` file has correct database credentials
→ Make sure `.env` permissions are 600

**Issue: "Stripe SDK not found"**
→ Install Stripe SDK (Step 8)

**Issue: Site shows errors**
→ Check `logs/error.log` for details
→ Make sure all database migrations ran successfully

**Issue: Can't login**
→ Clear browser cookies
→ Check error logs

---

## 📞 **Need Help?**

Check these files for detailed information:
- `SECURITY_AND_SETUP_GUIDE.md` - Comprehensive setup guide
- `IMPLEMENTATION_SUMMARY.md` - Technical details
- `logs/error.log` - Error messages

---

**You're all set! Your zero-commission booking platform is ready! 🚀**
