# BookingPro - Zero Commission Booking Platform

A transparent, self-hosted booking platform built for businesses who want full control. **No commission fees. No hidden costs. Full control.**

## 🎯 Why BookingPro?

Built to address common pain points in commercial booking platforms, BookingPro puts your business first:

### ✅ Our Advantages

| Feature | Typical Platforms | BookingPro |
|---------|--------|------------|
| **Commission** | Up to 20% marketplace fees | **0% - You keep everything** |
| **Client Tracking** | May charge for returning clients | **Smart recognition, no false charges** |
| **Customization** | Limited templates | **Full branding control** |
| **Hidden Fees** | SMS charges, rising costs | **100% transparent** |
| **Data Ownership** | Platform controls your data | **You own everything** |
| **Pricing Changes** | May add fees over time | **Honest from day one** |
| **Support** | Often limited or automated | **Community supported** |
| **Payment Options** | Locked to their system | **Any processor or cash** |

## 🚀 Features

### For Business Owners
- **Zero Commission Forever** - Keep 100% of your revenue
- **Full Branding** - Custom colors, logo, and design
- **Smart Client Management** - Accurate client tracking with no false "new client" charges
- **Multiple Staff & Services** - Unlimited staff members and services
- **Flexible Scheduling** - Custom working hours, blocked times, buffer periods
- **Customer Booking Page** - Beautiful, customizable booking interface
- **Dashboard & Analytics** - Track appointments, revenue, and clients
- **Email Notifications** - Automated booking confirmations and reminders
- **Self-Hosted** - Complete control over your data

### For Customers
- **Easy Online Booking** - Simple 4-step booking process
- **Transparent Pricing** - See prices upfront, no hidden fees
- **Flexible Scheduling** - Real-time availability
- **Appointment Reminders** - Email confirmations and reminders

## 📋 Requirements

- **Web Hosting**: NameCheap shared hosting or any hosting with:
  - PHP 7.4 or higher
  - MySQL 5.7 or higher
  - At least 100MB storage
- **Domain**: Your own domain name

## 🔧 Installation (5 Minutes)

### Step 1: Create Database

1. Log into your NameCheap cPanel
2. Go to **MySQL Databases**
3. Create a new database (e.g., `bookingpro`)
4. Create a database user with a strong password
5. Add the user to the database with **ALL PRIVILEGES**
6. Note down:
   - Database name
   - Database username
   - Database password

### Step 2: Import Database Schema

1. In cPanel, go to **phpMyAdmin**
2. Select your database
3. Click **Import** tab
4. Choose `database.sql` file
5. Click **Go**

### Step 3: Upload Files

1. In cPanel, go to **File Manager**
2. Navigate to `public_html` (or your domain folder)
3. Upload all files from the BookingPro package
4. Make sure the structure looks like:
   ```
   public_html/
   ├── assets/
   ├── includes/
   ├── api/
   ├── index.php
   ├── login.php
   ├── register.php
   ├── dashboard.php
   ├── config.php
   └── ...
   ```

### Step 4: Configure

1. Edit `config.php` file
2. Update database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```
3. Update site settings:
   ```php
   define('SITE_URL', 'https://yourdomain.com');
   define('ADMIN_EMAIL', 'your@email.com');
   ```
4. Save the file

### Step 5: Create Your Account

1. Visit `https://yourdomain.com`
2. Click **Get Started Free**
3. Fill in your business details
4. Create your account
5. Start adding staff and services!

## 🎨 Setup Guide

### After Installation

1. **Add Staff Members**
   - Go to Staff Management
   - Add your team members
   - Set their roles and availability

2. **Create Services**
   - Go to Services Management
   - Add your services with prices and durations
   - Organize by categories

3. **Customize Your Booking Page**
   - Go to Settings
   - Upload your logo
   - Choose your brand colors
   - Add business description

4. **Share Your Booking Link**
   - Copy your booking URL: `yourdomain.com/book.php?slug=your-business-name`
   - Share on social media, website, email signature
   - Start taking bookings!

## 📱 Customer Booking Flow

1. Customer visits your booking page
2. Selects a service
3. Chooses a staff member
4. Picks date and time from available slots
5. Enters their information
6. Confirms booking
7. Receives instant confirmation email

## 💰 Cost Comparison

### Typical Booking Platforms
- **Base**: Often "free" with conditions
- **Commission**: Up to 20% on bookings (can be hundreds per month)
- **SMS**: Charged per message
- **Hidden Fees**: Various additional costs
- **Estimated Monthly**: $200-500+ depending on volume

### BookingPro
- **Software**: Free (open source)
- **Hosting**: $3-10/month (NameCheap shared hosting)
- **Domain**: $10/year
- **Commission**: **$0 FOREVER**
- **Estimated Monthly**: **$5-15 total**

**Potential Savings**: $185-485+ per month = **$2,220-5,820+ per year!**

## 🔒 Security Features

- Password hashing with bcrypt
- SQL injection protection via prepared statements
- XSS protection with input sanitization
- CSRF protection on forms
- Session security and timeout
- Secure file uploads

## 🆘 Troubleshooting

### "Database connection failed"
- Check database credentials in `config.php`
- Ensure database exists in cPanel
- Verify user has correct permissions

### "Page not found" errors
- Check file uploads completed successfully
- Ensure files are in correct directory
- Verify .htaccess file exists (if using clean URLs)

### Can't login after registration
- Clear browser cache and cookies
- Check PHP error logs in cPanel
- Verify database tables were created correctly

### Booking page not showing
- Check business status is 'active' in database
- Verify services and staff are marked as active
- Clear browser cache

## 📞 Support

This is a self-hosted solution, which means:
- **You have full control** - Modify anything you want
- **No vendor lock-in** - Your data stays yours
- **Community support** - Help from other users
- **No support fees** - No expensive support packages required

## 🔄 Updates

To update BookingPro:
1. Backup your database and files
2. Download latest version
3. Replace files (keep your config.php)
4. Run any database migration scripts
5. Clear browser cache

## 📊 Features Roadmap

Future enhancements being considered:
- SMS notifications integration
- Payment gateway integration (Stripe, PayPal)
- Multi-location support
- Advanced reporting
- Mobile app
- Client loyalty programs
- Gift certificates
- Membership packages

## ⚖️ License

This software is provided as-is for use on NameCheap shared hosting or similar PHP hosting environments.

## 🙏 Credits

Built to address common issues in the booking platform industry:
- High commission fees on bookings
- False "new client" charges for returning customers
- Hidden and rising fees
- Limited customization options
- Inadequate customer support
- Lack of data ownership

**BookingPro puts businesses first.**

## 📝 Changelog

### Version 1.0.0 (2026-01-19)
- Initial release
- Zero commission booking system
- Smart client tracking
- Full branding customization
- Multi-staff and service management
- Real-time availability checking
- Email notifications
- Mobile-responsive design
- Self-hosted on shared hosting

---

## 🚀 Get Started Now

1. Follow the installation guide above
2. Create your account
3. Add your services and staff
4. Share your booking link
5. Start accepting bookings!

**No commission. No hidden fees. Just honest booking software.**

---

*Made with ❤️ for businesses who want to keep 100% of their revenue*
