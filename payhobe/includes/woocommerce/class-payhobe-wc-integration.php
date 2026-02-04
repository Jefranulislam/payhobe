<?php
/**
 * PayHobe WooCommerce Integration
 *
 * Handles WooCommerce integration
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Integration class
 */
class PayHobe_WC_Integration {
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register payment gateways
        add_filter('woocommerce_payment_gateways', array($this, 'register_gateways'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add order actions
        add_action('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_payhobe_verify', array($this, 'process_verify_action'));
        add_action('woocommerce_order_action_payhobe_reject', array($this, 'process_reject_action'));
        
        // Add meta box for payment details
        add_action('add_meta_boxes', array($this, 'add_payment_meta_box'));
        
        // Order status update
        add_action('payhobe_payment_updated', array($this, 'sync_order_status'), 10, 3);
        
        // Thank you page
        add_action('woocommerce_thankyou', array($this, 'thankyou_page'));
        
        // Order received text
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'order_received_text'), 10, 2);
    }
    
    /**
     * Register payment gateways
     *
     * @param array $gateways Existing gateways
     * @return array Modified gateways
     */
    public function register_gateways($gateways) {
        $gateways[] = 'PayHobe_Gateway_bKash';
        $gateways[] = 'PayHobe_Gateway_Rocket';
        $gateways[] = 'PayHobe_Gateway_Nagad';
        $gateways[] = 'PayHobe_Gateway_Upay';
        $gateways[] = 'PayHobe_Gateway_Bank';
        
        return $gateways;
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!is_checkout()) {
            return;
        }
        
        wp_enqueue_style(
            'payhobe-checkout',
            PAYHOBE_PLUGIN_URL . 'assets/css/checkout.css',
            array(),
            PAYHOBE_VERSION
        );
        
        wp_enqueue_script(
            'payhobe-checkout',
            PAYHOBE_PLUGIN_URL . 'assets/js/checkout.js',
            array('jquery'),
            PAYHOBE_VERSION,
            true
        );
        
        wp_localize_script('payhobe-checkout', 'payhobe_checkout', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => PayHobe_REST_API::get_api_url(),
            'nonce' => wp_create_nonce('payhobe_checkout'),
            'i18n' => array(
                'transaction_id_required' => __('Please enter the Transaction ID.', 'payhobe'),
                'sender_required' => __('Please enter the sender mobile number.', 'payhobe'),
                'invalid_phone' => __('Please enter a valid mobile number.', 'payhobe'),
                'screenshot_required' => __('Please upload a screenshot of your payment.', 'payhobe'),
                'submitting' => __('Submitting...', 'payhobe'),
                'verifying' => __('Verifying payment...', 'payhobe')
            )
        ));
    }
    
    /**
     * Add order actions
     *
     * @param array $actions Existing actions
     * @return array Modified actions
     */
    public function add_order_actions($actions) {
        global $post;
        
        $order = wc_get_order($post->ID);
        if (!$order) {
            return $actions;
        }
        
        // Check if this is a PayHobe payment
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'payhobe_') !== 0) {
            return $actions;
        }
        
        // Only for pending orders
        if ($order->get_status() === 'on-hold' || $order->get_status() === 'pending') {
            $actions['payhobe_verify'] = __('Verify PayHobe Payment', 'payhobe');
            $actions['payhobe_reject'] = __('Reject PayHobe Payment', 'payhobe');
        }
        
        return $actions;
    }
    
    /**
     * Process verify action
     *
     * @param WC_Order $order Order object
     */
    public function process_verify_action($order) {
        $payment = $this->get_payment_for_order($order->get_id());
        
        if ($payment) {
            PayHobe_Database::update_payment($payment->payment_id, array(
                'payment_status' => 'confirmed',
                'verification_source' => 'manual',
                'verified_by' => get_current_user_id(),
                'verified_at' => current_time('mysql')
            ));
        }
        
        $order->payment_complete();
        $order->add_order_note(__('Payment verified manually via PayHobe.', 'payhobe'));
    }
    
    /**
     * Process reject action
     *
     * @param WC_Order $order Order object
     */
    public function process_reject_action($order) {
        $payment = $this->get_payment_for_order($order->get_id());
        
        if ($payment) {
            PayHobe_Database::update_payment($payment->payment_id, array(
                'payment_status' => 'failed',
                'verification_source' => 'manual',
                'verified_by' => get_current_user_id(),
                'verified_at' => current_time('mysql')
            ));
        }
        
        $order->update_status('failed', __('Payment rejected via PayHobe.', 'payhobe'));
    }
    
    /**
     * Add payment details meta box
     */
    public function add_payment_meta_box() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
            
        add_meta_box(
            'payhobe_payment_details',
            __('PayHobe Payment Details', 'payhobe'),
            array($this, 'render_payment_meta_box'),
            $screen,
            'side',
            'high'
        );
    }
    
    /**
     * Render payment meta box
     *
     * @param WP_Post|WC_Order $post_or_order Post or Order object
     */
    public function render_payment_meta_box($post_or_order) {
        $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order($post_or_order->ID);
        
        if (!$order) {
            return;
        }
        
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'payhobe_') !== 0) {
            echo '<p>' . esc_html__('This order was not paid via PayHobe.', 'payhobe') . '</p>';
            return;
        }
        
        $payment = $this->get_payment_for_order($order->get_id());
        
        if (!$payment) {
            echo '<p>' . esc_html__('No payment record found.', 'payhobe') . '</p>';
            return;
        }
        
        ?>
        <div class="payhobe-payment-info">
            <p>
                <strong><?php esc_html_e('Method:', 'payhobe'); ?></strong><br>
                <?php echo esc_html(ucfirst($payment->payment_method)); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Transaction ID:', 'payhobe'); ?></strong><br>
                <code><?php echo esc_html($payment->transaction_id); ?></code>
            </p>
            <?php if ($payment->sender_number): ?>
            <p>
                <strong><?php esc_html_e('Sender:', 'payhobe'); ?></strong><br>
                <?php echo esc_html(PayHobe_Encryption::mask_phone($payment->sender_number)); ?>
            </p>
            <?php endif; ?>
            <p>
                <strong><?php esc_html_e('Amount:', 'payhobe'); ?></strong><br>
                <?php echo wc_price($payment->amount); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Status:', 'payhobe'); ?></strong><br>
                <span class="payhobe-status payhobe-status-<?php echo esc_attr($payment->payment_status); ?>">
                    <?php echo esc_html(ucfirst($payment->payment_status)); ?>
                </span>
            </p>
            <?php if ($payment->verified_at): ?>
            <p>
                <strong><?php esc_html_e('Verified:', 'payhobe'); ?></strong><br>
                <?php echo esc_html($payment->verified_at); ?>
                <?php if ($payment->verification_source): ?>
                    (<?php echo esc_html($payment->verification_source); ?>)
                <?php endif; ?>
            </p>
            <?php endif; ?>
            <?php if ($payment->payment_screenshot): ?>
            <p>
                <strong><?php esc_html_e('Screenshot:', 'payhobe'); ?></strong><br>
                <a href="<?php echo esc_url(wp_get_attachment_url($payment->payment_screenshot)); ?>" target="_blank">
                    <?php esc_html_e('View Screenshot', 'payhobe'); ?>
                </a>
            </p>
            <?php endif; ?>
        </div>
        <style>
            .payhobe-payment-info p { margin-bottom: 10px; }
            .payhobe-status { padding: 2px 8px; border-radius: 3px; font-size: 11px; }
            .payhobe-status-pending { background: #f0ad4e; color: #fff; }
            .payhobe-status-confirmed { background: #5cb85c; color: #fff; }
            .payhobe-status-failed { background: #d9534f; color: #fff; }
        </style>
        <?php
    }
    
    /**
     * Sync order status when payment is updated
     *
     * @param int $payment_id Payment ID
     * @param array $data Updated data
     * @param object $old_payment Old payment data
     */
    public function sync_order_status($payment_id, $data, $old_payment) {
        if (!isset($data['payment_status']) || !$old_payment || !$old_payment->order_id) {
            return;
        }
        
        $order = wc_get_order($old_payment->order_id);
        if (!$order) {
            return;
        }
        
        switch ($data['payment_status']) {
            case 'confirmed':
                if (!$order->is_paid()) {
                    $order->payment_complete($old_payment->transaction_id);
                    $order->add_order_note(sprintf(
                        __('PayHobe payment confirmed. Transaction ID: %s', 'payhobe'),
                        $old_payment->transaction_id
                    ));
                }
                break;
                
            case 'failed':
                if ($order->get_status() !== 'failed') {
                    $order->update_status('failed', __('PayHobe payment failed.', 'payhobe'));
                }
                break;
                
            case 'refunded':
                if ($order->get_status() !== 'refunded') {
                    $order->update_status('refunded', __('PayHobe payment refunded.', 'payhobe'));
                }
                break;
        }
    }
    
    /**
     * Thank you page content
     *
     * @param int $order_id Order ID
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'payhobe_') !== 0) {
            return;
        }
        
        $payment = $this->get_payment_for_order($order_id);
        
        if ($payment && $payment->payment_status === 'pending') {
            ?>
            <div class="payhobe-thankyou-pending">
                <h3><?php esc_html_e('Payment Verification in Progress', 'payhobe'); ?></h3>
                <p><?php esc_html_e('We have received your payment details. Your payment is being verified.', 'payhobe'); ?></p>
                <p><strong><?php esc_html_e('Transaction ID:', 'payhobe'); ?></strong> <?php echo esc_html($payment->transaction_id); ?></p>
                <p><?php esc_html_e('You will receive an email confirmation once your payment is verified.', 'payhobe'); ?></p>
                
                <div class="payhobe-status-check" data-order-id="<?php echo esc_attr($order_id); ?>">
                    <p class="payhobe-checking"><?php esc_html_e('Checking payment status...', 'payhobe'); ?></p>
                </div>
            </div>
            <script>
            jQuery(function($) {
                var checkCount = 0;
                var maxChecks = 12; // Check for 2 minutes
                
                function checkStatus() {
                    if (checkCount >= maxChecks) return;
                    checkCount++;
                    
                    $.post('<?php echo esc_url(PayHobe_REST_API::get_api_url('payments/check')); ?>', {
                        order_id: <?php echo (int) $order_id; ?>
                    }, function(response) {
                        if (response.success && response.data.status === 'confirmed') {
                            $('.payhobe-thankyou-pending').html(
                                '<h3><?php echo esc_js(__('Payment Confirmed!', 'payhobe')); ?></h3>' +
                                '<p><?php echo esc_js(__('Your payment has been successfully verified. Thank you!', 'payhobe')); ?></p>'
                            );
                        } else if (checkCount < maxChecks) {
                            setTimeout(checkStatus, 10000);
                        }
                    });
                }
                
                setTimeout(checkStatus, 5000);
            });
            </script>
            <?php
        } elseif ($payment && $payment->payment_status === 'confirmed') {
            ?>
            <div class="payhobe-thankyou-confirmed">
                <h3><?php esc_html_e('Payment Confirmed!', 'payhobe'); ?></h3>
                <p><?php esc_html_e('Your payment has been successfully verified. Thank you!', 'payhobe'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Modify order received text
     *
     * @param string $text Original text
     * @param WC_Order $order Order object
     * @return string Modified text
     */
    public function order_received_text($text, $order) {
        if (!$order) {
            return $text;
        }
        
        $payment_method = $order->get_payment_method();
        if (strpos($payment_method, 'payhobe_') !== 0) {
            return $text;
        }
        
        $payment = $this->get_payment_for_order($order->get_id());
        
        if ($payment && $payment->payment_status === 'pending') {
            return __('Thank you. We have received your payment details and are verifying your payment.', 'payhobe');
        } elseif ($payment && $payment->payment_status === 'confirmed') {
            return __('Thank you. Your payment has been confirmed!', 'payhobe');
        }
        
        return $text;
    }
    
    /**
     * Get payment record for order
     *
     * @param int $order_id Order ID
     * @return object|null Payment object or null
     */
    private function get_payment_for_order($order_id) {
        $payments = PayHobe_Database::get_payments(array(
            'order_id' => $order_id,
            'limit' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ));
        
        return !empty($payments) ? $payments[0] : null;
    }
}
