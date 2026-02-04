<?php
/**
 * PayHobe Verification Handler
 *
 * Handles payment verification logic
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verification class
 */
class PayHobe_Verification {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into payment submission
        add_action('payhobe_payment_submitted', array($this, 'auto_verify_payment'), 10, 2);
        
        // Cron job for batch verification
        add_action('payhobe_auto_verify_payments', array($this, 'batch_verify_pending'));
    }
    
    /**
     * Attempt automatic verification when payment is submitted
     *
     * @param int $payment_id Payment ID
     * @param array $payment_data Payment data
     */
    public function auto_verify_payment($payment_id, $payment_data) {
        // Check if auto-verify is enabled
        if (!get_option('payhobe_auto_verify', true)) {
            return;
        }
        
        // Bank transfers require manual verification
        if ($payment_data['payment_method'] === 'bank') {
            return;
        }
        
        $merchant_id = get_option('payhobe_merchant_user_id');
        
        // Check if SMS parser is enabled for this method
        $config = PayHobe_Database::get_mfs_config($merchant_id, $payment_data['payment_method']);
        if (!$config || !$config->sms_parser_enabled) {
            return;
        }
        
        // Try to find matching SMS
        $matched = $this->find_matching_sms(
            $payment_data['transaction_id'],
            $payment_data['sender_number'],
            $payment_data['payment_method'],
            $payment_data['amount']
        );
        
        if ($matched) {
            $this->confirm_payment($payment_id, $matched['sms_id'], 'auto');
        }
    }
    
    /**
     * Try to match SMS log with pending payment
     *
     * @param int $sms_id SMS log ID
     * @param array $parsed Parsed SMS data
     * @return bool Whether a match was found
     */
    public function try_match_sms($sms_id, $parsed) {
        if (empty($parsed['transaction_id']) || !$parsed['is_payment']) {
            return false;
        }
        
        // Find pending payment with matching transaction ID
        $payment = PayHobe_Database::find_payment_by_transaction_id(
            $parsed['transaction_id'],
            $parsed['method']
        );
        
        if (!$payment || $payment->payment_status !== 'pending') {
            return false;
        }
        
        // Additional verification: match sender number if available
        if (!empty($parsed['sender_number']) && !empty($payment->sender_number)) {
            $payment_sender = PayHobe_Encryption::decrypt($payment->sender_number);
            if ($this->normalize_phone($parsed['sender_number']) !== $this->normalize_phone($payment_sender)) {
                // Sender doesn't match - flag for manual review
                PayHobe_Database::log_transaction($payment->payment_id, 'sender_mismatch', array(
                    'notes' => sprintf(
                        'SMS sender %s does not match payment sender',
                        PayHobe_Encryption::mask_phone($parsed['sender_number'])
                    )
                ));
                return false;
            }
        }
        
        // Amount verification (if parsed)
        if (!empty($parsed['amount']) && $parsed['amount'] > 0) {
            $tolerance = 1; // Allow 1 BDT tolerance for fees
            if (abs($parsed['amount'] - floatval($payment->amount)) > $tolerance) {
                // Amount doesn't match - flag for manual review
                PayHobe_Database::log_transaction($payment->payment_id, 'amount_mismatch', array(
                    'notes' => sprintf(
                        'SMS amount %.2f does not match payment amount %.2f',
                        $parsed['amount'],
                        $payment->amount
                    )
                ));
                // Don't auto-confirm, but mark SMS as related
                PayHobe_Database::mark_sms_processed($sms_id, $payment->payment_id);
                return false;
            }
        }
        
        // All checks passed - confirm payment
        $this->confirm_payment($payment->payment_id, $sms_id, 'sms');
        
        return true;
    }
    
    /**
     * Find matching SMS for a payment
     *
     * @param string $transaction_id Transaction ID
     * @param string $sender_number Sender phone number
     * @param string $method Payment method
     * @param float $amount Amount
     * @return array|null Matching SMS data or null
     */
    private function find_matching_sms($transaction_id, $sender_number, $method, $amount) {
        global $wpdb;
        
        $merchant_id = get_option('payhobe_merchant_user_id');
        $sms_table = PayHobe_Database::get_table_name('sms_logs');
        
        // Look for SMS received in the last 30 minutes with matching transaction ID
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        
        $sms_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $sms_table 
             WHERE user_id = %d 
             AND is_processed = 0 
             AND received_at >= %s
             AND (payment_method = %s OR payment_method = 'unknown')
             ORDER BY received_at DESC
             LIMIT 50",
            $merchant_id,
            $cutoff,
            $method
        ));
        
        foreach ($sms_logs as $log) {
            // Decrypt and parse if not already parsed
            $message = PayHobe_Encryption::decrypt($log->message_body);
            
            $parsed_trx = $log->parsed_transaction_id ?: null;
            
            if (!$parsed_trx && !empty($message)) {
                $parser = new PayHobe_SMS_Parser();
                $parsed = $parser->parse($message);
                $parsed_trx = $parsed['transaction_id'];
            }
            
            // Check if transaction ID matches
            if ($parsed_trx && strtoupper($parsed_trx) === strtoupper($transaction_id)) {
                // Check sender if available
                if (!empty($sender_number) && !empty($log->parsed_sender)) {
                    if ($this->normalize_phone($log->parsed_sender) !== $this->normalize_phone($sender_number)) {
                        continue;
                    }
                }
                
                return array(
                    'sms_id' => $log->sms_id,
                    'transaction_id' => $parsed_trx,
                    'sender' => $log->parsed_sender,
                    'amount' => $log->parsed_amount
                );
            }
        }
        
        return null;
    }
    
    /**
     * Confirm a payment
     *
     * @param int $payment_id Payment ID
     * @param int|null $sms_id Matching SMS ID (if any)
     * @param string $source Verification source (sms, auto, manual)
     * @return bool Success
     */
    public function confirm_payment($payment_id, $sms_id = null, $source = 'auto') {
        $payment = PayHobe_Database::get_payment($payment_id);
        
        if (!$payment || $payment->payment_status !== 'pending') {
            return false;
        }
        
        // Update payment
        $update_data = array(
            'payment_status' => 'confirmed',
            'verification_source' => $source,
            'verified_at' => current_time('mysql')
        );
        
        $result = PayHobe_Database::update_payment($payment_id, $update_data);
        
        if (!$result) {
            return false;
        }
        
        // Mark SMS as processed
        if ($sms_id) {
            PayHobe_Database::mark_sms_processed($sms_id, $payment_id);
        }
        
        // Update WooCommerce order
        if ($payment->order_id && class_exists('WC_Order')) {
            $order = wc_get_order($payment->order_id);
            if ($order && !$order->is_paid()) {
                $order->payment_complete($payment->transaction_id);
                $order->add_order_note(sprintf(
                    __('Payment auto-verified via PayHobe (%s). Transaction ID: %s', 'payhobe'),
                    $source,
                    $payment->transaction_id
                ));
            }
        }
        
        // Log the verification
        PayHobe_Database::log_transaction($payment_id, 'auto_verified', array(
            'new_status' => 'confirmed',
            'old_status' => 'pending',
            'notes' => sprintf('Verified via %s', $source)
        ));
        
        // Send notification
        $this->send_verification_notification($payment);
        
        do_action('payhobe_payment_auto_verified', $payment_id, $source);
        
        return true;
    }
    
    /**
     * Batch verify pending payments
     */
    public function batch_verify_pending() {
        if (!get_option('payhobe_auto_verify', true)) {
            return;
        }
        
        $pending_payments = PayHobe_Database::get_payments(array(
            'status' => 'pending',
            'limit' => 100,
            'orderby' => 'created_at',
            'order' => 'ASC'
        ));
        
        foreach ($pending_payments as $payment) {
            // Skip bank transfers
            if ($payment->payment_method === 'bank') {
                continue;
            }
            
            // Skip if too old (> 24 hours by default)
            $timeout_hours = get_option('payhobe_pending_timeout_hours', 24);
            $created_time = strtotime($payment->created_at);
            if (time() - $created_time > $timeout_hours * 3600) {
                // Mark as failed due to timeout
                $this->timeout_payment($payment->payment_id);
                continue;
            }
            
            // Try to find matching SMS
            $sender = $payment->sender_number ? PayHobe_Encryption::decrypt($payment->sender_number) : null;
            
            $matched = $this->find_matching_sms(
                $payment->transaction_id,
                $sender,
                $payment->payment_method,
                $payment->amount
            );
            
            if ($matched) {
                $this->confirm_payment($payment->payment_id, $matched['sms_id'], 'auto');
            }
        }
    }
    
    /**
     * Mark payment as timed out
     *
     * @param int $payment_id Payment ID
     */
    private function timeout_payment($payment_id) {
        PayHobe_Database::update_payment($payment_id, array(
            'payment_status' => 'failed',
            'notes' => __('Payment verification timed out.', 'payhobe')
        ));
        
        PayHobe_Database::log_transaction($payment_id, 'timeout', array(
            'old_status' => 'pending',
            'new_status' => 'failed'
        ));
        
        $payment = PayHobe_Database::get_payment($payment_id);
        
        if ($payment && $payment->order_id && class_exists('WC_Order')) {
            $order = wc_get_order($payment->order_id);
            if ($order) {
                $order->update_status('failed', __('PayHobe: Payment verification timed out.', 'payhobe'));
            }
        }
    }
    
    /**
     * Normalize phone number for comparison
     *
     * @param string $phone Phone number
     * @return string Normalized phone
     */
    private function normalize_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle country code
        if (strlen($phone) === 13 && strpos($phone, '880') === 0) {
            $phone = '0' . substr($phone, 3);
        } elseif (strlen($phone) === 10 && strpos($phone, '1') === 0) {
            $phone = '0' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Send verification notification
     *
     * @param object $payment Payment object
     */
    private function send_verification_notification($payment) {
        if (!get_option('payhobe_email_notifications', true)) {
            return;
        }
        
        // Notify merchant
        $merchant_id = get_option('payhobe_merchant_user_id');
        $merchant = get_user_by('ID', $merchant_id);
        
        if ($merchant) {
            $subject = sprintf(
                __('[PayHobe] Payment Confirmed - %s %s', 'payhobe'),
                number_format($payment->amount, 2),
                get_option('payhobe_currency', 'BDT')
            );
            
            $message = sprintf(
                __("A payment has been automatically verified.\n\nTransaction ID: %s\nMethod: %s\nAmount: %s %s\nOrder ID: %s\n\nView in dashboard: %s", 'payhobe'),
                $payment->transaction_id,
                strtoupper($payment->payment_method),
                number_format($payment->amount, 2),
                get_option('payhobe_currency', 'BDT'),
                $payment->order_id ?: 'N/A',
                admin_url('admin.php?page=payhobe-payments&payment_id=' . $payment->payment_id)
            );
            
            wp_mail($merchant->user_email, $subject, $message);
        }
        
        // Notify customer
        if (!empty($payment->customer_email)) {
            $subject = sprintf(
                __('Payment Confirmed - Order #%s', 'payhobe'),
                $payment->order_id
            );
            
            $message = sprintf(
                __("Your payment has been confirmed!\n\nTransaction ID: %s\nAmount: %s %s\n\nThank you for your purchase.", 'payhobe'),
                $payment->transaction_id,
                number_format($payment->amount, 2),
                get_option('payhobe_currency', 'BDT')
            );
            
            wp_mail($payment->customer_email, $subject, $message);
        }
    }
    
    /**
     * Manual verification by admin
     *
     * @param int $payment_id Payment ID
     * @param string $action 'confirm' or 'reject'
     * @param int $admin_id Admin user ID
     * @param string $notes Optional notes
     * @return bool Success
     */
    public function manual_verify($payment_id, $action, $admin_id, $notes = '') {
        $payment = PayHobe_Database::get_payment($payment_id);
        
        if (!$payment || $payment->payment_status !== 'pending') {
            return false;
        }
        
        $update_data = array(
            'verified_by' => $admin_id,
            'verified_at' => current_time('mysql'),
            'verification_source' => 'manual'
        );
        
        if (!empty($notes)) {
            $update_data['notes'] = $notes;
        }
        
        if ($action === 'confirm') {
            $update_data['payment_status'] = 'confirmed';
            
            // Update WooCommerce order
            if ($payment->order_id && class_exists('WC_Order')) {
                $order = wc_get_order($payment->order_id);
                if ($order) {
                    $order->payment_complete($payment->transaction_id);
                    $order->add_order_note(sprintf(
                        __('Payment manually verified via PayHobe. Transaction ID: %s', 'payhobe'),
                        $payment->transaction_id
                    ));
                }
            }
        } else {
            $update_data['payment_status'] = 'failed';
            
            // Update WooCommerce order
            if ($payment->order_id && class_exists('WC_Order')) {
                $order = wc_get_order($payment->order_id);
                if ($order) {
                    $order->update_status('failed', __('Payment manually rejected via PayHobe.', 'payhobe'));
                }
            }
        }
        
        return PayHobe_Database::update_payment($payment_id, $update_data);
    }
}

// Initialize
new PayHobe_Verification();
