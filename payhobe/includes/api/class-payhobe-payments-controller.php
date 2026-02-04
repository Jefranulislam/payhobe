<?php
/**
 * PayHobe Payments Controller
 *
 * Handles payment-related API endpoints
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payments Controller class
 */
class PayHobe_Payments_Controller {
    
    /**
     * Route namespace
     */
    private $namespace;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->namespace = PayHobe_REST_API::get_namespace();
    }
    
    /**
     * Register routes
     */
    public function register_routes() {
        // List payments
        register_rest_route($this->namespace, '/payments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_payments'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => $this->get_collection_params()
        ));
        
        // Create payment (from checkout)
        register_rest_route($this->namespace, '/payments', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_payment'),
            'permission_callback' => '__return_true',
            'args' => array(
                'order_id' => array('type' => 'integer'),
                'payment_method' => array('required' => true, 'type' => 'string'),
                'transaction_id' => array('required' => true, 'type' => 'string'),
                'sender_number' => array('type' => 'string'),
                'amount' => array('required' => true, 'type' => 'number'),
                'customer_email' => array('type' => 'string'),
                'customer_phone' => array('type' => 'string')
            )
        ));
        
        // Get single payment
        register_rest_route($this->namespace, '/payments/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_payment'),
            'permission_callback' => array($this, 'check_payment_permission'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer')
            )
        ));
        
        // Update payment
        register_rest_route($this->namespace, '/payments/(?P<id>\d+)', array(
            'methods' => 'PUT,PATCH',
            'callback' => array($this, 'update_payment'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer'),
                'payment_status' => array('type' => 'string'),
                'notes' => array('type' => 'string')
            )
        ));
        
        // Verify payment
        register_rest_route($this->namespace, '/payments/(?P<id>\d+)/verify', array(
            'methods' => 'POST',
            'callback' => array($this, 'verify_payment'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer'),
                'action' => array('required' => true, 'type' => 'string', 'enum' => array('confirm', 'reject'))
            )
        ));
        
        // Check payment status (public endpoint for customers)
        register_rest_route($this->namespace, '/payments/check', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_payment_status'),
            'permission_callback' => '__return_true',
            'args' => array(
                'order_id' => array('type' => 'integer'),
                'transaction_id' => array('type' => 'string')
            )
        ));
        
        // Upload payment screenshot
        register_rest_route($this->namespace, '/payments/(?P<id>\d+)/screenshot', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_screenshot'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer')
            )
        ));
        
        // Export payments
        register_rest_route($this->namespace, '/payments/export', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_payments'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => $this->get_collection_params()
        ));
        
        // Payment statistics
        register_rest_route($this->namespace, '/payments/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_payment_stats'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'date_from' => array('type' => 'string'),
                'date_to' => array('type' => 'string')
            )
        ));
    }
    
    /**
     * Get collection parameters
     */
    private function get_collection_params() {
        return array(
            'page' => array(
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ),
            'per_page' => array(
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100
            ),
            'status' => array(
                'type' => 'string',
                'enum' => array('', 'pending', 'confirmed', 'failed', 'refunded', 'cancelled')
            ),
            'method' => array(
                'type' => 'string',
                'enum' => array('', 'bkash', 'rocket', 'nagad', 'upay', 'bank')
            ),
            'search' => array(
                'type' => 'string'
            ),
            'date_from' => array(
                'type' => 'string'
            ),
            'date_to' => array(
                'type' => 'string'
            ),
            'order_id' => array(
                'type' => 'integer'
            ),
            'orderby' => array(
                'type' => 'string',
                'default' => 'created_at',
                'enum' => array('created_at', 'amount', 'payment_status')
            ),
            'order' => array(
                'type' => 'string',
                'default' => 'desc',
                'enum' => array('asc', 'desc')
            )
        );
    }
    
    /**
     * Check merchant permission
     */
    public function check_merchant_permission($request) {
        $user_id = PayHobe_REST_API::authenticate_request($request);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        if (!PayHobe_REST_API::is_merchant($user_id)) {
            return new WP_Error('payhobe_forbidden', __('Access denied.', 'payhobe'), array('status' => 403));
        }
        
        return true;
    }
    
    /**
     * Check payment access permission
     */
    public function check_payment_permission($request) {
        $payment_id = (int) $request->get_param('id');
        $payment = PayHobe_Database::get_payment($payment_id, false);
        
        if (!$payment) {
            return new WP_Error('payhobe_not_found', __('Payment not found.', 'payhobe'), array('status' => 404));
        }
        
        // Check if merchant
        $user_id = PayHobe_REST_API::authenticate_request($request);
        
        if (!is_wp_error($user_id) && PayHobe_REST_API::is_merchant($user_id)) {
            return true;
        }
        
        // Check if customer owns the payment (by order_id and nonce)
        $nonce = $request->get_header('X-PayHobe-Nonce');
        if (!empty($nonce) && wp_verify_nonce($nonce, 'payhobe_payment_' . $payment_id)) {
            return true;
        }
        
        return new WP_Error('payhobe_unauthorized', __('Access denied.', 'payhobe'), array('status' => 401));
    }
    
    /**
     * Get payments list
     */
    public function get_payments($request) {
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        
        $args = array(
            'status' => $request->get_param('status') ?: '',
            'method' => $request->get_param('method') ?: '',
            'order_id' => $request->get_param('order_id') ?: '',
            'search' => $request->get_param('search') ?: '',
            'date_from' => $request->get_param('date_from') ?: '',
            'date_to' => $request->get_param('date_to') ?: '',
            'orderby' => $request->get_param('orderby'),
            'order' => strtoupper($request->get_param('order')),
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        );
        
        $payments = PayHobe_Database::get_payments($args);
        $total = PayHobe_Database::count_payments($args);
        
        // Format payments
        $formatted = array_map(array($this, 'format_payment'), $payments);
        
        return PayHobe_REST_API::success_response(array(
            'payments' => $formatted,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ));
    }
    
    /**
     * Get single payment
     */
    public function get_payment($request) {
        $payment_id = (int) $request->get_param('id');
        $payment = PayHobe_Database::get_payment($payment_id);
        
        if (!$payment) {
            return PayHobe_REST_API::error_response(
                'payhobe_not_found',
                __('Payment not found.', 'payhobe'),
                404
            );
        }
        
        return PayHobe_REST_API::success_response($this->format_payment($payment, true));
    }
    
    /**
     * Create new payment
     */
    public function create_payment($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        $required = array('payment_method', 'transaction_id', 'amount');
        $validation = PayHobe_REST_API::validate_required($required, $data);
        
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Validate payment method
        $valid_methods = array('bkash', 'rocket', 'nagad', 'upay', 'bank');
        if (!in_array($data['payment_method'], $valid_methods)) {
            return PayHobe_REST_API::error_response(
                'payhobe_invalid_method',
                __('Invalid payment method.', 'payhobe'),
                400
            );
        }
        
        // Validate transaction ID
        $trx_id = strtoupper(trim($data['transaction_id']));
        if (!PayHobe_REST_API::validate_transaction_id($trx_id, $data['payment_method'])) {
            return PayHobe_REST_API::error_response(
                'payhobe_invalid_trx_id',
                __('Invalid transaction ID format.', 'payhobe'),
                400
            );
        }
        
        // Check for duplicate transaction ID
        $existing = PayHobe_Database::find_payment_by_transaction_id($trx_id, $data['payment_method']);
        if ($existing) {
            return PayHobe_REST_API::error_response(
                'payhobe_duplicate_trx',
                __('This transaction ID has already been used.', 'payhobe'),
                400,
                array('existing_payment_id' => $existing->payment_id)
            );
        }
        
        // Validate sender number if provided
        $sender_number = null;
        if (!empty($data['sender_number'])) {
            $sender_number = PayHobe_REST_API::validate_phone($data['sender_number']);
            if (!$sender_number && $data['payment_method'] !== 'bank') {
                return PayHobe_REST_API::error_response(
                    'payhobe_invalid_phone',
                    __('Invalid sender phone number.', 'payhobe'),
                    400
                );
            }
        }
        
        // Prepare payment data
        $payment_data = array(
            'order_id' => isset($data['order_id']) ? absint($data['order_id']) : null,
            'user_id' => get_current_user_id() ?: null,
            'customer_email' => isset($data['customer_email']) ? sanitize_email($data['customer_email']) : null,
            'customer_phone' => isset($data['customer_phone']) ? sanitize_text_field($data['customer_phone']) : null,
            'payment_method' => $data['payment_method'],
            'transaction_id' => $trx_id,
            'sender_number' => $sender_number,
            'amount' => floatval($data['amount']),
            'payment_status' => 'pending'
        );
        
        // Insert payment
        $payment_id = PayHobe_Database::insert_payment($payment_data);
        
        if (!$payment_id) {
            return PayHobe_REST_API::error_response(
                'payhobe_insert_failed',
                __('Failed to create payment record.', 'payhobe'),
                500
            );
        }
        
        // Trigger auto verification
        do_action('payhobe_payment_submitted', $payment_id, $payment_data);
        
        // Get updated payment (may have been verified)
        $payment = PayHobe_Database::get_payment($payment_id);
        
        return PayHobe_REST_API::success_response(
            $this->format_payment($payment),
            __('Payment submitted successfully.', 'payhobe'),
            201
        );
    }
    
    /**
     * Update payment
     */
    public function update_payment($request) {
        $payment_id = (int) $request->get_param('id');
        $payment = PayHobe_Database::get_payment($payment_id);
        
        if (!$payment) {
            return PayHobe_REST_API::error_response(
                'payhobe_not_found',
                __('Payment not found.', 'payhobe'),
                404
            );
        }
        
        $update_data = array();
        
        // Status update
        if ($request->has_param('payment_status')) {
            $new_status = $request->get_param('payment_status');
            $valid_statuses = array('pending', 'confirmed', 'failed', 'refunded', 'cancelled');
            
            if (!in_array($new_status, $valid_statuses)) {
                return PayHobe_REST_API::error_response(
                    'payhobe_invalid_status',
                    __('Invalid payment status.', 'payhobe'),
                    400
                );
            }
            
            $update_data['payment_status'] = $new_status;
            
            if ($new_status === 'confirmed') {
                $update_data['verified_at'] = current_time('mysql');
                $update_data['verified_by'] = PayHobe_REST_API::authenticate_request($request);
                $update_data['verification_source'] = 'manual';
            }
        }
        
        // Notes
        if ($request->has_param('notes')) {
            $update_data['notes'] = sanitize_textarea_field($request->get_param('notes'));
        }
        
        if (empty($update_data)) {
            return PayHobe_REST_API::error_response(
                'payhobe_no_data',
                __('No data to update.', 'payhobe'),
                400
            );
        }
        
        if (PayHobe_Database::update_payment($payment_id, $update_data)) {
            $updated_payment = PayHobe_Database::get_payment($payment_id);
            return PayHobe_REST_API::success_response(
                $this->format_payment($updated_payment),
                __('Payment updated successfully.', 'payhobe')
            );
        }
        
        return PayHobe_REST_API::error_response(
            'payhobe_update_failed',
            __('Failed to update payment.', 'payhobe'),
            500
        );
    }
    
    /**
     * Verify/reject payment
     */
    public function verify_payment($request) {
        $payment_id = (int) $request->get_param('id');
        $action = $request->get_param('action');
        
        $payment = PayHobe_Database::get_payment($payment_id);
        
        if (!$payment) {
            return PayHobe_REST_API::error_response(
                'payhobe_not_found',
                __('Payment not found.', 'payhobe'),
                404
            );
        }
        
        if ($payment->payment_status !== 'pending') {
            return PayHobe_REST_API::error_response(
                'payhobe_already_processed',
                __('This payment has already been processed.', 'payhobe'),
                400
            );
        }
        
        $user_id = PayHobe_REST_API::authenticate_request($request);
        
        $update_data = array(
            'verified_by' => $user_id,
            'verified_at' => current_time('mysql'),
            'verification_source' => 'manual'
        );
        
        if ($action === 'confirm') {
            $update_data['payment_status'] = 'confirmed';
            
            // Update WooCommerce order if linked
            if ($payment->order_id && class_exists('WC_Order')) {
                $order = wc_get_order($payment->order_id);
                if ($order) {
                    $order->payment_complete($payment->transaction_id);
                    $order->add_order_note(sprintf(
                        __('PayHobe payment confirmed. Transaction ID: %s', 'payhobe'),
                        $payment->transaction_id
                    ));
                }
            }
        } else {
            $update_data['payment_status'] = 'failed';
            
            // Update WooCommerce order if linked
            if ($payment->order_id && class_exists('WC_Order')) {
                $order = wc_get_order($payment->order_id);
                if ($order) {
                    $order->update_status('failed', __('PayHobe payment was rejected.', 'payhobe'));
                }
            }
        }
        
        if (PayHobe_Database::update_payment($payment_id, $update_data)) {
            $updated_payment = PayHobe_Database::get_payment($payment_id);
            
            do_action('payhobe_payment_verified', $payment_id, $action, $user_id);
            
            return PayHobe_REST_API::success_response(
                $this->format_payment($updated_payment),
                $action === 'confirm' 
                    ? __('Payment confirmed successfully.', 'payhobe')
                    : __('Payment rejected.', 'payhobe')
            );
        }
        
        return PayHobe_REST_API::error_response(
            'payhobe_verify_failed',
            __('Failed to update payment.', 'payhobe'),
            500
        );
    }
    
    /**
     * Check payment status (public endpoint)
     */
    public function check_payment_status($request) {
        $order_id = $request->get_param('order_id');
        $transaction_id = strtoupper(trim($request->get_param('transaction_id') ?: ''));
        
        if (empty($order_id) && empty($transaction_id)) {
            return PayHobe_REST_API::error_response(
                'payhobe_missing_params',
                __('Please provide order ID or transaction ID.', 'payhobe'),
                400
            );
        }
        
        $payment = null;
        
        if (!empty($transaction_id)) {
            $payment = PayHobe_Database::find_payment_by_transaction_id($transaction_id);
        } elseif (!empty($order_id)) {
            $payments = PayHobe_Database::get_payments(array(
                'order_id' => $order_id,
                'limit' => 1,
                'orderby' => 'created_at',
                'order' => 'DESC'
            ));
            $payment = !empty($payments) ? $payments[0] : null;
        }
        
        if (!$payment) {
            return PayHobe_REST_API::error_response(
                'payhobe_not_found',
                __('Payment not found.', 'payhobe'),
                404
            );
        }
        
        // Return limited info for public endpoint
        return PayHobe_REST_API::success_response(array(
            'payment_id' => $payment->payment_id,
            'status' => $payment->payment_status,
            'status_label' => $this->get_status_label($payment->payment_status),
            'method' => $payment->payment_method,
            'amount' => $payment->amount,
            'verified_at' => $payment->verified_at
        ));
    }
    
    /**
     * Upload payment screenshot
     */
    public function upload_screenshot($request) {
        $payment_id = (int) $request->get_param('id');
        $payment = PayHobe_Database::get_payment($payment_id);
        
        if (!$payment) {
            return PayHobe_REST_API::error_response(
                'payhobe_not_found',
                __('Payment not found.', 'payhobe'),
                404
            );
        }
        
        if ($payment->payment_method !== 'bank') {
            return PayHobe_REST_API::error_response(
                'payhobe_not_bank',
                __('Screenshots are only for bank transfers.', 'payhobe'),
                400
            );
        }
        
        if (empty($_FILES['screenshot'])) {
            return PayHobe_REST_API::error_response(
                'payhobe_no_file',
                __('No file uploaded.', 'payhobe'),
                400
            );
        }
        
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        // Set upload directory
        add_filter('upload_dir', array($this, 'custom_upload_dir'));
        
        $attachment_id = media_handle_upload('screenshot', 0);
        
        remove_filter('upload_dir', array($this, 'custom_upload_dir'));
        
        if (is_wp_error($attachment_id)) {
            return PayHobe_REST_API::error_response(
                'payhobe_upload_failed',
                $attachment_id->get_error_message(),
                500
            );
        }
        
        // Update payment with screenshot
        PayHobe_Database::update_payment($payment_id, array(
            'payment_screenshot' => $attachment_id
        ));
        
        return PayHobe_REST_API::success_response(array(
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id)
        ), __('Screenshot uploaded successfully.', 'payhobe'));
    }
    
    /**
     * Custom upload directory for screenshots
     */
    public function custom_upload_dir($dirs) {
        $dirs['subdir'] = '/payhobe/screenshots/' . date('Y/m');
        $dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
        $dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];
        return $dirs;
    }
    
    /**
     * Export payments
     */
    public function export_payments($request) {
        $args = array(
            'status' => $request->get_param('status') ?: '',
            'method' => $request->get_param('method') ?: '',
            'date_from' => $request->get_param('date_from') ?: '',
            'date_to' => $request->get_param('date_to') ?: '',
            'limit' => 10000, // Max export
            'offset' => 0
        );
        
        $payments = PayHobe_Database::get_payments($args);
        
        $export_data = array();
        foreach ($payments as $payment) {
            $export_data[] = array(
                'Payment ID' => $payment->payment_id,
                'Order ID' => $payment->order_id,
                'Method' => strtoupper($payment->payment_method),
                'Transaction ID' => $payment->transaction_id,
                'Sender' => $payment->sender_number ? PayHobe_Encryption::mask_phone($payment->sender_number) : '',
                'Amount' => $payment->amount,
                'Status' => $payment->payment_status,
                'Customer Email' => $payment->customer_email,
                'Verified At' => $payment->verified_at,
                'Created At' => $payment->created_at
            );
        }
        
        return PayHobe_REST_API::success_response(array(
            'data' => $export_data,
            'count' => count($export_data),
            'exported_at' => current_time('mysql')
        ));
    }
    
    /**
     * Get payment statistics
     */
    public function get_payment_stats($request) {
        $args = array(
            'date_from' => $request->get_param('date_from') ?: '',
            'date_to' => $request->get_param('date_to') ?: ''
        );
        
        $stats = PayHobe_Database::get_payment_stats($args);
        
        return PayHobe_REST_API::success_response($stats);
    }
    
    /**
     * Format payment for response
     */
    private function format_payment($payment, $detailed = false) {
        $data = array(
            'id' => $payment->payment_id,
            'order_id' => $payment->order_id,
            'method' => $payment->payment_method,
            'method_label' => $this->get_method_label($payment->payment_method),
            'transaction_id' => $payment->transaction_id,
            'sender_number' => $payment->sender_number 
                ? PayHobe_Encryption::mask_phone($payment->sender_number) 
                : null,
            'amount' => floatval($payment->amount),
            'currency' => $payment->currency ?: 'BDT',
            'status' => $payment->payment_status,
            'status_label' => $this->get_status_label($payment->payment_status),
            'verification_source' => $payment->verification_source,
            'verified_at' => $payment->verified_at,
            'created_at' => $payment->created_at
        );
        
        if ($detailed) {
            $data['customer_email'] = $payment->customer_email;
            $data['customer_phone'] = $payment->customer_phone;
            $data['notes'] = $payment->notes;
            $data['screenshot_url'] = $payment->payment_screenshot 
                ? wp_get_attachment_url($payment->payment_screenshot) 
                : null;
            
            // Get order details if available
            if ($payment->order_id && class_exists('WC_Order')) {
                $order = wc_get_order($payment->order_id);
                if ($order) {
                    $data['order'] = array(
                        'id' => $order->get_id(),
                        'status' => $order->get_status(),
                        'total' => $order->get_total(),
                        'billing_email' => $order->get_billing_email(),
                        'billing_phone' => $order->get_billing_phone()
                    );
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Get method label
     */
    private function get_method_label($method) {
        $labels = array(
            'bkash' => 'bKash',
            'rocket' => 'Rocket',
            'nagad' => 'Nagad',
            'upay' => 'Upay',
            'bank' => 'Bank Transfer'
        );
        return isset($labels[$method]) ? $labels[$method] : ucfirst($method);
    }
    
    /**
     * Get status label
     */
    private function get_status_label($status) {
        $labels = array(
            'pending' => __('Pending', 'payhobe'),
            'confirmed' => __('Confirmed', 'payhobe'),
            'failed' => __('Failed', 'payhobe'),
            'refunded' => __('Refunded', 'payhobe'),
            'cancelled' => __('Cancelled', 'payhobe')
        );
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }
}
