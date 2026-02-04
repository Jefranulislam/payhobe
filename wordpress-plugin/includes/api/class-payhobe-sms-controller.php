<?php
/**
 * PayHobe SMS Controller
 *
 * Handles SMS-related API endpoints
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMS Controller class
 */
class PayHobe_SMS_Controller {
    
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
        // Receive SMS webhook (from Android forwarder or Twilio)
        register_rest_route($this->namespace, '/sms/receive', array(
            'methods' => 'POST',
            'callback' => array($this, 'receive_sms'),
            'permission_callback' => array($this, 'verify_webhook'),
            'args' => array(
                'sender' => array('required' => true, 'type' => 'string'),
                'message' => array('required' => true, 'type' => 'string'),
                'received_at' => array('type' => 'string'),
                'source' => array('type' => 'string', 'default' => 'api')
            )
        ));
        
        // Twilio webhook
        register_rest_route($this->namespace, '/sms/twilio', array(
            'methods' => 'POST',
            'callback' => array($this, 'receive_twilio_sms'),
            'permission_callback' => array($this, 'verify_twilio_webhook')
        ));
        
        // Manual SMS entry
        register_rest_route($this->namespace, '/sms/manual', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_manual_sms'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'message' => array('required' => true, 'type' => 'string'),
                'sender' => array('type' => 'string'),
                'received_at' => array('type' => 'string')
            )
        ));
        
        // Get SMS logs
        register_rest_route($this->namespace, '/sms/logs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sms_logs'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'page' => array('type' => 'integer', 'default' => 1),
                'per_page' => array('type' => 'integer', 'default' => 50),
                'processed' => array('type' => 'string', 'enum' => array('', 'yes', 'no')),
                'method' => array('type' => 'string')
            )
        ));
        
        // Get single SMS log
        register_rest_route($this->namespace, '/sms/logs/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sms_log'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Delete SMS log
        register_rest_route($this->namespace, '/sms/logs/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_sms_log'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Process unmatched SMS
        register_rest_route($this->namespace, '/sms/process', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_unmatched'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Get webhook URL and secret for setup
        register_rest_route($this->namespace, '/sms/webhook-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_webhook_info'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Regenerate webhook secret
        register_rest_route($this->namespace, '/sms/regenerate-secret', array(
            'methods' => 'POST',
            'callback' => array($this, 'regenerate_webhook_secret'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
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
     * Verify webhook signature
     */
    public function verify_webhook($request) {
        // Get webhook secret from header
        $signature = $request->get_header('X-PayHobe-Signature');
        $api_key = $request->get_header('X-PayHobe-Key');
        $secret = get_option('payhobe_sms_webhook_secret');
        
        if (empty($secret)) {
            // Generate new secret if none exists
            $secret = PayHobe_Encryption::generate_token(32);
            update_option('payhobe_sms_webhook_secret', $secret);
        }
        
        // Option 1: Check API key (simpler for Android apps)
        if (!empty($api_key)) {
            if (hash_equals($secret, $api_key)) {
                return true;
            }
        }
        
        // Option 2: Check query parameter (for apps that can't set headers)
        $query_key = $request->get_param('key');
        if (!empty($query_key)) {
            if (hash_equals($secret, $query_key)) {
                return true;
            }
        }
        
        // Option 3: Full HMAC signature verification
        if (!empty($signature)) {
            $body = $request->get_body();
            $expected = hash_hmac('sha256', $body, $secret);
            
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        
        // Allow localhost for development
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (in_array($ip, array('127.0.0.1', '::1'))) {
            return true;
        }
        
        return new WP_Error(
            'payhobe_invalid_signature',
            __('Invalid or missing webhook authentication. Use X-PayHobe-Key header or ?key= parameter with your webhook secret.', 'payhobe'),
            array('status' => 401)
        );
    }
    
    /**
     * Verify Twilio webhook
     */
    public function verify_twilio_webhook($request) {
        $twilio_auth_token = get_option('payhobe_twilio_auth_token');
        
        if (empty($twilio_auth_token)) {
            return new WP_Error(
                'payhobe_twilio_not_configured',
                __('Twilio is not configured.', 'payhobe'),
                array('status' => 400)
            );
        }
        
        // Get Twilio signature
        $signature = $request->get_header('X-Twilio-Signature');
        
        if (empty($signature)) {
            return new WP_Error(
                'payhobe_invalid_signature',
                __('Missing Twilio signature.', 'payhobe'),
                array('status' => 401)
            );
        }
        
        // Verify Twilio signature
        $url = PayHobe_REST_API::get_api_url('sms/twilio');
        $params = $request->get_body_params();
        ksort($params);
        
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }
        
        $expected = base64_encode(hash_hmac('sha1', $data, $twilio_auth_token, true));
        
        if (!hash_equals($expected, $signature)) {
            return new WP_Error(
                'payhobe_invalid_signature',
                __('Invalid Twilio signature.', 'payhobe'),
                array('status' => 401)
            );
        }
        
        return true;
    }
    
    /**
     * Receive SMS from webhook
     */
    public function receive_sms($request) {
        $sender = sanitize_text_field($request->get_param('sender'));
        $message = sanitize_textarea_field($request->get_param('message'));
        $received_at = $request->get_param('received_at') ?: current_time('mysql');
        $source = $request->get_param('source') ?: 'android_forwarder';
        
        return $this->process_incoming_sms($sender, $message, $received_at, $source, $request->get_body());
    }
    
    /**
     * Receive SMS from Twilio
     */
    public function receive_twilio_sms($request) {
        $params = $request->get_body_params();
        
        $sender = isset($params['From']) ? $params['From'] : '';
        $message = isset($params['Body']) ? $params['Body'] : '';
        $received_at = current_time('mysql');
        
        return $this->process_incoming_sms($sender, $message, $received_at, 'twilio', json_encode($params));
    }
    
    /**
     * Add manual SMS entry
     */
    public function add_manual_sms($request) {
        $message = sanitize_textarea_field($request->get_param('message'));
        $sender = sanitize_text_field($request->get_param('sender') ?: 'manual_entry');
        $received_at = $request->get_param('received_at') ?: current_time('mysql');
        
        return $this->process_incoming_sms($sender, $message, $received_at, 'manual');
    }
    
    /**
     * Process incoming SMS
     */
    private function process_incoming_sms($sender, $message, $received_at, $source, $raw_data = null) {
        $merchant_id = get_option('payhobe_merchant_user_id');
        
        if (!$merchant_id) {
            return PayHobe_REST_API::error_response(
                'payhobe_no_merchant',
                __('Merchant not configured.', 'payhobe'),
                400
            );
        }
        
        // Parse SMS
        $parser = new PayHobe_SMS_Parser();
        $parsed = $parser->parse($message);
        
        // Insert SMS log
        $sms_data = array(
            'user_id' => $merchant_id,
            'sender_number' => $sender,
            'message_body' => $message,
            'parsed_transaction_id' => $parsed['transaction_id'],
            'parsed_amount' => $parsed['amount'],
            'parsed_sender' => $parsed['sender_number'],
            'payment_method' => $parsed['method'],
            'source' => $source,
            'received_at' => $received_at,
            'raw_data' => $raw_data
        );
        
        $sms_id = PayHobe_Database::insert_sms_log($sms_data);
        
        if (!$sms_id) {
            return PayHobe_REST_API::error_response(
                'payhobe_insert_failed',
                __('Failed to save SMS.', 'payhobe'),
                500
            );
        }
        
        // Try to match with pending payments
        $matched = false;
        if (!empty($parsed['transaction_id'])) {
            $verification = new PayHobe_Verification();
            $matched = $verification->try_match_sms($sms_id, $parsed);
        }
        
        return PayHobe_REST_API::success_response(array(
            'sms_id' => $sms_id,
            'parsed' => $parsed,
            'matched' => $matched
        ), __('SMS received and processed.', 'payhobe'), 201);
    }
    
    /**
     * Get SMS logs
     */
    public function get_sms_logs($request) {
        $user_id = PayHobe_REST_API::authenticate_request($request);
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $processed = $request->get_param('processed');
        $method = $request->get_param('method');
        
        $args = array(
            'user_id' => get_option('payhobe_merchant_user_id'),
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        );
        
        if ($processed === 'yes') {
            $args['is_processed'] = 1;
        } elseif ($processed === 'no') {
            $args['is_processed'] = 0;
        }
        
        if (!empty($method)) {
            $args['payment_method'] = $method;
        }
        
        $logs = PayHobe_Database::get_sms_logs($args);
        
        // Format logs
        $formatted = array_map(function($log) {
            return array(
                'id' => $log->sms_id,
                'sender' => $log->sender_number,
                'message_preview' => mb_substr($log->message_body, 0, 100) . (mb_strlen($log->message_body) > 100 ? '...' : ''),
                'parsed_trx_id' => $log->parsed_transaction_id,
                'parsed_amount' => $log->parsed_amount,
                'method' => $log->payment_method,
                'is_processed' => (bool) $log->is_processed,
                'matched_payment_id' => $log->matched_payment_id,
                'source' => $log->source,
                'received_at' => $log->received_at
            );
        }, $logs);
        
        return PayHobe_REST_API::success_response(array(
            'logs' => $formatted,
            'page' => $page,
            'per_page' => $per_page
        ));
    }
    
    /**
     * Get single SMS log
     */
    public function get_sms_log($request) {
        global $wpdb;
        
        $sms_id = (int) $request->get_param('id');
        $table = PayHobe_Database::get_table_name('sms_logs');
        
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE sms_id = %d",
            $sms_id
        ));
        
        if (!$log) {
            return PayHobe_REST_API::error_response(
                'payhobe_not_found',
                __('SMS log not found.', 'payhobe'),
                404
            );
        }
        
        // Decrypt message
        if (!empty($log->message_body)) {
            $log->message_body = PayHobe_Encryption::decrypt($log->message_body);
        }
        
        return PayHobe_REST_API::success_response(array(
            'id' => $log->sms_id,
            'sender' => $log->sender_number,
            'message' => $log->message_body,
            'parsed_trx_id' => $log->parsed_transaction_id,
            'parsed_amount' => $log->parsed_amount,
            'parsed_sender' => $log->parsed_sender,
            'method' => $log->payment_method,
            'is_processed' => (bool) $log->is_processed,
            'matched_payment_id' => $log->matched_payment_id,
            'source' => $log->source,
            'received_at' => $log->received_at,
            'processed_at' => $log->processed_at
        ));
    }
    
    /**
     * Delete SMS log
     */
    public function delete_sms_log($request) {
        global $wpdb;
        
        $sms_id = (int) $request->get_param('id');
        $table = PayHobe_Database::get_table_name('sms_logs');
        
        $result = $wpdb->delete($table, array('sms_id' => $sms_id));
        
        if ($result) {
            return PayHobe_REST_API::success_response(null, __('SMS log deleted.', 'payhobe'));
        }
        
        return PayHobe_REST_API::error_response(
            'payhobe_delete_failed',
            __('Failed to delete SMS log.', 'payhobe'),
            500
        );
    }
    
    /**
     * Process unmatched SMS logs
     */
    public function process_unmatched($request) {
        $merchant_id = get_option('payhobe_merchant_user_id');
        $logs = PayHobe_Database::get_unmatched_sms_logs($merchant_id, 1440); // Last 24 hours
        
        $verification = new PayHobe_Verification();
        $matched_count = 0;
        
        foreach ($logs as $log) {
            // Decrypt message for parsing
            $message = PayHobe_Encryption::decrypt($log->message_body);
            
            $parser = new PayHobe_SMS_Parser();
            $parsed = $parser->parse($message);
            
            if (!empty($parsed['transaction_id'])) {
                if ($verification->try_match_sms($log->sms_id, $parsed)) {
                    $matched_count++;
                }
            }
        }
        
        return PayHobe_REST_API::success_response(array(
            'processed' => count($logs),
            'matched' => $matched_count
        ), sprintf(__('Processed %d SMS logs, matched %d.', 'payhobe'), count($logs), $matched_count));
    }
    
    /**
     * Get webhook info for setup
     */
    public function get_webhook_info($request) {
        $secret = get_option('payhobe_sms_webhook_secret');
        
        if (empty($secret)) {
            $secret = PayHobe_Encryption::generate_token(32);
            update_option('payhobe_sms_webhook_secret', $secret);
        }
        
        return PayHobe_REST_API::success_response(array(
            'webhook_url' => PayHobe_REST_API::get_api_url('sms/receive'),
            'webhook_secret' => $secret,
            'twilio_webhook_url' => PayHobe_REST_API::get_api_url('sms/twilio'),
            'instructions' => array(
                'android' => array(
                    'title' => __('Android SMS Forwarder Setup', 'payhobe'),
                    'steps' => array(
                        __('Download SMS Forwarder app from Play Store', 'payhobe'),
                        __('Add a new forwarding rule for MFS SMS', 'payhobe'),
                        __('Set the webhook URL above as the destination', 'payhobe'),
                        __('Add header: X-PayHobe-Signature with HMAC-SHA256 signature', 'payhobe'),
                        __('Test the forwarding with a sample SMS', 'payhobe')
                    )
                ),
                'twilio' => array(
                    'title' => __('Twilio Setup', 'payhobe'),
                    'steps' => array(
                        __('Get a Twilio phone number', 'payhobe'),
                        __('Set the Twilio webhook URL as the SMS webhook', 'payhobe'),
                        __('Forward your MFS SMS to this Twilio number', 'payhobe')
                    )
                )
            )
        ));
    }
    
    /**
     * Regenerate webhook secret
     */
    public function regenerate_webhook_secret($request) {
        $secret = PayHobe_Encryption::generate_token(32);
        update_option('payhobe_sms_webhook_secret', $secret);
        
        return PayHobe_REST_API::success_response(array(
            'webhook_secret' => $secret
        ), __('Webhook secret regenerated.', 'payhobe'));
    }
}
