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
                <h2 class="section-title">Why We're Better Than Fresha</h2>
                <p class="section-subtitle">We fixed everything that's wrong with other booking platforms</p>

                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <h3>Zero Commission Forever</h3>
                        <p>Unlike Fresha's 20% "marketplace fee", we charge ZERO commission. Ever. You keep 100% of your revenue. No tricks, no hidden fees.</p>
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
                <h2 class="section-title">Fresha vs <?php echo SITE_NAME; ?></h2>

                <div class="comparison-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th>Fresha</th>
                                <th><?php echo SITE_NAME; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Commission on bookings</td>
                                <td class="bad">20% "marketplace fee"</td>
                                <td class="good">0% - You keep everything</td>
                            </tr>
                            <tr>
                                <td>Client tracking accuracy</td>
                                <td class="bad">Charges for returning clients</td>
                                <td class="good">Smart recognition system</td>
                            </tr>
                            <tr>
                                <td>Booking page customization</td>
                                <td class="bad">Limited, templated</td>
                                <td class="good">Fully customizable branding</td>
                            </tr>
                            <tr>
                                <td>Hidden fees</td>
                                <td class="bad">SMS charges, rising costs</td>
                                <td class="good">Transparent, predictable</td>
                            </tr>
                            <tr>
                                <td>Data ownership</td>
                                <td class="bad">They control your data</td>
                                <td class="good">You own everything</td>
                            </tr>
                            <tr>
                                <td>Customer support</td>
                                <td class="bad">Dismissive, no complaints process</td>
                                <td class="good">Responsive and helpful</td>
                            </tr>
                            <tr>
                                <td>Payment processors</td>
                                <td class="bad">Locked to their system</td>
                                <td class="good">Use any processor or cash</td>
                            </tr>
                            <tr>
                                <td>Pricing changes</td>
                                <td class="bad">"Lifetime free" then adds fees</td>
                                <td class="good">Honest from day one</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <div class="container">
                <h2>Ready to Take Control?</h2>
                <p>Join businesses who are tired of being nickel-and-dimed by Fresha</p>
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
