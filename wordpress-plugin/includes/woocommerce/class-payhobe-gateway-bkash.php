<?php
/**
 * PayHobe bKash Payment Gateway
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * bKash Gateway class
 */
class PayHobe_Gateway_bKash extends WC_Payment_Gateway {
    
    /**
     * Method ID
     */
    protected $method = 'bkash';
    
    /**
     * Account number
     */
    protected $account_number = '';
    
    /**
     * Account type
     */
    protected $account_type = 'personal';
    
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
        $this->id = 'payhobe_bkash';
        $this->icon = PAYHOBE_PLUGIN_URL . 'assets/images/bkash.png';
        $this->has_fields = true;
        $this->method_title = __('bKash (PayHobe)', 'payhobe');
        $this->method_description = __('Accept payments via bKash mobile financial service.', 'payhobe');
        
        $this->supports = array('products');
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title', __('bKash', 'payhobe'));
        $this->description = $this->get_option('description', __('Pay using bKash Send Money.', 'payhobe'));
        $this->enabled = $this->get_option('enabled', 'no');
        
        // Load config from PayHobe
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
            $this->account_type = $config->account_type;
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
                'label' => __('Enable bKash Payment', 'payhobe'),
                'default' => 'no',
                'description' => __('Configure in PayHobe settings for best experience.', 'payhobe')
            ),
            'title' => array(
                'title' => __('Title', 'payhobe'),
                'type' => 'text',
                'description' => __('Payment method title at checkout.', 'payhobe'),
                'default' => __('bKash', 'payhobe'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'payhobe'),
                'type' => 'textarea',
                'description' => __('Payment method description at checkout.', 'payhobe'),
                'default' => __('Pay using bKash Send Money.', 'payhobe'),
                'desc_tip' => true
            )
        );
    }
    
    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function is_available() {
        if ($this->enabled !== 'yes') {
            return false;
        }
        
        // Check if properly configured
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
        
        // Show account number and instructions
        if ($config && !empty($config->account_number)) {
            ?>
            <div class="payhobe-payment-instructions">
                <div class="payhobe-account-info">
                    <p><strong><?php esc_html_e('bKash Number:', 'payhobe'); ?></strong></p>
                    <p class="payhobe-account-number"><?php echo esc_html($config->account_number); ?></p>
                    <p class="payhobe-account-type">(<?php echo esc_html(ucfirst($config->account_type ?: 'Personal')); ?>)</p>
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
                    <label for="payhobe_bkash_trx_id">
                        <?php esc_html_e('Transaction ID (TrxID)', 'payhobe'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" class="input-text" name="payhobe_trx_id" id="payhobe_bkash_trx_id" 
                           placeholder="<?php esc_attr_e('e.g., ABC123XYZ', 'payhobe'); ?>" required>
                </p>
                
                <p class="form-row form-row-wide">
                    <label for="payhobe_bkash_sender">
                        <?php esc_html_e('Sender bKash Number', 'payhobe'); ?> <span class="required">*</span>
                    </label>
                    <input type="tel" class="input-text" name="payhobe_sender" id="payhobe_bkash_sender" 
                           placeholder="<?php esc_attr_e('e.g., 01712345678', 'payhobe'); ?>" required>
                </p>
            </div>
            <?php
        } else {
            echo '<p class="payhobe-error">' . esc_html__('This payment method is not properly configured.', 'payhobe') . '</p>';
        }
    }
    
    /**
     * Validate payment fields
     *
     * @return bool
     */
    public function validate_fields() {
        $trx_id = isset($_POST['payhobe_trx_id']) ? sanitize_text_field($_POST['payhobe_trx_id']) : '';
        $sender = isset($_POST['payhobe_sender']) ? sanitize_text_field($_POST['payhobe_sender']) : '';
        
        if (empty($trx_id)) {
            wc_add_notice(__('Please enter the bKash Transaction ID.', 'payhobe'), 'error');
            return false;
        }
        
        // Validate transaction ID format
        if (!PayHobe_REST_API::validate_transaction_id($trx_id, $this->method)) {
            wc_add_notice(__('Invalid bKash Transaction ID format.', 'payhobe'), 'error');
            return false;
        }
        
        // Check for duplicate
        $existing = PayHobe_Database::find_payment_by_transaction_id(strtoupper($trx_id), $this->method);
        if ($existing) {
            wc_add_notice(__('This Transaction ID has already been used.', 'payhobe'), 'error');
            return false;
        }
        
        if (empty($sender)) {
            wc_add_notice(__('Please enter the sender bKash number.', 'payhobe'), 'error');
            return false;
        }
        
        // Validate phone
        $phone = PayHobe_REST_API::validate_phone($sender);
        if (!$phone) {
            wc_add_notice(__('Please enter a valid Bangladeshi mobile number.', 'payhobe'), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Process payment
     *
     * @param int $order_id Order ID
     * @return array Result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        $trx_id = strtoupper(sanitize_text_field($_POST['payhobe_trx_id']));
        $sender = PayHobe_REST_API::validate_phone($_POST['payhobe_sender']);
        
        // Create payment record
        $payment_data = array(
            'order_id' => $order_id,
            'user_id' => get_current_user_id() ?: null,
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'payment_method' => $this->method,
            'transaction_id' => $trx_id,
            'sender_number' => $sender,
            'amount' => $order->get_total(),
            'payment_status' => 'pending'
        );
        
        $payment_id = PayHobe_Database::insert_payment($payment_data);
        
        if (!$payment_id) {
            wc_add_notice(__('Failed to process payment. Please try again.', 'payhobe'), 'error');
            return array('result' => 'failure');
        }
        
        // Store payment ID in order meta
        $order->update_meta_data('_payhobe_payment_id', $payment_id);
        $order->update_meta_data('_payhobe_trx_id', $trx_id);
        $order->save();
        
        // Set order status to on-hold (pending payment verification)
        $order->update_status('on-hold', sprintf(
            __('Awaiting bKash payment verification. Transaction ID: %s', 'payhobe'),
            $trx_id
        ));
        
        // Trigger auto verification
        do_action('payhobe_payment_submitted', $payment_id, $payment_data);
        
        // Check if already verified
        $payment = PayHobe_Database::get_payment($payment_id);
        if ($payment && $payment->payment_status === 'confirmed') {
            $order->payment_complete($trx_id);
        }
        
        // Empty the cart
        WC()->cart->empty_cart();
        
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
    
    /**
     * Thank you page
     *
     * @param int $order_id Order ID
     */
    public function thankyou_page($order_id) {
        // Handled by WC Integration class
    }
}
