<?php
/**
 * Stripe Webhook Handler
 * Processes Stripe events (payment confirmations, refunds, etc.)
 *
 * SETUP:
 * 1. Set STRIPE_WEBHOOK_SECRET in .env file
 * 2. Configure webhook URL in Stripe Dashboard:
 *    https://yourdomain.com/stripe-webhook.php
 * 3. Listen for events: payment_intent.succeeded, payment_intent.payment_failed, charge.refunded
 */

require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/StripePayment.php';

// Read raw POST data
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify webhook signature
try {
    if (empty(env('STRIPE_WEBHOOK_SECRET'))) {
        error_log('Stripe webhook secret not configured');
        http_response_code(500);
        exit;
    }

    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        env('STRIPE_WEBHOOK_SECRET')
    );

} catch (\Stripe\Exception\SignatureVerificationException $e) {
    error_log('Stripe webhook signature verification failed: ' . $e->getMessage());
    http_response_code(400);
    exit;
} catch (Exception $e) {
    error_log('Stripe webhook error: ' . $e->getMessage());
    http_response_code(400);
    exit;
}

// Handle the event
$stripePayment = new StripePayment();

switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;
        error_log('Payment succeeded: ' . $paymentIntent->id);
        $stripePayment->handleSuccessfulPayment($paymentIntent->id);
        break;

    case 'payment_intent.payment_failed':
        $paymentIntent = $event->data->object;
        error_log('Payment failed: ' . $paymentIntent->id);
        $failureMessage = $paymentIntent->last_payment_error->message ?? 'Unknown error';
        $stripePayment->handleFailedPayment($paymentIntent->id, $failureMessage);
        break;

    case 'charge.refunded':
        $charge = $event->data->object;
        error_log('Charge refunded: ' . $charge->id);
        // Handle refund if needed (already handled in StripePayment::refundPayment)
        break;

    default:
        error_log('Unhandled Stripe event type: ' . $event->type);
}

// Return 200 OK
http_response_code(200);
echo json_encode(['status' => 'success']);
