<?php
/**
 * PayHobe Payment Processor
 *
 * Main payment processing orchestrator
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Processor class
 */
class PayHobe_Payment_Processor {
    
    /**
     * Singleton instance
     *
     * @var PayHobe_Payment_Processor
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return PayHobe_Payment_Processor
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Handle WooCommerce checkout
        add_action('woocommerce_checkout_order_processed', array($this, 'on_order_processed'), 10, 3);
        
        // Handle AJAX payment submission
        add_action('wp_ajax_payhobe_submit_payment', array($this, 'ajax_submit_payment'));
        add_action('wp_ajax_nopriv_payhobe_submit_payment', array($this, 'ajax_submit_payment'));
        
        // Handle payment status check
        add_action('wp_ajax_payhobe_check_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_nopriv_payhobe_check_status', array($this, 'ajax_check_status'));
    }
    
    /**
     * Process payment for WooCommerce order
     *
     * @param int $order_id WooCommerce order ID
     * @param string $method Payment method (bkash, nagad, rocket, upay, bank)
     * @param array $payment_data Payment data
     * @return array Result with status and message
     */
    public function process_woocommerce_payment($order_id, $method, $payment_data) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'success' => false,
                'message' => __('Order not found.', 'payhobe')
            );
        }
        
        // Get merchant user ID
        $merchant_id = get_option('payhobe_merchant_user_id');
        
        // Prepare payment data
        $insert_data = array(
            'user_id' => $merchant_id,
            'order_id' => $order_id,
            'payment_method' => $method,
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_status' => 'pending',
            'transaction_id' => isset($payment_data['transaction_id']) ? sanitize_text_field($payment_data['transaction_id']) : null,
            'sender_number' => isset($payment_data['sender_number']) ? sanitize_text_field($payment_data['sender_number']) : null,
            'sender_account_type' => isset($payment_data['account_type']) ? sanitize_text_field($payment_data['account_type']) : 'personal',
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_ip' => WC_Geolocation::get_ip_address(),
            'notes' => isset($payment_data['notes']) ? sanitize_textarea_field($payment_data['notes']) : ''
        );
        
        // Insert payment record
        $payment_id = PayHobe_Database::insert_payment($insert_data);
        
        if (!$payment_id) {
            return array(
                'success' => false,
                'message' => __('Failed to create payment record.', 'payhobe')
            );
        }
        
        // Handle screenshot upload for bank transfers
        if ($method === 'bank' && !empty($_FILES['payment_screenshot'])) {
            $screenshot_url = $this->upload_screenshot($_FILES['payment_screenshot'], $payment_id);
            if ($screenshot_url) {
                PayHobe_Database::update_payment($payment_id, array(
                    'screenshot_url' => $screenshot_url
                ));
            }
        }
        
        // Log initial submission
        PayHobe_Database::log_transaction($payment_id, 'created', array(
            'notes' => sprintf('Payment submitted via %s', strtoupper($method))
        ));
        
        // Update order meta
        $order->update_meta_data('_payhobe_payment_id', $payment_id);
        $order->update_meta_data('_payhobe_payment_method', $method);
        $order->update_meta_data('_payhobe_transaction_id', $payment_data['transaction_id'] ?? '');
        $order->save();
        
        // Trigger auto-verify attempt
        do_action('payhobe_payment_submitted', $payment_id, $insert_data);
        
        // Check if payment was auto-verified
        $payment = PayHobe_Database::get_payment($payment_id);
        
        if ($payment->payment_status === 'confirmed') {
            return array(
                'success' => true,
                'payment_id' => $payment_id,
                'status' => 'confirmed',
                'message' => __('Payment confirmed!', 'payhobe'),
                'redirect' => $order->get_checkout_order_received_url()
            );
        }
        
        return array(
            'success' => true,
            'payment_id' => $payment_id,
            'status' => 'pending',
            'message' => __('Payment submitted! Awaiting verification.', 'payhobe'),
            'redirect' => add_query_arg('payhobe_pending', $payment_id, $order->get_checkout_order_received_url())
        );
    }
    
    /**
     * Process standalone payment (non-WooCommerce)
     *
     * @param array $payment_data Payment data
     * @return array Result with status and message
     */
    public function process_standalone_payment($payment_data) {
        $required_fields = array('method', 'amount', 'transaction_id');
        
        foreach ($required_fields as $field) {
            if (empty($payment_data[$field])) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('Missing required field: %s', 'payhobe'), $field)
                );
            }
        }
        
        $method = sanitize_text_field($payment_data['method']);
        
        if (!in_array($method, array('bkash', 'nagad', 'rocket', 'upay', 'bank'))) {
            return array(
                'success' => false,
                'message' => __('Invalid payment method.', 'payhobe')
            );
        }
        
        $merchant_id = get_option('payhobe_merchant_user_id');
        
        $insert_data = array(
            'user_id' => $merchant_id,
            'order_id' => null,
            'payment_method' => $method,
            'amount' => floatval($payment_data['amount']),
            'currency' => get_option('payhobe_currency', 'BDT'),
            'payment_status' => 'pending',
            'transaction_id' => sanitize_text_field($payment_data['transaction_id']),
            'sender_number' => isset($payment_data['sender_number']) ? sanitize_text_field($payment_data['sender_number']) : null,
            'sender_account_type' => isset($payment_data['account_type']) ? sanitize_text_field($payment_data['account_type']) : 'personal',
            'customer_name' => isset($payment_data['customer_name']) ? sanitize_text_field($payment_data['customer_name']) : null,
            'customer_email' => isset($payment_data['customer_email']) ? sanitize_email($payment_data['customer_email']) : null,
            'customer_phone' => isset($payment_data['customer_phone']) ? sanitize_text_field($payment_data['customer_phone']) : null,
            'customer_ip' => $this->get_client_ip()
        );
        
        $payment_id = PayHobe_Database::insert_payment($insert_data);
        
        if (!$payment_id) {
            return array(
                'success' => false,
                'message' => __('Failed to create payment record.', 'payhobe')
            );
        }
        
        // Handle screenshot
        if ($method === 'bank' && !empty($payment_data['screenshot_base64'])) {
            $screenshot_url = $this->save_base64_screenshot($payment_data['screenshot_base64'], $payment_id);
            if ($screenshot_url) {
                PayHobe_Database::update_payment($payment_id, array(
                    'screenshot_url' => $screenshot_url
                ));
            }
        }
        
        // Log
        PayHobe_Database::log_transaction($payment_id, 'created', array(
            'notes' => 'Payment submitted via standalone API'
        ));
        
        // Trigger auto-verification
        do_action('payhobe_payment_submitted', $payment_id, $insert_data);
        
        // Return current status
        $payment = PayHobe_Database::get_payment($payment_id);
        
        return array(
            'success' => true,
            'payment_id' => $payment_id,
            'status' => $payment->payment_status,
            'message' => $payment->payment_status === 'confirmed' 
                ? __('Payment confirmed!', 'payhobe')
                : __('Payment submitted! Awaiting verification.', 'payhobe')
        );
    }
    
    /**
     * Handle order processed hook
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted data
     * @param WC_Order $order Order object
     */
    public function on_order_processed($order_id, $posted_data, $order) {
        // Check if payment method is one of ours
        $payhobe_methods = array(
            'payhobe_bkash',
            'payhobe_nagad',
            'payhobe_rocket',
            'payhobe_upay',
            'payhobe_bank'
        );
        
        if (!in_array($order->get_payment_method(), $payhobe_methods)) {
            return;
        }
        
        // Payment processing is handled by the gateway process_payment method
    }
    
    /**
     * Handle AJAX payment submission
     */
    public function ajax_submit_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'payhobe_payment')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'payhobe')));
        }
        
        $order_id = absint($_POST['order_id'] ?? 0);
        $method = sanitize_text_field($_POST['payment_method'] ?? '');
        
        if (!$order_id || !$method) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'payhobe')));
        }
        
        $payment_data = array(
            'transaction_id' => sanitize_text_field($_POST['transaction_id'] ?? ''),
            'sender_number' => sanitize_text_field($_POST['sender_number'] ?? ''),
            'account_type' => sanitize_text_field($_POST['account_type'] ?? 'personal'),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        $result = $this->process_woocommerce_payment($order_id, $method, $payment_data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle AJAX status check
     */
    public function ajax_check_status() {
        $payment_id = absint($_POST['payment_id'] ?? $_GET['payment_id'] ?? 0);
        
        if (!$payment_id) {
            wp_send_json_error(array('message' => __('Payment ID required.', 'payhobe')));
        }
        
        $payment = PayHobe_Database::get_payment($payment_id);
        
        if (!$payment) {
            wp_send_json_error(array('message' => __('Payment not found.', 'payhobe')));
        }
        
        wp_send_json_success(array(
            'payment_id' => $payment->payment_id,
            'status' => $payment->payment_status,
            'verified_at' => $payment->verified_at
        ));
    }
    
    /**
     * Upload screenshot file
     *
     * @param array $file $_FILES array element
     * @param int $payment_id Payment ID
     * @return string|false Upload URL or false on failure
     */
    private function upload_screenshot($file, $payment_id) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf');
        
        if (!in_array($file['type'], $allowed_types)) {
            return false;
        }
        
        // Max 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            return false;
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'payhobe_' . $payment_id . '_' . wp_generate_password(8, false) . '.' . $ext;
        $file['name'] = $new_filename;
        
        // Upload
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            return $movefile['url'];
        }
        
        return false;
    }
    
    /**
     * Save base64 encoded screenshot
     *
     * @param string $base64 Base64 encoded image
     * @param int $payment_id Payment ID
     * @return string|false URL or false
     */
    private function save_base64_screenshot($base64, $payment_id) {
        // Parse data URI
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $ext = $matches[1];
            $data = substr($base64, strpos($base64, ',') + 1);
        } else {
            $ext = 'png';
            $data = $base64;
        }
        
        $data = base64_decode($data);
        
        if ($data === false) {
            return false;
        }
        
        // Max 5MB decoded
        if (strlen($data) > 5 * 1024 * 1024) {
            return false;
        }
        
        // Get upload dir
        $upload_dir = wp_upload_dir();
        $payhobe_dir = $upload_dir['basedir'] . '/payhobe-screenshots';
        
        if (!file_exists($payhobe_dir)) {
            wp_mkdir_p($payhobe_dir);
            // Protect directory
            file_put_contents($payhobe_dir . '/.htaccess', 'Options -Indexes');
        }
        
        // Generate filename
        $filename = 'payhobe_' . $payment_id . '_' . wp_generate_password(8, false) . '.' . $ext;
        $filepath = $payhobe_dir . '/' . $filename;
        
        // Save file
        if (file_put_contents($filepath, $data) !== false) {
            return $upload_dir['baseurl'] . '/payhobe-screenshots/' . $filename;
        }
        
        return false;
    }
    
    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Get first IP if comma-separated
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get payment instructions for a method
     *
     * @param string $method Payment method
     * @return array Instructions array
     */
    public function get_payment_instructions($method) {
        $merchant_id = get_option('payhobe_merchant_user_id');
        $config = PayHobe_Database::get_mfs_config($merchant_id, $method);
        
        if (!$config) {
            return array(
                'enabled' => false,
                'instructions' => array()
            );
        }
        
        $account_number = PayHobe_Encryption::decrypt($config->account_number);
        $masked_number = PayHobe_Encryption::mask_phone($account_number);
        
        $method_names = array(
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'upay' => 'Upay',
            'bank' => 'Bank Transfer'
        );
        
        $name = $method_names[$method] ?? ucfirst($method);
        
        $instructions = array();
        
        switch ($method) {
            case 'bkash':
            case 'nagad':
            case 'upay':
                $instructions = array(
                    sprintf(__('Open your %s app', 'payhobe'), $name),
                    __('Tap "Send Money"', 'payhobe'),
                    sprintf(__('Enter our %s number: %s', 'payhobe'), $name, $masked_number),
                    __('Enter the exact amount shown below', 'payhobe'),
                    __('Complete the transaction', 'payhobe'),
                    __('Enter the Transaction ID (TrxID) you received', 'payhobe')
                );
                break;
                
            case 'rocket':
                $instructions = array(
                    __('Open your Rocket app or dial *322#', 'payhobe'),
                    __('Select "Send Money"', 'payhobe'),
                    sprintf(__('Enter our Rocket number: %s', 'payhobe'), $masked_number),
                    __('Enter the exact amount shown below', 'payhobe'),
                    __('Complete the transaction with your PIN', 'payhobe'),
                    __('Enter the Transaction ID (TxnId) you received', 'payhobe')
                );
                break;
                
            case 'bank':
                $bank_name = $config->bank_name ?? '';
                $account_name = $config->account_holder_name ?? '';
                $branch = $config->bank_branch ?? '';
                $routing = $config->routing_number ?? '';
                
                $instructions = array(
                    __('Transfer the exact amount to our bank account', 'payhobe'),
                    sprintf(__('Bank: %s', 'payhobe'), $bank_name),
                    sprintf(__('Account Name: %s', 'payhobe'), $account_name),
                    sprintf(__('Account Number: %s', 'payhobe'), $masked_number),
                    $branch ? sprintf(__('Branch: %s', 'payhobe'), $branch) : null,
                    $routing ? sprintf(__('Routing: %s', 'payhobe'), $routing) : null,
                    __('Upload a screenshot/receipt of your transfer', 'payhobe')
                );
                break;
        }
        
        return array(
            'enabled' => true,
            'method' => $method,
            'method_name' => $name,
            'account_number' => $config->is_merchant_account ? $account_number : $masked_number,
            'account_type' => $config->account_type,
            'is_merchant' => $config->is_merchant_account,
            'instructions' => array_filter($instructions),
            'bank_details' => $method === 'bank' ? array(
                'bank_name' => $config->bank_name ?? '',
                'account_name' => $config->account_holder_name ?? '',
                'branch' => $config->bank_branch ?? '',
                'routing' => $config->routing_number ?? ''
            ) : null
        );
    }
    
    /**
     * Get all enabled payment methods with instructions
     *
     * @return array Array of payment methods
     */
    public function get_available_methods() {
        $methods = array('bkash', 'nagad', 'rocket', 'upay', 'bank');
        $available = array();
        
        foreach ($methods as $method) {
            $info = $this->get_payment_instructions($method);
            if ($info['enabled']) {
                $available[$method] = $info;
            }
        }
        
        return $available;
    }
}
