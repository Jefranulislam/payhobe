<?php
/**
 * PayHobe Upay Payment Gateway
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Upay Gateway class
 */
class PayHobe_Gateway_Upay extends PayHobe_Gateway_bKash {
    
    /**
     * Method ID
     */
    protected $method = 'upay';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'payhobe_upay';
        $this->icon = PAYHOBE_PLUGIN_URL . 'assets/images/upay.png';
        $this->has_fields = true;
        $this->method_title = __('Upay (PayHobe)', 'payhobe');
        $this->method_description = __('Accept payments via Upay mobile financial service.', 'payhobe');
        
        $this->supports = array('products');
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title', __('Upay', 'payhobe'));
        $this->description = $this->get_option('description', __('Pay using Upay Send Money.', 'payhobe'));
        $this->enabled = $this->get_option('enabled', 'no');
        
        $this->load_payhobe_config();
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'payhobe'),
                'type' => 'checkbox',
                'label' => __('Enable Upay Payment', 'payhobe'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'payhobe'),
                'type' => 'text',
                'default' => __('Upay', 'payhobe'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'payhobe'),
                'type' => 'textarea',
                'default' => __('Pay using Upay Send Money.', 'payhobe'),
                'desc_tip' => true
            )
        );
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
                <div class="payhobe-account-info">
                    <p><strong><?php esc_html_e('Upay Number:', 'payhobe'); ?></strong></p>
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
                    <label for="payhobe_upay_trx_id">
                        <?php esc_html_e('Transaction ID (TxnID)', 'payhobe'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" class="input-text" name="payhobe_trx_id" id="payhobe_upay_trx_id" 
                           placeholder="<?php esc_attr_e('e.g., ABC123XYZ', 'payhobe'); ?>" required>
                </p>
                
                <p class="form-row form-row-wide">
                    <label for="payhobe_upay_sender">
                        <?php esc_html_e('Sender Upay Number', 'payhobe'); ?> <span class="required">*</span>
                    </label>
                    <input type="tel" class="input-text" name="payhobe_sender" id="payhobe_upay_sender" 
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
     */
    public function validate_fields() {
        $trx_id = isset($_POST['payhobe_trx_id']) ? sanitize_text_field($_POST['payhobe_trx_id']) : '';
        $sender = isset($_POST['payhobe_sender']) ? sanitize_text_field($_POST['payhobe_sender']) : '';
        
        if (empty($trx_id)) {
            wc_add_notice(__('Please enter the Upay Transaction ID.', 'payhobe'), 'error');
            return false;
        }
        
        if (!PayHobe_REST_API::validate_transaction_id($trx_id, $this->method)) {
            wc_add_notice(__('Invalid Upay Transaction ID format.', 'payhobe'), 'error');
            return false;
        }
        
        $existing = PayHobe_Database::find_payment_by_transaction_id(strtoupper($trx_id), $this->method);
        if ($existing) {
            wc_add_notice(__('This Transaction ID has already been used.', 'payhobe'), 'error');
            return false;
        }
        
        if (empty($sender)) {
            wc_add_notice(__('Please enter the sender Upay number.', 'payhobe'), 'error');
            return false;
        }
        
        $phone = PayHobe_REST_API::validate_phone($sender);
        if (!$phone) {
            wc_add_notice(__('Please enter a valid Bangladeshi mobile number.', 'payhobe'), 'error');
            return false;
        }
        
        return true;
    }
}
