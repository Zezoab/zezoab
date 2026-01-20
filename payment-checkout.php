<?php
/**
 * Payment Checkout Page
 * Allows customers to pay for their appointments
 */

require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/StripePayment.php';
require_once 'includes/functions.php';

$db = Database::getInstance();

// Get appointment ID from URL
$appointmentId = (int)($_GET['appointment'] ?? 0);
$token = $_GET['token'] ?? '';

if (empty($appointmentId) || empty($token)) {
    die('Invalid payment link');
}

// Get appointment details
$appointment = $db->fetchOne(
    "SELECT a.*, c.first_name, c.last_name, c.email,
            s.name as service_name, s.price,
            b.business_name, b.currency, b.primary_color, b.logo_url
     FROM appointments a
     JOIN clients c ON a.client_id = c.id
     JOIN services s ON a.service_id = s.id
     JOIN businesses b ON a.business_id = b.id
     WHERE a.id = ? AND MD5(CONCAT(a.id, a.created_at)) = ?",
    [$appointmentId, $token]
);

if (!$appointment) {
    die('Appointment not found or link is invalid');
}

// Check if already paid
if ($appointment['payment_status'] === 'paid') {
    $message = 'This appointment has already been paid.';
    $paid = true;
} else {
    $paid = false;

    // Create payment intent if form submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_intent') {
        $stripe = new StripePayment($appointment['business_id']);

        if (!$stripe->isEnabled()) {
            $error = 'Online payments are not configured for this business.';
        } else {
            $result = $stripe->createPaymentIntent(
                $appointmentId,
                $appointment['price'],
                $appointment['currency']
            );

            if ($result['success']) {
                $clientSecret = $result['client_secret'];
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - <?php echo htmlspecialchars($appointment['business_name']); ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/booking.css">
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        :root {
            --primary-color: <?php echo $appointment['primary_color'] ?? '#6366F1'; ?>;
        }
        .payment-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }
        #payment-form {
            margin-top: 20px;
        }
        #payment-element {
            margin: 20px 0;
        }
        #submit-button {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        #submit-button:hover {
            opacity: 0.9;
        }
        #submit-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .payment-summary {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <?php if ($appointment['logo_url']): ?>
            <img src="<?php echo htmlspecialchars($appointment['logo_url']); ?>" alt="Logo" style="max-height: 60px; margin-bottom: 20px;">
        <?php endif; ?>

        <h1>Complete Your Payment</h1>
        <p><?php echo htmlspecialchars($appointment['business_name']); ?></p>

        <div class="payment-summary">
            <h3>Appointment Details</h3>
            <p><strong>Service:</strong> <?php echo htmlspecialchars($appointment['service_name']); ?></p>
            <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
            <p><strong>Time:</strong> <?php echo formatTime($appointment['start_time']); ?></p>
            <p><strong>Client:</strong> <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
            <hr>
            <p><strong>Amount Due:</strong> <?php echo formatCurrency($appointment['price'], $appointment['currency']); ?></p>
        </div>

        <?php if ($paid): ?>
            <div class="alert alert-success">
                <h3>✅ Payment Completed</h3>
                <p><?php echo $message; ?></p>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php elseif (isset($clientSecret)): ?>
            <form id="payment-form">
                <div id="payment-element"></div>
                <button id="submit-button" type="submit">
                    <span id="button-text">Pay <?php echo formatCurrency($appointment['price'], $appointment['currency']); ?></span>
                    <span id="spinner" style="display: none;">Processing...</span>
                </button>
                <div id="payment-message" style="margin-top: 20px;"></div>
            </form>

            <script>
                const stripe = Stripe('<?php echo STRIPE_PUBLISHABLE_KEY; ?>');
                const elements = stripe.elements({clientSecret: '<?php echo $clientSecret; ?>'});
                const paymentElement = elements.create('payment');
                paymentElement.mount('#payment-element');

                const form = document.getElementById('payment-form');
                const submitButton = document.getElementById('submit-button');
                const buttonText = document.getElementById('button-text');
                const spinner = document.getElementById('spinner');
                const messageDiv = document.getElementById('payment-message');

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    submitButton.disabled = true;
                    buttonText.style.display = 'none';
                    spinner.style.display = 'inline';

                    const {error} = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: '<?php echo SITE_URL; ?>/payment-success.php?appointment=<?php echo $appointmentId; ?>&token=<?php echo $token; ?>',
                        },
                    });

                    if (error) {
                        messageDiv.innerHTML = '<div class="alert alert-error">' + error.message + '</div>';
                        submitButton.disabled = false;
                        buttonText.style.display = 'inline';
                        spinner.style.display = 'none';
                    }
                });
            </script>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="create_intent">
                <button type="submit" class="btn btn-primary btn-block">Proceed to Payment</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
