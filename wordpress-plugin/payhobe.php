<?php
/**
 * Plugin Name: PayHobe - Bangladeshi MFS Payment Gateway
 * Plugin URI: https://payhobe.com
 * Description: A headless WordPress plugin enabling Bangladeshi WooCommerce merchants to accept seamless online payments through bKash, Rocket, Nagad, Upay, and bank transfers.
 * Version: 1.0.0
 * Author: PayHobe
 * Author URI: https://payhobe.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: payhobe
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('PAYHOBE_VERSION', '1.0.0');
define('PAYHOBE_PLUGIN_FILE', __FILE__);
define('PAYHOBE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAYHOBE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PAYHOBE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Encryption key (should be set in wp-config.php for production)
if (!defined('PAYHOBE_ENCRYPTION_KEY')) {
    define('PAYHOBE_ENCRYPTION_KEY', 'payhobe_default_key_change_in_production');
}

/**
 * Main PayHobe Plugin Class
 */
final class PayHobe {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Plugin version
     */
    public $version = '1.0.0';
    
    /**
     * Get single instance
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
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Define additional constants
     */
    private function define_constants() {
        $this->define('PAYHOBE_ABSPATH', dirname(PAYHOBE_PLUGIN_FILE) . '/');
    }
    
    /**
     * Define constant if not already set
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core includes
        require_once PAYHOBE_PLUGIN_DIR . 'includes/class-payhobe-encryption.php';
        require_once PAYHOBE_PLUGIN_DIR . 'includes/class-payhobe-database.php';
        require_once PAYHOBE_PLUGIN_DIR . 'includes/class-payhobe-activator.php';
        
        // Admin includes
        if (is_admin()) {
            require_once PAYHOBE_PLUGIN_DIR . 'includes/admin/class-payhobe-admin.php';
            require_once PAYHOBE_PLUGIN_DIR . 'includes/admin/class-payhobe-settings.php';
        }
        
        // REST API
        require_once PAYHOBE_PLUGIN_DIR . 'includes/api/class-payhobe-rest-api.php';
        require_once PAYHOBE_PLUGIN_DIR . 'includes/api/class-payhobe-auth-controller.php';
        require_once PAYHOBE_PLUGIN_DIR . 'includes/api/class-payhobe-payments-controller.php';
        require_once PAYHOBE_PLUGIN_DIR . 'includes/api/class-payhobe-sms-controller.php';
        require_once PAYHOBE_PLUGIN_DIR . 'includes/api/class-payhobe-config-controller.php';
        require_once PAYHOBE_PLUGIN_DIR . 'includes/api/class-payhobe-dashboard-controller.php';
        
        // Payment processing
        require_once PAYHOBE_PLUGIN_DIR . 'includes/class-payhobe-payment-processor.php';
        require_once PAYHOBE_PLUGIN_DIR . 'includes/class-payhobe-sms-parser.php';
        require_once PAYHOBE_PLUGIN_DIR . 'includes/class-payhobe-verification.php';
    }
    
    /**
     * Include WooCommerce files (called after plugins_loaded)
     */
    public function include_woocommerce_files() {
        if ($this->is_woocommerce_active()) {
            require_once PAYHOBE_PLUGIN_DIR . 'includes/woocommerce/class-payhobe-wc-integration.php';
            require_once PAYHOBE_PLUGIN_DIR . 'includes/woocommerce/class-payhobe-gateway-bkash.php';
            require_once PAYHOBE_PLUGIN_DIR . 'includes/woocommerce/class-payhobe-gateway-rocket.php';
            require_once PAYHOBE_PLUGIN_DIR . 'includes/woocommerce/class-payhobe-gateway-nagad.php';
            require_once PAYHOBE_PLUGIN_DIR . 'includes/woocommerce/class-payhobe-gateway-upay.php';
            require_once PAYHOBE_PLUGIN_DIR . 'includes/woocommerce/class-payhobe-gateway-bank.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(PAYHOBE_PLUGIN_FILE, array('PayHobe_Activator', 'activate'));
        register_deactivation_hook(PAYHOBE_PLUGIN_FILE, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'on_plugins_loaded'), -1);
        add_action('init', array($this, 'init'), 0);
        add_action('rest_api_init', array($this, 'init_rest_api'));
        
        // Load text domain
        add_action('init', array($this, 'load_textdomain'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }
    
    /**
     * Deactivation callback
     */
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('payhobe_process_pending_payments');
        wp_clear_scheduled_hook('payhobe_cleanup_expired_payments');
    }
    
    /**
     * On plugins loaded
     */
    public function on_plugins_loaded() {
        // Include WooCommerce files after WooCommerce is loaded
        $this->include_woocommerce_files();
        
        do_action('payhobe_loaded');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        do_action('before_payhobe_init');
        
        // Initialize components
        if (is_admin()) {
            PayHobe_Admin::instance();
        }
        
        if ($this->is_woocommerce_active()) {
            PayHobe_WC_Integration::instance();
        }
        
        do_action('payhobe_init');
    }
    
    /**
     * Initialize REST API
     */
    public function init_rest_api() {
        PayHobe_REST_API::instance();
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('payhobe', false, dirname(PAYHOBE_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Check if WooCommerce is active
     */
    public function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check WooCommerce dependency
        if (!$this->is_woocommerce_active()) {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('PayHobe requires WooCommerce to be installed and active.', 'payhobe'); ?></p>
            </div>
            <?php
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('PayHobe requires PHP 7.4 or higher.', 'payhobe'); ?></p>
            </div>
            <?php
        }
        
        // Show onboarding notice
        if (get_option('payhobe_needs_onboarding', true) && $this->is_woocommerce_active()) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <?php esc_html_e('Welcome to PayHobe! Let\'s get you started.', 'payhobe'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=payhobe-onboarding')); ?>" class="button button-primary">
                        <?php esc_html_e('Start Setup', 'payhobe'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', PAYHOBE_PLUGIN_FILE, true);
        }
    }
    
    /**
     * Get plugin path
     */
    public function plugin_path() {
        return untrailingslashit(PAYHOBE_PLUGIN_DIR);
    }
    
    /**
     * Get plugin URL
     */
    public function plugin_url() {
        return untrailingslashit(PAYHOBE_PLUGIN_URL);
    }
}

/**
 * Returns the main instance of PayHobe
 */
function PayHobe() {
    return PayHobe::instance();
}

// Initialize the plugin
PayHobe();
