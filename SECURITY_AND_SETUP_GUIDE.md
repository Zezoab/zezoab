# BookingPro - Security & Setup Guide

## 🎉 What's New - Major Security & Feature Updates

This guide covers all the critical security fixes, new features, and setup instructions for your payment platform.

---

## ✅ COMPLETED: Critical Security Fixes

### 1. **Environment Configuration (.env)**
**Problem:** Database credentials were hardcoded in `config.php` and exposed in Git.

**Solution:**
- Created `.env` file for sensitive credentials
- Added `.gitignore` to prevent committing secrets
- Updated `config.php` to read from environment variables

**Action Required:**
```bash
# 1. Copy .env.example to .env
cp .env.example .env

# 2. Edit .env with your actual credentials
nano .env

# 3. Set APP_ENV to 'production' for live sites
APP_ENV=production
```

### 2. **Error Handling & Logging**
**Problem:** Errors displayed sensitive information to users in production.

**Solution:**
- Disabled `display_errors` in production
- Enabled error logging to `/logs/error.log`
- Controlled by `APP_ENV` variable

**Monitor Errors:**
```bash
tail -f logs/error.log
```

### 3. **SQL Injection Prevention**
**Problem:** Table names in `Database.php` weren't validated.

**Solution:**
- Added whitelist of valid table names
- All insert/update/delete operations now validate table names
- Invalid table names are logged and blocked

**Location:** `includes/Database.php:12-20`

### 4. **Session Security**
**Problem:** Session cookies lacked security flags, vulnerable to hijacking.

**Solution:**
- Added `httponly`, `secure`, and `samesite` cookie flags
- Implemented session fingerprinting (User-Agent + IP)
- Session regeneration on login to prevent fixation
- 2-hour timeout with automatic extension

**Location:** `includes/Auth.php:10-70`

### 5. **CSRF Protection**
**Problem:** Forms vulnerable to Cross-Site Request Forgery attacks.

**Solution:**
- Created `CSRF` class with token generation/validation
- Added CSRF tokens to all forms (login, register, booking)
- Tokens expire after 1 hour

**Usage:**
```php
// In form HTML
<?php echo csrf_field(); ?>

// In form processing
if (!csrf_validate()) {
    die('CSRF validation failed');
}
```

**Location:** `includes/CSRF.php`, `includes/functions.php:13-29`

### 6. **Input Validation**
**Problem:** Weak validation on booking form allowed malformed data.

**Solution:**
- Added comprehensive validation functions:
  - `isValidEmail()` - Email format validation
  - `isValidPhone()` - Phone number validation (10-15 digits)
  - `isValidFutureDate()` - Date validation (future dates only)
  - `isValidTime()` - Time format validation
  - `isValidName()` - Name format (2-50 chars, letters/spaces/hyphens)
  - `isValidId()` - Positive integer validation

**Location:** `includes/functions.php:48-95`

---

## 🆕 NEW FEATURE: Email Verification

### Database Setup
```bash
# Run the email verification migration
mysql -u username -p database_name < database_email_verification.sql
```

### How It Works
1. User registers → Email sent with verification link
2. User clicks link → Email verified, account activated
3. Unverified users cannot login (if `REQUIRE_EMAIL_VERIFICATION=true`)

### Configuration
```env
# In .env file
REQUIRE_EMAIL_VERIFICATION=true
```

### Files Created
- `database_email_verification.sql` - Database migration
- `includes/Auth.php` - Verification methods (lines 286-426)
- `verify-email.php` - Email verification page
- `resend-verification.php` - Resend verification email
- Updated `register.php` - Sends verification emails
- Updated `login.php` - Blocks unverified users

### Testing
1. Register a new account
2. Check email for verification link
3. Click link to verify
4. Login successfully

---

## 💳 NEW FEATURE: Stripe Payment Processing

### Prerequisites
```bash
# Install Stripe PHP SDK
composer require stripe/stripe-php
```

### Database Setup
```bash
# Run the Stripe payments migration
mysql -u username -p database_name < database_stripe_payments.sql
```

### Configuration
```env
# In .env file
STRIPE_ENABLED=true
STRIPE_PUBLISHABLE_KEY=pk_test_xxxxx
STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

### Get Stripe Keys
1. Sign up at https://stripe.com
2. Go to **Developers → API Keys**
3. Copy **Publishable key** and **Secret key**
4. For webhooks:
   - Go to **Developers → Webhooks**
   - Add endpoint: `https://yourdomain.com/stripe-webhook.php`
   - Select events: `payment_intent.succeeded`, `payment_intent.payment_failed`
   - Copy **Signing secret**

### Files Created
- `database_stripe_payments.sql` - Payment tables migration
- `includes/StripePayment.php` - Complete payment processing class
- `payment-checkout.php` - Customer payment page
- `stripe-webhook.php` - Webhook handler for Stripe events

### Features Implemented
✅ Create payment intents
✅ Process credit card payments
✅ Automatic customer creation
✅ Payment status tracking
✅ Full refund support
✅ Webhook processing
✅ Payment history

### Usage Example
```php
// Create payment for an appointment
$stripe = new StripePayment($businessId);
$result = $stripe->createPaymentIntent($appointmentId, 50.00, 'USD');

if ($result['success']) {
    echo "Payment URL: payment-checkout.php?appointment=$appointmentId&token=$token";
}

// Process refund
$refundResult = $stripe->refundPayment($paymentId, 50.00, 'Customer request');
```

### Testing with Stripe Test Cards
```
Success: 4242 4242 4242 4242
Decline: 4000 0000 0000 0002
```

---

## 📦 NEW FEATURE: Automated Backups

### Setup
```bash
# Make backup script executable
chmod +x includes/backup.php

# Test backup manually
php includes/backup.php
```

### Automated Daily Backups
```bash
# Edit crontab
crontab -e

# Add this line for daily 2 AM backups
0 2 * * * cd /path/to/your/app && php includes/backup.php >> /path/to/your/app/logs/backup.log 2>&1
```

### Features
- Automatic MySQL dumps
- Gzip compression (saves 90% space)
- Keeps last 30 backups (configurable)
- Email notifications on completion
- Backup files stored in `/backups/` directory

### Restore from Backup
```bash
# Decompress backup
gunzip backups/backup_2024-01-15_02-00-00.sql.gz

# Restore to database
mysql -u username -p database_name < backups/backup_2024-01-15_02-00-00.sql
```

---

## 🔒 Security Checklist

### Before Going Live

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Enable HTTPS in `.htaccess` (uncomment lines 14-15)
- [ ] Set strong `ENCRYPTION_KEY` in `.env` (32+ random characters)
- [ ] Change default `ADMIN_EMAIL` in `.env`
- [ ] Set `REQUIRE_EMAIL_VERIFICATION=true` in `.env`
- [ ] Configure SMTP for email sending (optional but recommended)
- [ ] Set up SSL certificate (Let's Encrypt is free)
- [ ] Enable automated backups (cron job)
- [ ] Test all forms with CSRF protection
- [ ] Verify `.env` is in `.gitignore`
- [ ] Review file permissions:
  ```bash
  chmod 600 .env
  chmod 755 backups/
  chmod 755 logs/
  chmod 755 uploads/
  ```

### Production `.htaccess` Updates
```apache
# Uncomment these lines in .htaccess for HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 📧 Email Configuration

### Option 1: PHP mail() Function (Default)
```env
SMTP_ENABLED=false
```
Works on most shared hosting, but emails may go to spam.

### Option 2: SMTP (Recommended)
```env
SMTP_ENABLED=true
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_FROM_EMAIL=noreply@yourdomain.com
```

**Gmail Setup:**
1. Enable 2-Factor Authentication
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use App Password in `SMTP_PASS`

---

## 🚀 Deployment Checklist

### First Time Setup
1. Upload all files to web server
2. Create MySQL database
3. Import database schemas:
   ```bash
   mysql -u user -p dbname < database.sql
   mysql -u user -p dbname < database_advanced_features.sql
   mysql -u user -p dbname < database_email_verification.sql
   mysql -u user -p dbname < database_stripe_payments.sql
   ```
4. Copy `.env.example` to `.env`
5. Update `.env` with your credentials
6. Set file permissions (see above)
7. Test in browser: `https://yourdomain.com`

### For Existing Installations
1. Backup your current database
2. Run new migration files:
   ```bash
   mysql -u user -p dbname < database_email_verification.sql
   mysql -u user -p dbname < database_stripe_payments.sql
   ```
3. Copy `.env.example` to `.env`
4. Transfer settings from old `config.php` to `.env`
5. Upload new files (except `.env`)
6. Test all functionality

---

## 🧪 Testing Your Security Improvements

### 1. CSRF Protection Test
1. Open developer tools (F12)
2. Try submitting a form without CSRF token
3. Should get error: "Security validation failed"

### 2. Session Security Test
1. Login to account
2. Copy session cookie value
3. Try using it from different browser/IP
4. Should be logged out (session hijacking prevention)

### 3. SQL Injection Test
Try accessing: `https://yourdomain.com/book.php?slug=test' OR '1'='1`
Should return "Business not found" (not SQL error)

### 4. Email Verification Test
1. Register new account
2. Check email inbox
3. Click verification link
4. Should see success message
5. Try logging in (should work)

### 5. Stripe Payment Test
1. Create an appointment
2. Go to payment URL
3. Use test card: 4242 4242 4242 4242
4. Verify payment appears in Stripe Dashboard

---

## 📊 Monitoring & Maintenance

### Check Error Logs Daily
```bash
tail -n 100 logs/error.log
```

### Monitor Backup Success
```bash
ls -lh backups/ | tail -5
```

### Check Payment Status
```sql
SELECT status, COUNT(*) as count, SUM(amount) as total
FROM payments
GROUP BY status;
```

### Security Alerts to Watch For
```bash
# Check for suspicious activity
grep "CSRF validation failed" logs/error.log
grep "Session hijacking" logs/error.log
grep "Invalid table name" logs/error.log
```

---

## 🆘 Troubleshooting

### Email Not Sending
**Problem:** Verification emails not received
**Solution:**
1. Check spam folder
2. Verify SMTP settings in `.env`
3. Check logs: `tail -f logs/error.log`
4. Test with online SMTP testers

### Stripe Payments Not Working
**Problem:** "Stripe is not enabled" error
**Solution:**
1. Run: `composer require stripe/stripe-php`
2. Verify `STRIPE_ENABLED=true` in `.env`
3. Check API keys are correct
4. Look for errors in `logs/error.log`

### Backup Script Fails
**Problem:** Backup not created
**Solution:**
1. Check `mysqldump` is installed: `which mysqldump`
2. Verify database credentials in `.env`
3. Ensure `/backups/` directory is writable: `chmod 755 backups/`
4. Run manually to see errors: `php includes/backup.php`

### Session Issues After Update
**Problem:** Can't login after security updates
**Solution:**
1. Clear browser cookies
2. Clear PHP sessions: `rm -rf /tmp/sess_*` (on server)
3. Regenerate session: Login again

---

## 📞 Support

If you encounter issues:

1. Check logs first: `logs/error.log`
2. Review this guide
3. Search for error message in logs
4. Create GitHub issue with:
   - Error message
   - Steps to reproduce
   - PHP version
   - Server environment

---

## 🎯 Next Steps (Optional Enhancements)

### High Priority
- [ ] Add rate limiting on booking form (prevent spam)
- [ ] Implement SMS reminders (Twilio integration)
- [ ] Add Google Calendar sync
- [ ] Create mobile-responsive PWA
- [ ] Add automated appointment reminders

### Medium Priority
- [ ] Multi-language support
- [ ] Advanced analytics dashboard
- [ ] Customer loyalty program
- [ ] Gift card system
- [ ] Package deals

### Low Priority
- [ ] Instagram integration
- [ ] Review system
- [ ] Staff commissions tracking
- [ ] Inventory management

---

## 📝 Change Log

### Version 2.0 (Current)
- ✅ Environment variable configuration
- ✅ Production-ready error handling
- ✅ SQL injection prevention
- ✅ Session security enhancements
- ✅ CSRF protection on all forms
- ✅ Comprehensive input validation
- ✅ Email verification system
- ✅ Stripe payment processing
- ✅ Automated database backups
- ✅ Security audit completed

### Version 1.0 (Original)
- Basic booking system
- Client management
- Staff scheduling
- Service catalog
- Email notifications

---

## 🔐 Security Best Practices

1. **Never commit `.env` to Git**
2. **Use strong passwords** (12+ characters, mixed case, numbers, symbols)
3. **Enable HTTPS** (free with Let's Encrypt)
4. **Keep software updated** (PHP, MySQL, Stripe SDK)
5. **Monitor logs daily** for suspicious activity
6. **Backup regularly** (automated + manual before major changes)
7. **Use Stripe test mode** until thoroughly tested
8. **Limit database user permissions** (no DROP, no GRANT)
9. **Set proper file permissions** (644 for files, 755 for directories)
10. **Review code before deployment** (especially payment handling)

---

**You're now running a secure, production-ready payment platform! 🎉**

For questions or support, check the logs first, then reach out.
