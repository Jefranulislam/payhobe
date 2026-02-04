<?php
/**
 * PayHobe Config Controller
 *
 * Handles configuration-related API endpoints
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Config Controller class
 */
class PayHobe_Config_Controller {
    
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
        // Get all MFS configurations
        register_rest_route($this->namespace, '/config/mfs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_mfs_configs'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Get single MFS configuration
        register_rest_route($this->namespace, '/config/mfs/(?P<method>[a-z]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_mfs_config'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'method' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('bkash', 'rocket', 'nagad', 'upay', 'bank')
                )
            )
        ));
        
        // Update MFS configuration
        register_rest_route($this->namespace, '/config/mfs/(?P<method>[a-z]+)', array(
            'methods' => 'PUT,PATCH',
            'callback' => array($this, 'update_mfs_config'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'method' => array('required' => true, 'type' => 'string'),
                'is_enabled' => array('type' => 'boolean'),
                'account_type' => array('type' => 'string'),
                'account_number' => array('type' => 'string'),
                'account_name' => array('type' => 'string'),
                'bank_name' => array('type' => 'string'),
                'branch_name' => array('type' => 'string'),
                'routing_number' => array('type' => 'string'),
                'instructions_en' => array('type' => 'string'),
                'instructions_bn' => array('type' => 'string'),
                'sms_parser_enabled' => array('type' => 'boolean')
            )
        ));
        
        // Get general settings
        register_rest_route($this->namespace, '/config/settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_settings'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Update general settings
        register_rest_route($this->namespace, '/config/settings', array(
            'methods' => 'PUT,PATCH',
            'callback' => array($this, 'update_settings'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Complete onboarding
        register_rest_route($this->namespace, '/config/onboarding/complete', array(
            'methods' => 'POST',
            'callback' => array($this, 'complete_onboarding'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Test configuration
        register_rest_route($this->namespace, '/config/test', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_configuration'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Get available methods (public endpoint for checkout)
        register_rest_route($this->namespace, '/config/available-methods', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_available_methods'),
            'permission_callback' => '__return_true'
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
     * Get all MFS configurations
     */
    public function get_mfs_configs($request) {
        $merchant_id = get_option('payhobe_merchant_user_id');
        $configs = PayHobe_Database::get_mfs_config($merchant_id);
        
        // Prepare response with all methods
        $methods = array('bkash', 'rocket', 'nagad', 'upay', 'bank');
        $result = array();
        
        foreach ($methods as $method) {
            $config = null;
            foreach ($configs as $c) {
                if ($c->method === $method) {
                    $config = $c;
                    break;
                }
            }
            
            $result[$method] = $this->format_config($method, $config);
        }
        
        return PayHobe_REST_API::success_response($result);
    }
    
    /**
     * Get single MFS configuration
     */
    public function get_mfs_config($request) {
        $method = $request->get_param('method');
        $merchant_id = get_option('payhobe_merchant_user_id');
        
        $config = PayHobe_Database::get_mfs_config($merchant_id, $method);
        
        return PayHobe_REST_API::success_response($this->format_config($method, $config));
    }
    
    /**
     * Update MFS configuration
     */
    public function update_mfs_config($request) {
        $method = $request->get_param('method');
        $merchant_id = get_option('payhobe_merchant_user_id');
        
        $valid_methods = array('bkash', 'rocket', 'nagad', 'upay', 'bank');
        if (!in_array($method, $valid_methods)) {
            return PayHobe_REST_API::error_response(
                'payhobe_invalid_method',
                __('Invalid payment method.', 'payhobe'),
                400
            );
        }
        
        $data = array();
        
        // Boolean fields
        if ($request->has_param('is_enabled')) {
            $data['is_enabled'] = (int) $request->get_param('is_enabled');
        }
        
        if ($request->has_param('sms_parser_enabled')) {
            $data['sms_parser_enabled'] = (int) $request->get_param('sms_parser_enabled');
        }
        
        // String fields
        $string_fields = array(
            'account_type', 'account_number', 'account_name',
            'bank_name', 'branch_name', 'routing_number',
            'instructions_en', 'instructions_bn', 'sms_keywords'
        );
        
        foreach ($string_fields as $field) {
            if ($request->has_param($field)) {
                $value = $request->get_param($field);
                if (in_array($field, array('instructions_en', 'instructions_bn'))) {
                    $data[$field] = sanitize_textarea_field($value);
                } else {
                    $data[$field] = sanitize_text_field($value);
                }
            }
        }
        
        // Validate account number for MFS methods
        if (!empty($data['account_number']) && $method !== 'bank') {
            $phone = PayHobe_REST_API::validate_phone($data['account_number']);
            if (!$phone) {
                return PayHobe_REST_API::error_response(
                    'payhobe_invalid_phone',
                    __('Invalid mobile number format.', 'payhobe'),
                    400
                );
            }
            $data['account_number'] = $phone;
        }
        
        if (empty($data)) {
            return PayHobe_REST_API::error_response(
                'payhobe_no_data',
                __('No data to update.', 'payhobe'),
                400
            );
        }
        
        if (PayHobe_Database::save_mfs_config($merchant_id, $method, $data)) {
            $config = PayHobe_Database::get_mfs_config($merchant_id, $method);
            
            do_action('payhobe_config_updated', $method, $config);
            
            return PayHobe_REST_API::success_response(
                $this->format_config($method, $config),
                __('Configuration updated successfully.', 'payhobe')
            );
        }
        
        return PayHobe_REST_API::error_response(
            'payhobe_update_failed',
            __('Failed to update configuration.', 'payhobe'),
            500
        );
    }
    
    /**
     * Get general settings
     */
    public function get_settings($request) {
        $settings = array(
            'currency' => get_option('payhobe_currency', 'BDT'),
            'auto_verify' => (bool) get_option('payhobe_auto_verify', true),
            'email_notifications' => (bool) get_option('payhobe_email_notifications', true),
            'sms_retention_days' => (int) get_option('payhobe_sms_retention_days', 30),
            'pending_timeout_hours' => (int) get_option('payhobe_pending_timeout_hours', 24),
            'dashboard_url' => get_option('payhobe_dashboard_url', ''),
            'cors_origins' => get_option('payhobe_api_cors_origins', array()),
            'onboarding_complete' => !get_option('payhobe_needs_onboarding', true),
            'site_url' => get_site_url(),
            'api_url' => PayHobe_REST_API::get_api_url(),
            'version' => PAYHOBE_VERSION
        );
        
        return PayHobe_REST_API::success_response($settings);
    }
    
    /**
     * Update general settings
     */
    public function update_settings($request) {
        $updated = array();
        
        $settings_map = array(
            'currency' => 'payhobe_currency',
            'auto_verify' => 'payhobe_auto_verify',
            'email_notifications' => 'payhobe_email_notifications',
            'sms_retention_days' => 'payhobe_sms_retention_days',
            'pending_timeout_hours' => 'payhobe_pending_timeout_hours',
            'dashboard_url' => 'payhobe_dashboard_url',
            'cors_origins' => 'payhobe_api_cors_origins'
        );
        
        foreach ($settings_map as $param => $option) {
            if ($request->has_param($param)) {
                $value = $request->get_param($param);
                
                // Sanitize based on type
                if (in_array($param, array('auto_verify', 'email_notifications'))) {
                    $value = (bool) $value;
                } elseif (in_array($param, array('sms_retention_days', 'pending_timeout_hours'))) {
                    $value = absint($value);
                } elseif ($param === 'cors_origins') {
                    $value = is_array($value) ? array_map('esc_url_raw', $value) : array();
                } elseif ($param === 'dashboard_url') {
                    $value = esc_url_raw($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                update_option($option, $value);
                $updated[$param] = $value;
            }
        }
        
        if (empty($updated)) {
            return PayHobe_REST_API::error_response(
                'payhobe_no_data',
                __('No settings to update.', 'payhobe'),
                400
            );
        }
        
        return PayHobe_REST_API::success_response($updated, __('Settings updated successfully.', 'payhobe'));
    }
    
    /**
     * Complete onboarding
     */
    public function complete_onboarding($request) {
        update_option('payhobe_needs_onboarding', false);
        
        do_action('payhobe_onboarding_completed');
        
        return PayHobe_REST_API::success_response(null, __('Onboarding completed!', 'payhobe'));
    }
    
    /**
     * Test configuration
     */
    public function test_configuration($request) {
        $method = $request->get_param('method');
        $merchant_id = get_option('payhobe_merchant_user_id');
        
        if (!empty($method)) {
            $config = PayHobe_Database::get_mfs_config($merchant_id, $method);
            
            if (!$config || !$config->is_enabled) {
                return PayHobe_REST_API::error_response(
                    'payhobe_not_configured',
                    sprintf(__('%s is not configured or enabled.', 'payhobe'), strtoupper($method)),
                    400
                );
            }
            
            if (empty($config->account_number)) {
                return PayHobe_REST_API::error_response(
                    'payhobe_missing_account',
                    __('Account number is not set.', 'payhobe'),
                    400
                );
            }
            
            return PayHobe_REST_API::success_response(array(
                'method' => $method,
                'status' => 'ok',
                'has_sms_parser' => (bool) $config->sms_parser_enabled
            ), __('Configuration is valid.', 'payhobe'));
        }
        
        // Test all configurations
        $configs = PayHobe_Database::get_mfs_config($merchant_id);
        $results = array();
        
        foreach ($configs as $config) {
            if ($config->is_enabled) {
                $results[$config->method] = array(
                    'enabled' => true,
                    'has_account' => !empty($config->account_number),
                    'has_instructions' => !empty($config->instructions_en) || !empty($config->instructions_bn),
                    'sms_parser' => (bool) $config->sms_parser_enabled
                );
            }
        }
        
        if (empty($results)) {
            return PayHobe_REST_API::error_response(
                'payhobe_no_methods',
                __('No payment methods are enabled.', 'payhobe'),
                400
            );
        }
        
        return PayHobe_REST_API::success_response($results, __('Configuration test completed.', 'payhobe'));
    }
    
    /**
     * Get available payment methods (public endpoint)
     */
    public function get_available_methods($request) {
        $merchant_id = get_option('payhobe_merchant_user_id');
        
        if (!$merchant_id) {
            return PayHobe_REST_API::error_response(
                'payhobe_not_configured',
                __('Payment gateway is not configured.', 'payhobe'),
                400
            );
        }
        
        $configs = PayHobe_Database::get_mfs_config($merchant_id);
        $methods = array();
        
        foreach ($configs as $config) {
            if ($config->is_enabled && !empty($config->account_number)) {
                $methods[] = array(
                    'id' => $config->method,
                    'name' => $this->get_method_name($config->method),
                    'type' => $config->method === 'bank' ? 'bank' : 'mfs',
                    'icon' => $this->get_method_icon($config->method),
                    'account_display' => PayHobe_Encryption::mask($config->account_number, 4),
                    'instructions_en' => $config->instructions_en ?: $this->get_default_instructions($config->method, 'en'),
                    'instructions_bn' => $config->instructions_bn ?: $this->get_default_instructions($config->method, 'bn'),
                    'requires_sender' => $config->method !== 'bank',
                    'requires_screenshot' => $config->method === 'bank'
                );
            }
        }
        
        return PayHobe_REST_API::success_response(array(
            'methods' => $methods,
            'currency' => get_option('payhobe_currency', 'BDT')
        ));
    }
    
    /**
     * Format configuration for response
     */
    private function format_config($method, $config) {
        $default = array(
            'method' => $method,
            'name' => $this->get_method_name($method),
            'type' => $method === 'bank' ? 'bank' : 'mfs',
            'is_enabled' => false,
            'account_type' => 'personal',
            'account_number' => '',
            'account_name' => '',
            'bank_name' => '',
            'branch_name' => '',
            'routing_number' => '',
            'instructions_en' => $this->get_default_instructions($method, 'en'),
            'instructions_bn' => $this->get_default_instructions($method, 'bn'),
            'sms_parser_enabled' => false,
            'sms_keywords' => $this->get_default_sms_keywords($method)
        );
        
        if ($config) {
            return array(
                'method' => $method,
                'name' => $this->get_method_name($method),
                'type' => $method === 'bank' ? 'bank' : 'mfs',
                'is_enabled' => (bool) $config->is_enabled,
                'account_type' => $config->account_type ?: 'personal',
                'account_number' => $config->account_number ?: '',
                'account_name' => $config->account_name ?: '',
                'bank_name' => $config->bank_name ?: '',
                'branch_name' => $config->branch_name ?: '',
                'routing_number' => $config->routing_number ?: '',
                'instructions_en' => $config->instructions_en ?: $default['instructions_en'],
                'instructions_bn' => $config->instructions_bn ?: $default['instructions_bn'],
                'sms_parser_enabled' => (bool) $config->sms_parser_enabled,
                'sms_keywords' => $config->sms_keywords ?: $default['sms_keywords']
            );
        }
        
        return $default;
    }
    
    /**
     * Get method display name
     */
    private function get_method_name($method) {
        $names = array(
            'bkash' => 'bKash',
            'rocket' => 'Rocket',
            'nagad' => 'Nagad',
            'upay' => 'Upay',
            'bank' => 'Bank Transfer'
        );
        return isset($names[$method]) ? $names[$method] : ucfirst($method);
    }
    
    /**
     * Get method icon URL
     */
    private function get_method_icon($method) {
        return PAYHOBE_PLUGIN_URL . 'assets/images/' . $method . '.png';
    }
    
    /**
     * Get default instructions
     */
    private function get_default_instructions($method, $lang = 'en') {
        $instructions = array(
            'bkash' => array(
                'en' => "1. Open bKash app or dial *247#\n2. Select 'Send Money'\n3. Enter our bKash number\n4. Enter the exact amount\n5. Enter your PIN and confirm\n6. Note down the Transaction ID",
                'bn' => "১. বিকাশ অ্যাপ খুলুন অথবা *247# ডায়াল করুন\n২. 'সেন্ড মানি' নির্বাচন করুন\n৩. আমাদের বিকাশ নম্বর দিন\n৪. সঠিক টাকার পরিমাণ দিন\n৫. পিন দিয়ে নিশ্চিত করুন\n৬. ট্রানজেকশন আইডি নোট করুন"
            ),
            'rocket' => array(
                'en' => "1. Dial *322#\n2. Select 'Send Money'\n3. Enter our Rocket number\n4. Enter the exact amount\n5. Enter your PIN\n6. Note down the Transaction ID",
                'bn' => "১. *322# ডায়াল করুন\n২. 'সেন্ড মানি' নির্বাচন করুন\n৩. আমাদের রকেট নম্বর দিন\n৪. সঠিক টাকার পরিমাণ দিন\n৫. পিন দিন\n৬. ট্রানজেকশন আইডি নোট করুন"
            ),
            'nagad' => array(
                'en' => "1. Open Nagad app or dial *167#\n2. Select 'Send Money'\n3. Enter our Nagad number\n4. Enter the exact amount\n5. Enter your PIN\n6. Note down the Transaction ID",
                'bn' => "১. নগদ অ্যাপ খুলুন অথবা *167# ডায়াল করুন\n২. 'সেন্ড মানি' নির্বাচন করুন\n৩. আমাদের নগদ নম্বর দিন\n৪. সঠিক টাকার পরিমাণ দিন\n৫. পিন দিন\n৬. ট্রানজেকশন আইডি নোট করুন"
            ),
            'upay' => array(
                'en' => "1. Open Upay app\n2. Select 'Send Money'\n3. Enter our Upay number\n4. Enter the exact amount\n5. Enter your PIN\n6. Note down the Transaction ID",
                'bn' => "১. উপায় অ্যাপ খুলুন\n২. 'সেন্ড মানি' নির্বাচন করুন\n৩. আমাদের উপায় নম্বর দিন\n৪. সঠিক টাকার পরিমাণ দিন\n৫. পিন দিন\n৬. ট্রানজেকশন আইডি নোট করুন"
            ),
            'bank' => array(
                'en' => "1. Log in to your bank app/website\n2. Select 'Fund Transfer'\n3. Enter our bank account details\n4. Enter the exact amount\n5. Complete the transfer\n6. Take a screenshot of the confirmation",
                'bn' => "১. আপনার ব্যাংক অ্যাপ/ওয়েবসাইটে লগইন করুন\n২. 'ফান্ড ট্রান্সফার' নির্বাচন করুন\n৩. আমাদের ব্যাংক একাউন্ট তথ্য দিন\n৪. সঠিক টাকার পরিমাণ দিন\n৫. ট্রান্সফার সম্পন্ন করুন\n৬. কনফার্মেশনের স্ক্রিনশট নিন"
            )
        );
        
        return isset($instructions[$method][$lang]) ? $instructions[$method][$lang] : '';
    }
    
    /**
     * Get default SMS keywords for parsing
     */
    private function get_default_sms_keywords($method) {
        $keywords = array(
            'bkash' => 'bKash,TrxID,received,প্রাপ্ত',
            'rocket' => 'Rocket,DBBL,TxnId,received',
            'nagad' => 'Nagad,TxnNo,received,প্রাপ্ত',
            'upay' => 'Upay,TxnID,received',
            'bank' => ''
        );
        
        return isset($keywords[$method]) ? $keywords[$method] : '';
    }
}
