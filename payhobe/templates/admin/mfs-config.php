<?php
/**
 * PayHobe Admin MFS Configuration Template
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$methods = array(
    'bkash' => array('name' => 'bKash', 'color' => '#E2136E', 'icon' => 'bkash.png'),
    'nagad' => array('name' => 'Nagad', 'color' => '#F6921E', 'icon' => 'nagad.png'),
    'rocket' => array('name' => 'Rocket', 'color' => '#8B1D82', 'icon' => 'rocket.png'),
    'upay' => array('name' => 'Upay', 'color' => '#00A0E3', 'icon' => 'upay.png'),
    'bank' => array('name' => 'Bank Transfer', 'color' => '#333333', 'icon' => 'bank.png')
);
?>

<div class="wrap payhobe-mfs-config">
    <h1><?php esc_html_e('MFS Configuration', 'payhobe'); ?></h1>
    
    <p><?php esc_html_e('Configure your Mobile Financial Service (MFS) accounts. Enable the payment methods you want to offer to customers.', 'payhobe'); ?></p>
    
    <div class="mfs-methods-grid">
        <?php foreach ($methods as $method => $info): 
            $config = $configs[$method] ?? new stdClass();
            $is_enabled = isset($config->is_enabled) && $config->is_enabled;
            $account = isset($config->account_number) ? PayHobe_Encryption::decrypt($config->account_number) : '';
        ?>
        <div class="mfs-method-card" data-method="<?php echo esc_attr($method); ?>" style="border-top-color: <?php echo esc_attr($info['color']); ?>;">
            <div class="mfs-method-header">
                <div class="mfs-method-title">
                    <strong><?php echo esc_html($info['name']); ?></strong>
                </div>
                <label class="mfs-toggle">
                    <input type="checkbox" class="mfs-enable-toggle" <?php checked($is_enabled); ?>>
                    <span class="slider"></span>
                </label>
            </div>
            
            <form class="mfs-config-form" style="<?php echo $is_enabled ? '' : 'display:none;'; ?>">
                <input type="hidden" name="method" value="<?php echo esc_attr($method); ?>">
                <input type="hidden" name="is_enabled" value="1">
                
                <div class="form-field">
                    <label><?php echo $method === 'bank' ? esc_html__('Account Number', 'payhobe') : esc_html__('Mobile Number', 'payhobe'); ?></label>
                    <input type="text" name="account_number" value="<?php echo esc_attr($account); ?>" 
                           placeholder="<?php echo $method === 'bank' ? '1234567890' : '01XXXXXXXXX'; ?>" required>
                </div>
                
                <?php if ($method !== 'bank'): ?>
                <div class="form-field">
                    <label><?php esc_html_e('Account Type', 'payhobe'); ?></label>
                    <select name="account_type">
                        <option value="personal" <?php selected($config->account_type ?? '', 'personal'); ?>><?php esc_html_e('Personal', 'payhobe'); ?></option>
                        <option value="merchant" <?php selected($config->account_type ?? '', 'merchant'); ?>><?php esc_html_e('Merchant', 'payhobe'); ?></option>
                        <option value="agent" <?php selected($config->account_type ?? '', 'agent'); ?>><?php esc_html_e('Agent', 'payhobe'); ?></option>
                    </select>
                </div>
                
                <div class="form-field">
                    <label>
                        <input type="checkbox" name="is_merchant_account" value="1" <?php checked($config->is_merchant_account ?? false); ?>>
                        <?php esc_html_e('This is a merchant/business account', 'payhobe'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Merchant accounts may show the full number to customers for "Pay Merchant" feature.', 'payhobe'); ?></p>
                </div>
                
                <div class="form-field">
                    <label>
                        <input type="checkbox" name="sms_parser_enabled" value="1" <?php checked($config->sms_parser_enabled ?? true); ?>>
                        <?php esc_html_e('Enable SMS auto-verification', 'payhobe'); ?>
                    </label>
                </div>
                <?php else: ?>
                <div class="form-field">
                    <label><?php esc_html_e('Bank Name', 'payhobe'); ?></label>
                    <input type="text" name="bank_name" value="<?php echo esc_attr($config->bank_name ?? ''); ?>" placeholder="e.g., DBBL, Brac Bank">
                </div>
                
                <div class="form-field">
                    <label><?php esc_html_e('Account Holder Name', 'payhobe'); ?></label>
                    <input type="text" name="account_holder_name" value="<?php echo esc_attr($config->account_holder_name ?? ''); ?>">
                </div>
                
                <div class="form-field">
                    <label><?php esc_html_e('Branch', 'payhobe'); ?></label>
                    <input type="text" name="bank_branch" value="<?php echo esc_attr($config->bank_branch ?? ''); ?>">
                </div>
                
                <div class="form-field">
                    <label><?php esc_html_e('Routing Number', 'payhobe'); ?></label>
                    <input type="text" name="routing_number" value="<?php echo esc_attr($config->routing_number ?? ''); ?>">
                </div>
                <?php endif; ?>
                
                <div class="form-field">
                    <label><?php esc_html_e('Account Holder Name (for receipt)', 'payhobe'); ?></label>
                    <input type="text" name="account_holder_name" value="<?php echo esc_attr($config->account_holder_name ?? ''); ?>">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Configuration', 'payhobe'); ?></button>
                    <span class="spinner"></span>
                    <span class="save-status"></span>
                </div>
            </form>
            
            <div class="mfs-disabled-notice" style="<?php echo $is_enabled ? 'display:none;' : ''; ?>">
                <p><?php esc_html_e('Enable this payment method to configure it.', 'payhobe'); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
jQuery(function($) {
    // Toggle enable/disable
    $('.mfs-enable-toggle').on('change', function() {
        var $card = $(this).closest('.mfs-method-card');
        var enabled = this.checked;
        
        $card.find('.mfs-config-form').toggle(enabled);
        $card.find('.mfs-disabled-notice').toggle(!enabled);
        
        if (!enabled) {
            // Save disabled state
            var method = $card.data('method');
            $.post(payhobe_admin.ajax_url, {
                action: 'payhobe_save_mfs_config',
                nonce: payhobe_admin.nonce,
                method: method,
                is_enabled: 0
            });
        }
    });
    
    // Save form
    $('.mfs-config-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $spinner = $form.find('.spinner');
        var $status = $form.find('.save-status');
        
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $status.text('');
        
        $.post(payhobe_admin.ajax_url, {
            action: 'payhobe_save_mfs_config',
            nonce: payhobe_admin.nonce,
            method: $form.find('[name="method"]').val(),
            is_enabled: 1,
            account_number: $form.find('[name="account_number"]').val(),
            account_type: $form.find('[name="account_type"]').val(),
            is_merchant_account: $form.find('[name="is_merchant_account"]').is(':checked') ? 1 : 0,
            sms_parser_enabled: $form.find('[name="sms_parser_enabled"]').is(':checked') ? 1 : 0,
            account_holder_name: $form.find('[name="account_holder_name"]').val(),
            bank_name: $form.find('[name="bank_name"]').val(),
            bank_branch: $form.find('[name="bank_branch"]').val(),
            routing_number: $form.find('[name="routing_number"]').val()
        }, function(response) {
            if (response.success) {
                $status.html('<span style="color:green;">✓ Saved</span>');
            } else {
                $status.html('<span style="color:red;">Error: ' + response.data.message + '</span>');
            }
        }).fail(function() {
            $status.html('<span style="color:red;">Network error</span>');
        }).always(function() {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });
});
</script>
