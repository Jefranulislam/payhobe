<?php
/**
 * PayHobe REST API Handler
 *
 * Main REST API initialization and routing
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API class
 */
class PayHobe_REST_API {
    
    /**
     * API namespace
     */
    const API_NAMESPACE = 'payhobe/v1';
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Current authenticated user
     */
    private $current_user = null;
    
    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Register routes - this is called during rest_api_init
        $this->register_routes();
        
        // Add CORS headers
        $this->add_cors_headers();
    }
    
    /**
     * Register all routes
     */
    private function register_routes() {
        // Auth endpoints
        $auth_controller = new PayHobe_Auth_Controller();
        $auth_controller->register_routes();
        
        // Payments endpoints
        $payments_controller = new PayHobe_Payments_Controller();
        $payments_controller->register_routes();
        
        // SMS endpoints
        $sms_controller = new PayHobe_SMS_Controller();
        $sms_controller->register_routes();
        
        // Config endpoints
        $config_controller = new PayHobe_Config_Controller();
        $config_controller->register_routes();
        
        // Dashboard endpoints
        $dashboard_controller = new PayHobe_Dashboard_Controller();
        $dashboard_controller->register_routes();
        
        // Register a simple test route to verify API is working
        register_rest_route(self::API_NAMESPACE, '/ping', array(
            'methods' => 'GET',
            'callback' => function() {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'PayHobe API is working',
                    'version' => PAYHOBE_VERSION,
                    'timestamp' => current_time('mysql')
                ), 200);
            },
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Add CORS headers for allowed origins
     */
    private function add_cors_headers() {
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', array($this, 'send_cors_headers'));
        }, 15);
    }
    
    /**
     * Send CORS headers
     *
     * @param mixed $value Pre-serve value
     * @return mixed
     */
    public function send_cors_headers($value) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        $allowed_origins = get_option('payhobe_api_cors_origins', array());
        
        // Always allow localhost for development
        $allowed_origins[] = 'http://localhost:3000';
        $allowed_origins[] = 'http://localhost:3001';
        
        // Add dashboard URL if set
        $dashboard_url = get_option('payhobe_dashboard_url', '');
        if (!empty($dashboard_url)) {
            $allowed_origins[] = rtrim($dashboard_url, '/');
        }
        
        if (in_array($origin, $allowed_origins, true)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-PayHobe-Token');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
        
        return $value;
    }
    
    /**
     * Get API namespace
     *
     * @return string
     */
    public static function get_namespace() {
        return self::API_NAMESPACE;
    }
    
    /**
     * Get API URL
     *
     * @param string $endpoint Endpoint path
     * @return string Full URL
     */
    public static function get_api_url($endpoint = '') {
        return rest_url(self::API_NAMESPACE . '/' . ltrim($endpoint, '/'));
    }
    
    /**
     * Authenticate API request
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if authenticated, error otherwise
     */
    public static function authenticate_request($request) {
        // Check for API token header
        $token = $request->get_header('X-PayHobe-Token');
        
        // Also check Authorization header (Bearer token)
        if (empty($token)) {
            $auth_header = $request->get_header('Authorization');
            if (!empty($auth_header) && strpos($auth_header, 'Bearer ') === 0) {
                $token = substr($auth_header, 7);
            }
        }
        
        if (!empty($token)) {
            $token_data = PayHobe_Database::validate_api_token($token);
            
            if ($token_data) {
                return $token_data->user_id;
            }
            
            return new WP_Error(
                'payhobe_invalid_token',
                __('Invalid or expired API token.', 'payhobe'),
                array('status' => 401)
            );
        }
        
        // Fall back to WordPress authentication
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $merchant_id = get_option('payhobe_merchant_user_id');
            
            if ($user_id == $merchant_id || current_user_can('manage_options')) {
                return $user_id;
            }
        }
        
        return new WP_Error(
            'payhobe_unauthorized',
            __('Authentication required.', 'payhobe'),
            array('status' => 401)
        );
    }
    
    /**
     * Check if user is merchant
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function is_merchant($user_id) {
        $merchant_id = get_option('payhobe_merchant_user_id');
        return $user_id == $merchant_id || user_can($user_id, 'manage_options');
    }
    
    /**
     * Standard success response
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $status HTTP status code
     * @return WP_REST_Response
     */
    public static function success_response($data = null, $message = '', $status = 200) {
        $response = array(
            'success' => true
        );
        
        if (!empty($message)) {
            $response['message'] = $message;
        }
        
        if (!is_null($data)) {
            $response['data'] = $data;
        }
        
        return new WP_REST_Response($response, $status);
    }
    
    /**
     * Standard error response
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array $additional Additional data
     * @return WP_Error
     */
    public static function error_response($code, $message, $status = 400, $additional = array()) {
        return new WP_Error($code, $message, array_merge(
            array('status' => $status),
            $additional
        ));
    }
    
    /**
     * Validate required fields
     *
     * @param array $required Required field names
     * @param array $data Data to check
     * @return true|WP_Error True if valid, error otherwise
     */
    public static function validate_required($required, $data) {
        $missing = array();
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            return self::error_response(
                'payhobe_missing_fields',
                sprintf(__('Missing required fields: %s', 'payhobe'), implode(', ', $missing)),
                400
            );
        }
        
        return true;
    }
    
    /**
     * Sanitize and validate phone number (Bangladesh format)
     *
     * @param string $phone Phone number
     * @return string|false Formatted phone or false
     */
    public static function validate_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle country code
        if (strlen($phone) === 13 && strpos($phone, '880') === 0) {
            $phone = '0' . substr($phone, 3);
        } elseif (strlen($phone) === 10 && strpos($phone, '1') === 0) {
            $phone = '0' . $phone;
        }
        
        // Validate Bangladesh mobile number format
        if (preg_match('/^01[3-9][0-9]{8}$/', $phone)) {
            return $phone;
        }
        
        return false;
    }
    
    /**
     * Validate transaction ID format by method
     *
     * @param string $trx_id Transaction ID
     * @param string $method Payment method
     * @return bool
     */
    public static function validate_transaction_id($trx_id, $method) {
        $trx_id = strtoupper(trim($trx_id));
        
        switch ($method) {
            case 'bkash':
                // bKash: Alphanumeric, usually 8-12 characters
                return preg_match('/^[A-Z0-9]{6,15}$/', $trx_id);
                
            case 'nagad':
                // Nagad: Usually starts with letters and alphanumeric
                return preg_match('/^[A-Z0-9]{8,20}$/', $trx_id);
                
            case 'rocket':
                // Rocket: Numeric, typically 10-15 digits
                return preg_match('/^[0-9]{8,20}$/', $trx_id);
                
            case 'upay':
                // Upay: Alphanumeric
                return preg_match('/^[A-Z0-9]{6,20}$/', $trx_id);
                
            case 'bank':
                // Bank transfers: Various formats
                return preg_match('/^[A-Z0-9\-]{6,30}$/', $trx_id);
                
            default:
                return strlen($trx_id) >= 6 && strlen($trx_id) <= 30;
        }
    }
}
