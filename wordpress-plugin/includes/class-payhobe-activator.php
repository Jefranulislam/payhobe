<?php
/**
 * PayHobe Activator
 *
 * Handles plugin activation tasks
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activator class
 */
class PayHobe_Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Check requirements
        self::check_requirements();
        
        // Create database tables
        PayHobe_Database::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Create upload directories
        self::create_upload_directories();
        
        // Set merchant user
        self::set_merchant_user();
        
        // Schedule cleanup cron
        self::schedule_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        set_transient('payhobe_activated', true, 30);
    }
    
    /**
     * Check requirements
     */
    private static function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(PAYHOBE_PLUGIN_BASENAME);
            wp_die(
                esc_html__('PayHobe requires PHP 7.4 or higher.', 'payhobe'),
                esc_html__('Plugin Activation Error', 'payhobe'),
                array('back_link' => true)
            );
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            deactivate_plugins(PAYHOBE_PLUGIN_BASENAME);
            wp_die(
                esc_html__('PayHobe requires WordPress 5.8 or higher.', 'payhobe'),
                esc_html__('Plugin Activation Error', 'payhobe'),
                array('back_link' => true)
            );
        }
        
        // Check OpenSSL
        if (!extension_loaded('openssl')) {
            deactivate_plugins(PAYHOBE_PLUGIN_BASENAME);
            wp_die(
                esc_html__('PayHobe requires the OpenSSL PHP extension.', 'payhobe'),
                esc_html__('Plugin Activation Error', 'payhobe'),
                array('back_link' => true)
            );
        }
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $default_options = array(
            'payhobe_version' => PAYHOBE_VERSION,
            'payhobe_needs_onboarding' => true,
            'payhobe_currency' => 'BDT',
            'payhobe_enabled_methods' => array(),
            'payhobe_sms_retention_days' => 30,
            'payhobe_pending_timeout_hours' => 24,
            'payhobe_auto_verify' => true,
            'payhobe_email_notifications' => true,
            'payhobe_dashboard_url' => '',
            'payhobe_api_cors_origins' => array()
        );
        
        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Create upload directories
     */
    private static function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $payhobe_dir = $upload_dir['basedir'] . '/payhobe';
        $screenshots_dir = $payhobe_dir . '/screenshots';
        
        if (!file_exists($payhobe_dir)) {
            wp_mkdir_p($payhobe_dir);
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            file_put_contents($payhobe_dir . '/.htaccess', $htaccess_content);
            
            // Create index.php
            file_put_contents($payhobe_dir . '/index.php', '<?php // Silence is golden');
        }
        
        if (!file_exists($screenshots_dir)) {
            wp_mkdir_p($screenshots_dir);
            file_put_contents($screenshots_dir . '/index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Set the current user as merchant
     */
    private static function set_merchant_user() {
        $current_user_id = get_current_user_id();
        
        if ($current_user_id && !get_option('payhobe_merchant_user_id')) {
            update_option('payhobe_merchant_user_id', $current_user_id);
        }
    }
    
    /**
     * Schedule cron events
     */
    private static function schedule_events() {
        // SMS cleanup
        if (!wp_next_scheduled('payhobe_cleanup_sms_logs')) {
            wp_schedule_event(time(), 'daily', 'payhobe_cleanup_sms_logs');
        }
        
        // Pending payment timeout check
        if (!wp_next_scheduled('payhobe_check_pending_payments')) {
            wp_schedule_event(time(), 'hourly', 'payhobe_check_pending_payments');
        }
        
        // Auto verification
        if (!wp_next_scheduled('payhobe_auto_verify_payments')) {
            wp_schedule_event(time(), 'twicehourly', 'payhobe_auto_verify_payments');
        }
    }
}
