<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';

$auth = new Auth();

// If logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Zero Commission Booking Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="landing-page">
        <header class="hero">
            <nav class="navbar">
                <div class="container">
                    <div class="logo">
                        <h1><?php echo SITE_NAME; ?></h1>
                    </div>
                    <div class="nav-links">
                        <a href="login.php" class="btn btn-outline">Login</a>
                        <a href="register.php" class="btn btn-primary">Get Started Free</a>
                    </div>
                </div>
            </nav>

            <div class="hero-content container">
                <div class="hero-text">
                    <h1>The Honest Booking Platform</h1>
                    <h2>Zero Commission. Full Control. Your Data.</h2>
                    <p class="hero-description">
                        Built by businesses, for businesses. No hidden fees, no marketplace charges,
                        no bait-and-switch. Own your platform, keep 100% of your revenue.
                    </p>
                    <div class="hero-buttons">
                        <a href="register.php" class="btn btn-primary btn-large">Start Free Today</a>
                        <a href="#features" class="btn btn-outline btn-large">Learn More</a>
                    </div>
                </div>
            </div>
        </header>

        <section id="features" class="features-section">
            <div class="container">
                <h2 class="section-title">Why Choose <?php echo SITE_NAME; ?>?</h2>
                <p class="section-subtitle">A booking platform built for your success</p>

                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <h3>Zero Commission Forever</h3>
                        <p>We charge ZERO commission. Ever. You keep 100% of your revenue. No tricks, no hidden fees, no marketplace fees.</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">✅</div>
                        <h3>Accurate Client Tracking</h3>
                        <p>Our smart system properly recognizes returning clients. No more false "new client" charges for customers who found you on Google or came back directly.</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">🎨</div>
                        <h3>Full Brand Customization</h3>
                        <p>Make your booking page truly yours. Custom colors, logo, design. Not a cookie-cutter template that screams "I use a booking platform".</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">💬</div>
                        <h3>Real Customer Support</h3>
                        <p>No bot responses. No "policy says no" dismissals. Real support that actually helps solve problems.</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">🏆</div>
                        <h3>You Own Your Data</h3>
                        <p>Self-hosted means your business data stays yours. No vendor lock-in. Export anytime. Move anytime.</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Transparent Pricing</h3>
                        <p>What you see is what you get. No surprise fees after "lifetime free" promises. No per-text-message charges.</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">💳</div>
                        <h3>Your Payment Processor</h3>
                        <p>Use Stripe, PayPal, or take cash. We don't force you into our payment system to extract more fees.</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3>No Bait & Switch</h3>
                        <p>We're upfront about everything. No adding fees later. No charging for features that were "always free".</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="comparison-section">
            <div class="container">
                <h2 class="section-title">Square vs Typical Platforms vs <?php echo SITE_NAME; ?></h2>
                <p class="section-subtitle">See how we stack up against the competition</p>

                <div class="comparison-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th>Square</th>
                                <th>Others</th>
                                <th><?php echo SITE_NAME; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Transaction Fees</td>
                                <td class="bad">2.6-3.5% + $0.15-0.30</td>
                                <td class="bad">Up to 20% marketplace fees</td>
                                <td class="good">0% - You keep everything</td>
                            </tr>
                            <tr>
                                <td>Monthly Cost</td>
                                <td class="bad">$29/month per location</td>
                                <td class="bad">$50-200+/month</td>
                                <td class="good">$3-10/month (hosting only)</td>
                            </tr>
                            <tr>
                                <td>Multi-Location</td>
                                <td class="bad">$29 per location</td>
                                <td class="bad">Extra fees per location</td>
                                <td class="good">Unlimited, no extra cost</td>
                            </tr>
                            <tr>
                                <td>Branding Customization</td>
                                <td class="bad">Limited templates</td>
                                <td class="bad">Basic customization</td>
                                <td class="good">Full control - colors, logo, design</td>
                            </tr>
                            <tr>
                                <td>Calendar Integration</td>
                                <td class="bad">Google Calendar only</td>
                                <td class="bad">Limited options</td>
                                <td class="good">Export to any calendar (iCal, etc.)</td>
                            </tr>
                            <tr>
                                <td>Advanced Scheduling</td>
                                <td class="bad">Basic recurring only</td>
                                <td class="bad">Limited patterns</td>
                                <td class="good">Biweekly, exceptions, date ranges</td>
                            </tr>
                            <tr>
                                <td>Client Tracking</td>
                                <td class="bad">May charge for returning clients</td>
                                <td class="bad">Unreliable recognition</td>
                                <td class="good">Smart email + phone matching</td>
                            </tr>
                            <tr>
                                <td>Group Classes</td>
                                <td class="bad">Limited support</td>
                                <td class="bad">Extra tier required</td>
                                <td class="good">Built-in (coming soon)</td>
                            </tr>
                            <tr>
                                <td>Reporting & Analytics</td>
                                <td class="bad">Basic only</td>
                                <td class="bad">Limited insights</td>
                                <td class="good">Comprehensive analytics</td>
                            </tr>
                            <tr>
                                <td>Data Ownership</td>
                                <td class="bad">Square controls your data</td>
                                <td class="bad">Platform controls data</td>
                                <td class="good">You own everything</td>
                            </tr>
                            <tr>
                                <td>Customer Support</td>
                                <td class="bad">No weekend support, slow</td>
                                <td class="bad">Often automated</td>
                                <td class="good">Community + full control</td>
                            </tr>
                            <tr>
                                <td>SMS Customization</td>
                                <td class="bad">Can't edit messages</td>
                                <td class="bad">Pay per message</td>
                                <td class="good">Fully customizable templates</td>
                            </tr>
                            <tr>
                                <td>Payment Flexibility</td>
                                <td class="bad">Locked to Square</td>
                                <td class="bad">Platform-specific</td>
                                <td class="good">Any processor or cash</td>
                            </tr>
                            <tr>
                                <td>App Stability</td>
                                <td class="bad">Reported crashes</td>
                                <td class="bad">Varies</td>
                                <td class="good">Reliable web-based</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 2rem; text-align: center; padding: 2rem; background: var(--bg-tertiary); border-radius: var(--border-radius);">
                    <h3 style="margin-bottom: 1rem;">Real Cost Comparison</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; text-align: center;">
                        <div>
                            <h4 style="color: var(--text-secondary);">Square</h4>
                            <p style="font-size: 1.75rem; font-weight: 700; color: var(--danger-color); margin: 0.5rem 0;">$500-1000+</p>
                            <p style="font-size: 0.875rem; color: var(--text-muted);">per month with fees</p>
                        </div>
                        <div>
                            <h4 style="color: var(--text-secondary);">Others</h4>
                            <p style="font-size: 1.75rem; font-weight: 700; color: var(--danger-color); margin: 0.5rem 0;">$200-500+</p>
                            <p style="font-size: 0.875rem; color: var(--text-muted);">per month with fees</p>
                        </div>
                        <div>
                            <h4 style="color: var(--text-secondary);"><?php echo SITE_NAME; ?></h4>
                            <p style="font-size: 1.75rem; font-weight: 700; color: var(--success-color); margin: 0.5rem 0;">$5-15</p>
                            <p style="font-size: 0.875rem; color: var(--text-muted);">per month total</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <div class="container">
                <h2>Ready to Take Control?</h2>
                <p>Join businesses who want to keep 100% of their revenue</p>
                <a href="register.php" class="btn btn-primary btn-large">Get Started - It's Free</a>
                <p class="cta-note">No credit card required. Setup in 5 minutes.</p>
            </div>
        </section>

        <footer class="footer">
            <div class="container">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Built with transparency in mind.</p>
            </div>
        </footer>
    </div>
</body>
</html>
