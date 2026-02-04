<?php
/**
 * PayHobe Bank Transfer Payment Gateway
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bank Transfer Gateway class
 */
class PayHobe_Gateway_Bank extends WC_Payment_Gateway {
    
    /**
     * Method ID
     */
    protected $method = 'bank';
    
    /**
     * Account number
     */
    protected $account_number = '';
    
    /**
     * Account name
     */
    protected $account_name = '';
    
    /**
     * Bank name
     */
    protected $bank_name = '';
    
    /**
     * Branch name
     */
    protected $branch_name = '';
    
    /**
     * Routing number
     */
    protected $routing_number = '';
    
    /**
     * English instructions
     */
    protected $instructions_en = '';
    
    /**
     * Bengali instructions
     */
    protected $instructions_bn = '';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'payhobe_bank';
        $this->icon = PAYHOBE_PLUGIN_URL . 'assets/images/bank.png';
        $this->has_fields = true;
        $this->method_title = __('Bank Transfer (PayHobe)', 'payhobe');
        $this->method_description = __('Accept direct bank transfer payments.', 'payhobe');
        
        $this->supports = array('products');
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title', __('Bank Transfer', 'payhobe'));
        $this->description = $this->get_option('description', __('Pay via direct bank transfer.', 'payhobe'));
        $this->enabled = $this->get_option('enabled', 'no');
        
        $this->load_payhobe_config();
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
    }
    
    /**
     * Load configuration from PayHobe
     */
    protected function load_payhobe_config() {
        $merchant_id = get_option('payhobe_merchant_user_id');
        if (!$merchant_id) {
            return;
        }
        
        $config = PayHobe_Database::get_mfs_config($merchant_id, $this->method);
        
        if ($config && $config->is_enabled) {
            $this->enabled = 'yes';
            $this->account_number = $config->account_number;
            $this->account_name = $config->account_name;
            $this->bank_name = $config->bank_name;
            $this->branch_name = $config->branch_name;
            $this->routing_number = $config->routing_number;
            $this->instructions_en = $config->instructions_en;
            $this->instructions_bn = $config->instructions_bn;
        } else {
            $this->enabled = 'no';
        }
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'payhobe'),
                'type' => 'checkbox',
                'label' => __('Enable Bank Transfer', 'payhobe'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'payhobe'),
                'type' => 'text',
                'default' => __('Bank Transfer', 'payhobe'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'payhobe'),
                'type' => 'textarea',
                'default' => __('Pay via direct bank transfer.', 'payhobe'),
                'desc_tip' => true
            )
        );
    }
    
    /**
     * Check if gateway is available
     */
    public function is_available() {
        if ($this->enabled !== 'yes') {
            return false;
        }
        
        $merchant_id = get_option('payhobe_merchant_user_id');
        if (!$merchant_id) {
            return false;
        }
        
        $config = PayHobe_Database::get_mfs_config($merchant_id, $this->method);
        
        return $config && $config->is_enabled && !empty($config->account_number);
    }
    
    /**
     * Payment fields on checkout
     */
    public function payment_fields() {
        $merchant_id = get_option('payhobe_merchant_user_id');
        $config = PayHobe_Database::get_mfs_config($merchant_id, $this->method);
        
        if ($this->description) {
            echo '<p>' . wp_kses_post($this->description) . '</p>';
        }
        
        if ($config && !empty($config->account_number)) {
            ?>
            <div class="payhobe-payment-instructions">
                <div class="payhobe-account-info payhobe-bank-info">
                    <p><strong><?php esc_html_e('Bank Account Details:', 'payhobe'); ?></strong></p>
                    
                    <?php if (!empty($config->bank_name)): ?>
                    <p><span class="label"><?php esc_html_e('Bank Name:', 'payhobe'); ?></span> 
                       <span class="value"><?php echo esc_html($config->bank_name); ?></span></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($config->branch_name)): ?>
                    <p><span class="label"><?php esc_html_e('Branch:', 'payhobe'); ?></span> 
                       <span class="value"><?php echo esc_html($config->branch_name); ?></span></p>
                    <?php endif; ?>
                    
                    <p><span class="label"><?php esc_html_e('Account Name:', 'payhobe'); ?></span> 
                       <span class="value"><?php echo esc_html($config->account_name); ?></span></p>
                    
                    <p><span class="label"><?php esc_html_e('Account Number:', 'payhobe'); ?></span> 
                       <span class="value payhobe-account-number"><?php echo esc_html($config->account_number); ?></span></p>
                    
                    <?php if (!empty($config->routing_number)): ?>
                    <p><span class="label"><?php esc_html_e('Routing Number:', 'payhobe'); ?></span> 
                       <span class="value"><?php echo esc_html($config->routing_number); ?></span></p>
                    <?php endif; ?>
                </div>
                
                <div class="payhobe-instructions">
                    <p><strong><?php esc_html_e('Instructions:', 'payhobe'); ?></strong></p>
                    <?php
                    $locale = get_locale();
                    $instructions = (strpos($locale, 'bn') !== false && !empty($config->instructions_bn)) 
                        ? $config->instructions_bn 
                        : $config->instructions_en;
                    
                    if (!empty($instructions)) {
                        echo '<pre class="payhobe-instruction-text">' . esc_html($instructions) . '</pre>';
                    }
                    ?>
                </div>
            </div>
            
            <div class="payhobe-payment-fields">
                <p class="form-row form-row-wide">
                    <label for="payhobe_bank_trx_id">
                        <?php esc_html_e('Transaction Reference / UTR Number', 'payhobe'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" class="input-text" name="payhobe_trx_id" id="payhobe_bank_trx_id" 
                           placeholder="<?php esc_attr_e('e.g., UTR123456789', 'payhobe'); ?>" required>
                </p>
                
                <p class="form-row form-row-wide">
                    <label for="payhobe_bank_screenshot">
                        <?php esc_html_e('Payment Screenshot', 'payhobe'); ?> <span class="required">*</span>
                    </label>
                    <input type="file" class="input-text" name="payhobe_screenshot" id="payhobe_bank_screenshot" 
                           accept="image/*" required>
                    <span class="description"><?php esc_html_e('Upload a screenshot of your payment confirmation.', 'payhobe'); ?></span>
                </p>
            </div>
            <?php
        } else {
            echo '<p class="payhobe-error">' . esc_html__('This payment method is not properly configured.', 'payhobe') . '</p>';
        }
    }
    
    /**
     * Validate payment fields
     */
    public function validate_fields() {
        $trx_id = isset($_POST['payhobe_trx_id']) ? sanitize_text_field($_POST['payhobe_trx_id']) : '';
        
        if (empty($trx_id)) {
            wc_add_notice(__('Please enter the Transaction Reference.', 'payhobe'), 'error');
            return false;
        }
        
        if (!PayHobe_REST_API::validate_transaction_id($trx_id, $this->method)) {
            wc_add_notice(__('Invalid Transaction Reference format.', 'payhobe'), 'error');
            return false;
        }
        
        $existing = PayHobe_Database::find_payment_by_transaction_id(strtoupper($trx_id), $this->method);
        if ($existing) {
            wc_add_notice(__('This Transaction Reference has already been used.', 'payhobe'), 'error');
            return false;
        }
        
        // Check for screenshot
        if (empty($_FILES['payhobe_screenshot']) || empty($_FILES['payhobe_screenshot']['name'])) {
            wc_add_notice(__('Please upload a screenshot of your payment.', 'payhobe'), 'error');
            return false;
        }
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($_FILES['payhobe_screenshot']['type'], $allowed_types)) {
            wc_add_notice(__('Please upload a valid image file (JPEG, PNG, GIF, or WebP).', 'payhobe'), 'error');
            return false;
        }
        
        // Check file size (max 5MB)
        if ($_FILES['payhobe_screenshot']['size'] > 5 * 1024 * 1024) {
            wc_add_notice(__('Screenshot file is too large. Maximum size is 5MB.', 'payhobe'), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Process payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        $trx_id = strtoupper(sanitize_text_field($_POST['payhobe_trx_id']));
        
        // Create payment record
        $payment_data = array(
            'order_id' => $order_id,
            'user_id' => get_current_user_id() ?: null,
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'payment_method' => $this->method,
            'transaction_id' => $trx_id,
            'sender_number' => null,
            'amount' => $order->get_total(),
            'payment_status' => 'pending'
        );
        
        $payment_id = PayHobe_Database::insert_payment($payment_data);
        
        if (!$payment_id) {
            wc_add_notice(__('Failed to process payment. Please try again.', 'payhobe'), 'error');
            return array('result' => 'failure');
        }
        
        // Handle screenshot upload
        if (!empty($_FILES['payhobe_screenshot']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            
            // Set custom upload directory
            add_filter('upload_dir', array($this, 'custom_upload_dir'));
            
            $attachment_id = media_handle_upload('payhobe_screenshot', 0);
            
            remove_filter('upload_dir', array($this, 'custom_upload_dir'));
            
            if (!is_wp_error($attachment_id)) {
                PayHobe_Database::update_payment($payment_id, array(
                    'payment_screenshot' => $attachment_id
                ));
            }
        }
        
        // Store payment ID in order meta
        $order->update_meta_data('_payhobe_payment_id', $payment_id);
        $order->update_meta_data('_payhobe_trx_id', $trx_id);
        $order->save();
        
        // Set order status to on-hold
        $order->update_status('on-hold', sprintf(
            __('Awaiting bank transfer verification. Transaction Ref: %s', 'payhobe'),
            $trx_id
        ));
        
        // Empty the cart
        WC()->cart->empty_cart();
        
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
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
     * Thank you page
     */
    public function thankyou_page($order_id) {
        // Handled by WC Integration class
    }
}
