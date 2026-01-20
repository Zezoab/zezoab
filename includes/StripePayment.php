<?php
/**
 * Stripe Payment Processing Class
 * Handles all Stripe payment operations
 *
 * REQUIREMENTS:
 * - Install Stripe PHP SDK: composer require stripe/stripe-php
 * - Set STRIPE_SECRET_KEY in .env file
 */

class StripePayment {
    private $db;
    private $stripe;
    private $businessId;

    public function __construct($businessId = null) {
        $this->db = Database::getInstance();
        $this->businessId = $businessId;

        // Initialize Stripe
        if (STRIPE_ENABLED && !empty(STRIPE_SECRET_KEY)) {
            try {
                // Check if Stripe SDK is installed
                if (class_exists('\Stripe\Stripe')) {
                    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
                    $this->stripe = true;
                } else {
                    error_log('Stripe SDK not installed. Run: composer require stripe/stripe-php');
                    $this->stripe = false;
                }
            } catch (Exception $e) {
                error_log('Stripe initialization error: ' . $e->getMessage());
                $this->stripe = false;
            }
        } else {
            $this->stripe = false;
        }
    }

    /**
     * Check if Stripe is enabled and configured
     */
    public function isEnabled() {
        return $this->stripe === true;
    }

    /**
     * Create a payment intent for an appointment
     */
    public function createPaymentIntent($appointmentId, $amount, $currency = 'USD', $metadata = []) {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Stripe is not enabled'];
        }

        try {
            // Get appointment details
            $appointment = $this->db->fetchOne(
                "SELECT a.*, c.email, c.first_name, c.last_name, c.stripe_customer_id,
                        s.name as service_name, b.business_name
                 FROM appointments a
                 JOIN clients c ON a.client_id = c.id
                 JOIN services s ON a.service_id = s.id
                 JOIN businesses b ON a.business_id = b.id
                 WHERE a.id = ?",
                [$appointmentId]
            );

            if (!$appointment) {
                return ['success' => false, 'message' => 'Appointment not found'];
            }

            // Get or create Stripe customer
            $customerId = $this->getOrCreateCustomer(
                $appointment['client_id'],
                $appointment['email'],
                $appointment['first_name'] . ' ' . $appointment['last_name']
            );

            // Create payment intent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => round($amount * 100), // Convert to cents
                'currency' => strtolower($currency),
                'customer' => $customerId,
                'description' => $appointment['service_name'] . ' - ' . $appointment['business_name'],
                'metadata' => array_merge($metadata, [
                    'appointment_id' => $appointmentId,
                    'business_id' => $appointment['business_id'],
                    'client_id' => $appointment['client_id']
                ]),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            // Save payment record
            $paymentId = $this->db->insert('payments', [
                'business_id' => $appointment['business_id'],
                'appointment_id' => $appointmentId,
                'client_id' => $appointment['client_id'],
                'stripe_payment_intent_id' => $paymentIntent->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'payment_method' => 'stripe',
                'description' => $appointment['service_name'],
                'metadata' => json_encode($metadata)
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'payment_id' => $paymentId
            ];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe API error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment processing error: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log('Payment intent creation error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while creating the payment'];
        }
    }

    /**
     * Get or create Stripe customer
     */
    private function getOrCreateCustomer($clientId, $email, $name) {
        // Check if customer already has Stripe ID
        $client = $this->db->fetchOne(
            "SELECT stripe_customer_id FROM clients WHERE id = ?",
            [$clientId]
        );

        if (!empty($client['stripe_customer_id'])) {
            return $client['stripe_customer_id'];
        }

        // Create new Stripe customer
        try {
            $customer = \Stripe\Customer::create([
                'email' => $email,
                'name' => $name,
                'metadata' => ['client_id' => $clientId]
            ]);

            // Save Stripe customer ID
            $this->db->update(
                'clients',
                ['stripe_customer_id' => $customer->id],
                'id = :id',
                ['id' => $clientId]
            );

            return $customer->id;

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe customer creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle successful payment (called from webhook)
     */
    public function handleSuccessfulPayment($paymentIntentId) {
        try {
            // Update payment record
            $payment = $this->db->fetchOne(
                "SELECT * FROM payments WHERE stripe_payment_intent_id = ?",
                [$paymentIntentId]
            );

            if (!$payment) {
                error_log("Payment not found for intent: $paymentIntentId");
                return false;
            }

            $this->db->update(
                'payments',
                [
                    'status' => 'succeeded',
                    'paid_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $payment['id']]
            );

            // Update appointment payment status
            if ($payment['appointment_id']) {
                $this->db->update(
                    'appointments',
                    [
                        'payment_status' => 'paid',
                        'paid_amount' => $payment['amount']
                    ],
                    'id = :id',
                    ['id' => $payment['appointment_id']]
                );
            }

            return true;

        } catch (Exception $e) {
            error_log('Error handling successful payment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle failed payment
     */
    public function handleFailedPayment($paymentIntentId, $failureReason = null) {
        try {
            $payment = $this->db->fetchOne(
                "SELECT * FROM payments WHERE stripe_payment_intent_id = ?",
                [$paymentIntentId]
            );

            if (!$payment) {
                return false;
            }

            $this->db->update(
                'payments',
                [
                    'status' => 'failed',
                    'failure_reason' => $failureReason
                ],
                'id = :id',
                ['id' => $payment['id']]
            );

            return true;

        } catch (Exception $e) {
            error_log('Error handling failed payment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process refund
     */
    public function refundPayment($paymentId, $amount = null, $reason = null) {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Stripe is not enabled'];
        }

        try {
            $payment = $this->db->fetchOne(
                "SELECT * FROM payments WHERE id = ?",
                [$paymentId]
            );

            if (!$payment) {
                return ['success' => false, 'message' => 'Payment not found'];
            }

            if ($payment['status'] !== 'succeeded') {
                return ['success' => false, 'message' => 'Only successful payments can be refunded'];
            }

            // Default to full refund
            $refundAmount = $amount ?? $payment['amount'];

            // Create refund in Stripe
            $refund = \Stripe\Refund::create([
                'payment_intent' => $payment['stripe_payment_intent_id'],
                'amount' => round($refundAmount * 100), // Convert to cents
                'reason' => $reason ?? 'requested_by_customer',
                'metadata' => [
                    'payment_id' => $paymentId,
                    'business_id' => $payment['business_id']
                ]
            ]);

            // Update payment record
            $this->db->update(
                'payments',
                [
                    'status' => 'refunded',
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $reason,
                    'refunded_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $paymentId]
            );

            // Update appointment
            if ($payment['appointment_id']) {
                $this->db->update(
                    'appointments',
                    ['payment_status' => 'refunded'],
                    'id = :id',
                    ['id' => $payment['appointment_id']]
                );
            }

            return ['success' => true, 'refund_id' => $refund->id];

        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe refund error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Refund failed: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log('Refund processing error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while processing the refund'];
        }
    }

    /**
     * Get payment by ID
     */
    public function getPayment($paymentId) {
        return $this->db->fetchOne(
            "SELECT * FROM payments WHERE id = ?",
            [$paymentId]
        );
    }

    /**
     * Get payments for appointment
     */
    public function getAppointmentPayments($appointmentId) {
        return $this->db->fetchAll(
            "SELECT * FROM payments WHERE appointment_id = ? ORDER BY created_at DESC",
            [$appointmentId]
        );
    }

    /**
     * Get payments for business (with pagination)
     */
    public function getBusinessPayments($businessId, $limit = 50, $offset = 0) {
        return $this->db->fetchAll(
            "SELECT p.*, c.first_name, c.last_name, c.email,
                    a.appointment_date, a.start_time
             FROM payments p
             JOIN clients c ON p.client_id = c.id
             LEFT JOIN appointments a ON p.appointment_id = a.id
             WHERE p.business_id = ?
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?",
            [$businessId, $limit, $offset]
        );
    }
}
