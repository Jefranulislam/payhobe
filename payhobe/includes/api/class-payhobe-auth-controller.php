<?php
/**
 * PayHobe Auth Controller
 *
 * Handles authentication endpoints
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auth Controller class
 */
class PayHobe_Auth_Controller {
    
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
        // Login endpoint
        register_rest_route($this->namespace, '/auth/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login'),
            'permission_callback' => '__return_true',
            'args' => array(
                'username' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_user'
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));
        
        // Token generation
        register_rest_route($this->namespace, '/auth/token', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_token'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'name' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'expires_days' => array(
                    'type' => 'integer',
                    'default' => 0
                )
            )
        ));
        
        // List tokens
        register_rest_route($this->namespace, '/auth/tokens', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_tokens'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Revoke token
        register_rest_route($this->namespace, '/auth/tokens/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'revoke_token'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        // Verify token / Get current user
        register_rest_route($this->namespace, '/auth/me', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_current_user'),
            'permission_callback' => array($this, 'check_auth')
        ));
        
        // Logout (revoke current token)
        register_rest_route($this->namespace, '/auth/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'logout'),
            'permission_callback' => array($this, 'check_auth')
        ));
    }
    
    /**
     * Check basic authentication
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_auth($request) {
        $result = PayHobe_REST_API::authenticate_request($request);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return true;
    }
    
    /**
     * Check merchant permission
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_merchant_permission($request) {
        $user_id = PayHobe_REST_API::authenticate_request($request);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        if (!PayHobe_REST_API::is_merchant($user_id)) {
            return new WP_Error(
                'payhobe_forbidden',
                __('You do not have permission to access this resource.', 'payhobe'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Handle login request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function login($request) {
        $username = $request->get_param('username');
        $password = $request->get_param('password');
        
        // Authenticate user
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            return PayHobe_REST_API::error_response(
                'payhobe_invalid_credentials',
                __('Invalid username or password.', 'payhobe'),
                401
            );
        }
        
        // Check if user is merchant
        if (!PayHobe_REST_API::is_merchant($user->ID)) {
            return PayHobe_REST_API::error_response(
                'payhobe_not_merchant',
                __('You do not have merchant access.', 'payhobe'),
                403
            );
        }
        
        // Generate API token
        $token_data = PayHobe_Database::create_api_token(
            $user->ID,
            'Dashboard Login - ' . date('Y-m-d H:i'),
            array('all'),
            30 // 30 days expiry
        );
        
        if (!$token_data) {
            return PayHobe_REST_API::error_response(
                'payhobe_token_error',
                __('Failed to generate access token.', 'payhobe'),
                500
            );
        }
        
        return PayHobe_REST_API::success_response(array(
            'token' => $token_data['token'],
            'expires_at' => $token_data['expires_at'],
            'user' => array(
                'id' => $user->ID,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'is_merchant' => true
            )
        ), __('Login successful.', 'payhobe'));
    }
    
    /**
     * Generate new API token
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function generate_token($request) {
        $user_id = PayHobe_REST_API::authenticate_request($request);
        $name = $request->get_param('name') ?: 'API Token - ' . date('Y-m-d H:i');
        $expires_days = (int) $request->get_param('expires_days');
        
        $token_data = PayHobe_Database::create_api_token(
            $user_id,
            $name,
            array('all'),
            $expires_days
        );
        
        if (!$token_data) {
            return PayHobe_REST_API::error_response(
                'payhobe_token_error',
                __('Failed to generate access token.', 'payhobe'),
                500
            );
        }
        
        return PayHobe_REST_API::success_response(array(
            'token' => $token_data['token'],
            'expires_at' => $token_data['expires_at'],
            'message' => __('Save this token securely. It will not be shown again.', 'payhobe')
        ), __('Token generated successfully.', 'payhobe'), 201);
    }
    
    /**
     * List user's API tokens
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_tokens($request) {
        global $wpdb;
        
        $user_id = PayHobe_REST_API::authenticate_request($request);
        $table = PayHobe_Database::get_table_name('api_tokens');
        
        $tokens = $wpdb->get_results($wpdb->prepare(
            "SELECT token_id, token_name, last_used_at, expires_at, is_revoked, created_at 
             FROM $table 
             WHERE user_id = %d 
             ORDER BY created_at DESC",
            $user_id
        ));
        
        return PayHobe_REST_API::success_response($tokens);
    }
    
    /**
     * Revoke a token
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function revoke_token($request) {
        global $wpdb;
        
        $user_id = PayHobe_REST_API::authenticate_request($request);
        $token_id = (int) $request->get_param('id');
        
        $table = PayHobe_Database::get_table_name('api_tokens');
        
        // Verify token belongs to user
        $token = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token_id = %d AND user_id = %d",
            $token_id,
            $user_id
        ));
        
        if (!$token) {
            return PayHobe_REST_API::error_response(
                'payhobe_token_not_found',
                __('Token not found.', 'payhobe'),
                404
            );
        }
        
        if (PayHobe_Database::revoke_api_token($token_id)) {
            return PayHobe_REST_API::success_response(null, __('Token revoked successfully.', 'payhobe'));
        }
        
        return PayHobe_REST_API::error_response(
            'payhobe_revoke_failed',
            __('Failed to revoke token.', 'payhobe'),
            500
        );
    }
    
    /**
     * Get current authenticated user
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_current_user($request) {
        $user_id = PayHobe_REST_API::authenticate_request($request);
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return PayHobe_REST_API::error_response(
                'payhobe_user_not_found',
                __('User not found.', 'payhobe'),
                404
            );
        }
        
        // Get merchant config
        $configs = PayHobe_Database::get_mfs_config($user_id);
        $enabled_methods = array();
        
        foreach ($configs as $config) {
            if ($config->is_enabled) {
                $enabled_methods[] = $config->method;
            }
        }
        
        return PayHobe_REST_API::success_response(array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'is_merchant' => PayHobe_REST_API::is_merchant($user_id),
            'enabled_methods' => $enabled_methods,
            'onboarding_complete' => !get_option('payhobe_needs_onboarding', true),
            'site_url' => get_site_url(),
            'api_url' => PayHobe_REST_API::get_api_url()
        ));
    }
    
    /**
     * Logout (invalidate current token if using token auth)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function logout($request) {
        global $wpdb;
        
        // Get token from request
        $token = $request->get_header('X-PayHobe-Token');
        
        if (empty($token)) {
            $auth_header = $request->get_header('Authorization');
            if (!empty($auth_header) && strpos($auth_header, 'Bearer ') === 0) {
                $token = substr($auth_header, 7);
            }
        }
        
        if (!empty($token)) {
            $token_hash = PayHobe_Encryption::hash($token);
            $table = PayHobe_Database::get_table_name('api_tokens');
            
            $wpdb->update(
                $table,
                array('is_revoked' => 1),
                array('token_hash' => $token_hash)
            );
        }
        
        return PayHobe_REST_API::success_response(null, __('Logged out successfully.', 'payhobe'));
    }
}
