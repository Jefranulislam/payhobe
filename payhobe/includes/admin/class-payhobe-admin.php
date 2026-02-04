<?php
/**
 * PayHobe Admin
 *
 * Handles admin menu, pages and functionality
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class PayHobe_Admin {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
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
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'check_onboarding'));
        add_action('wp_ajax_payhobe_verify_payment', array($this, 'ajax_verify_payment'));
        add_action('wp_ajax_payhobe_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_payhobe_save_mfs_config', array($this, 'ajax_save_mfs_config'));
        add_action('wp_ajax_payhobe_regenerate_token', array($this, 'ajax_regenerate_token'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        // Main menu
        add_menu_page(
            __('PayHobe', 'payhobe'),
            __('PayHobe', 'payhobe'),
            'manage_woocommerce',
            'payhobe',
            array($this, 'render_dashboard_page'),
            'data:image/svg+xml;base64,' . base64_encode($this->get_menu_icon()),
            56
        );
        
        // Dashboard
        add_submenu_page(
            'payhobe',
            __('Dashboard', 'payhobe'),
            __('Dashboard', 'payhobe'),
            'manage_woocommerce',
            'payhobe',
            array($this, 'render_dashboard_page')
        );
        
        // Payments
        add_submenu_page(
            'payhobe',
            __('Payments', 'payhobe'),
            __('Payments', 'payhobe'),
            'manage_woocommerce',
            'payhobe-payments',
            array($this, 'render_payments_page')
        );
        
        // MFS Configuration
        add_submenu_page(
            'payhobe',
            __('MFS Configuration', 'payhobe'),
            __('MFS Config', 'payhobe'),
            'manage_woocommerce',
            'payhobe-mfs-config',
            array($this, 'render_mfs_config_page')
        );
        
        // SMS Logs
        add_submenu_page(
            'payhobe',
            __('SMS Logs', 'payhobe'),
            __('SMS Logs', 'payhobe'),
            'manage_woocommerce',
            'payhobe-sms-logs',
            array($this, 'render_sms_logs_page')
        );
        
        // Settings
        add_submenu_page(
            'payhobe',
            __('Settings', 'payhobe'),
            __('Settings', 'payhobe'),
            'manage_woocommerce',
            'payhobe-settings',
            array($this, 'render_settings_page')
        );
        
        // API Documentation
        add_submenu_page(
            'payhobe',
            __('API Docs', 'payhobe'),
            __('API Docs', 'payhobe'),
            'manage_woocommerce',
            'payhobe-api-docs',
            array($this, 'render_api_docs_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page
     */
    public function enqueue_scripts($hook) {
        // Only on our pages
        if (strpos($hook, 'payhobe') === false) {
            return;
        }
        
        wp_enqueue_style(
            'payhobe-admin',
            PAYHOBE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PAYHOBE_VERSION
        );
        
        wp_enqueue_script(
            'payhobe-admin',
            PAYHOBE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            PAYHOBE_VERSION,
            true
        );
        
        wp_localize_script('payhobe-admin', 'payhobe_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('payhobe_admin'),
            'rest_url' => rest_url('payhobe/v1/'),
            'strings' => array(
                'confirm_verify' => __('Are you sure you want to verify this payment?', 'payhobe'),
                'confirm_reject' => __('Are you sure you want to reject this payment?', 'payhobe'),
                'saving' => __('Saving...', 'payhobe'),
                'saved' => __('Saved!', 'payhobe'),
                'error' => __('Error occurred. Please try again.', 'payhobe')
            )
        ));
        
        // Charts library for dashboard
        if ($hook === 'toplevel_page_payhobe') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
                array(),
                '4.4.0',
                true
            );
        }
    }
    
    /**
     * Check if onboarding is needed
     */
    public function check_onboarding() {
        // Skip for AJAX
        if (wp_doing_ajax()) {
            return;
        }
        
        // Skip if already setup
        if (get_option('payhobe_setup_complete')) {
            return;
        }
        
        // Redirect to onboarding if on payhobe pages but not settings
        global $pagenow;
        if ($pagenow === 'admin.php' && isset($_GET['page'])) {
            $page = $_GET['page'];
            if (strpos($page, 'payhobe') !== false && $page !== 'payhobe-settings') {
                wp_redirect(admin_url('admin.php?page=payhobe-settings&tab=onboarding'));
                exit;
            }
        }
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $stats = PayHobe_Database::get_payment_stats();
        $recent_payments = PayHobe_Database::get_payments(array('limit' => 10));
        
        include PAYHOBE_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
    
    /**
     * Render payments page
     */
    public function render_payments_page() {
        // Handle filters
        $args = array(
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'method' => isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '',
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'limit' => 25,
            'offset' => isset($_GET['paged']) ? (absint($_GET['paged']) - 1) * 25 : 0
        );
        
        $payments = PayHobe_Database::get_payments($args);
        $total = PayHobe_Database::count_payments($args);
        
        // Single payment view
        if (isset($_GET['payment_id'])) {
            $payment = PayHobe_Database::get_payment(absint($_GET['payment_id']));
            if ($payment) {
                $logs = PayHobe_Database::get_transaction_logs($payment->payment_id);
                include PAYHOBE_PLUGIN_DIR . 'templates/admin/payment-single.php';
                return;
            }
        }
        
        include PAYHOBE_PLUGIN_DIR . 'templates/admin/payments.php';
    }
    
    /**
     * Render MFS configuration page
     */
    public function render_mfs_config_page() {
        $user_id = get_current_user_id();
        $methods = array('bkash', 'nagad', 'rocket', 'upay', 'bank');
        $configs = array();
        
        foreach ($methods as $method) {
            $config = PayHobe_Database::get_mfs_config($user_id, $method);
            $configs[$method] = $config ?: new stdClass();
        }
        
        include PAYHOBE_PLUGIN_DIR . 'templates/admin/mfs-config.php';
    }
    
    /**
     * Render SMS logs page
     */
    public function render_sms_logs_page() {
        $user_id = get_current_user_id();
        
        $args = array(
            'user_id' => $user_id,
            'limit' => 50,
            'offset' => isset($_GET['paged']) ? (absint($_GET['paged']) - 1) * 50 : 0,
            'payment_method' => isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '',
            'is_processed' => isset($_GET['processed']) ? ($_GET['processed'] === 'yes' ? 1 : 0) : ''
        );
        
        $logs = PayHobe_Database::get_sms_logs($args);
        
        include PAYHOBE_PLUGIN_DIR . 'templates/admin/sms-logs.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        $settings = new PayHobe_Settings();
        $settings->render($tab);
    }
    
    /**
     * Render API documentation page
     */
    public function render_api_docs_page() {
        $user_id = get_current_user_id();
        $api_token = PayHobe_Database::get_user_api_token($user_id);
        
        include PAYHOBE_PLUGIN_DIR . 'templates/admin/api-docs.php';
    }
    
    /**
     * Handle payment verification via AJAX
     */
    public function ajax_verify_payment() {
        check_ajax_referer('payhobe_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'payhobe')));
        }
        
        $payment_id = absint($_POST['payment_id']);
        $action = sanitize_text_field($_POST['verify_action']); // 'confirm' or 'reject'
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        $verification = new PayHobe_Verification();
        $result = $verification->manual_verify($payment_id, $action, get_current_user_id(), $notes);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $action === 'confirm' 
                    ? __('Payment confirmed successfully.', 'payhobe')
                    : __('Payment rejected.', 'payhobe')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update payment.', 'payhobe')));
        }
    }
    
    /**
     * Handle settings save via AJAX
     */
    public function ajax_save_settings() {
        check_ajax_referer('payhobe_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'payhobe')));
        }
        
        $settings = $_POST['settings'] ?? array();
        
        // Save each setting
        $allowed_settings = array(
            'payhobe_merchant_user_id',
            'payhobe_auto_verify',
            'payhobe_email_notifications',
            'payhobe_pending_timeout_hours',
            'payhobe_currency',
            'payhobe_debug_mode',
            'payhobe_sms_webhook_enabled',
            'payhobe_twilio_enabled',
            'payhobe_twilio_sid',
            'payhobe_twilio_token',
            'payhobe_twilio_phone'
        );
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_settings)) {
                if (in_array($key, array('payhobe_twilio_token'))) {
                    $value = PayHobe_Encryption::encrypt(sanitize_text_field($value));
                } else {
                    $value = sanitize_text_field($value);
                }
                update_option($key, $value);
            }
        }
        
        // Mark setup complete if required settings are filled
        if (!empty($settings['payhobe_merchant_user_id'])) {
            update_option('payhobe_setup_complete', true);
        }
        
        wp_send_json_success(array('message' => __('Settings saved successfully.', 'payhobe')));
    }
    
    /**
     * Handle MFS config save via AJAX
     */
    public function ajax_save_mfs_config() {
        check_ajax_referer('payhobe_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'payhobe')));
        }
        
        $method = sanitize_text_field($_POST['method']);
        $user_id = get_current_user_id();
        
        $config_data = array(
            'is_enabled' => !empty($_POST['is_enabled']) ? 1 : 0,
            'account_number' => sanitize_text_field($_POST['account_number'] ?? ''),
            'account_type' => sanitize_text_field($_POST['account_type'] ?? 'personal'),
            'account_name' => sanitize_text_field($_POST['account_holder_name'] ?? ''),
            'sms_parser_enabled' => !empty($_POST['sms_parser_enabled']) ? 1 : 0,
            'sms_keywords' => sanitize_textarea_field($_POST['custom_keywords'] ?? '')
        );
        
        // Bank-specific fields
        if ($method === 'bank') {
            $config_data['bank_name'] = sanitize_text_field($_POST['bank_name'] ?? '');
            $config_data['branch_name'] = sanitize_text_field($_POST['bank_branch'] ?? '');
            $config_data['routing_number'] = sanitize_text_field($_POST['routing_number'] ?? '');
        }
        
        $result = PayHobe_Database::save_mfs_config($user_id, $method, $config_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Configuration saved successfully.', 'payhobe')));
        } else {
            wp_send_json_error(array('message' => __('Failed to save configuration.', 'payhobe')));
        }
    }
    
    /**
     * Regenerate API token
     */
    public function ajax_regenerate_token() {
        check_ajax_referer('payhobe_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'payhobe')));
        }
        
        $user_id = get_current_user_id();
        $token_name = sanitize_text_field($_POST['token_name'] ?? 'API Token');
        
        // Revoke existing tokens
        PayHobe_Database::revoke_user_tokens($user_id);
        
        // Create new token
        $result = PayHobe_Database::create_api_token($user_id, $token_name, array('*'));
        
        if ($result) {
            wp_send_json_success(array(
                'token' => $result['token'],
                'message' => __('New API token generated. Copy it now - it won\'t be shown again!', 'payhobe')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to generate token.', 'payhobe')));
        }
    }
    
    /**
     * Get menu icon SVG
     *
     * @return string SVG markup
     */
    private function get_menu_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
        </svg>';
    }
}
