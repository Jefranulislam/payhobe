<?php
/**
 * PayHobe Admin SMS Logs Template
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap payhobe-sms-logs">
    <h1><?php esc_html_e('SMS Logs', 'payhobe'); ?></h1>
    
    <p><?php esc_html_e('View SMS messages received from your MFS accounts. These are used for automatic payment verification.', 'payhobe'); ?></p>
    
    <!-- Webhook Info -->
    <div class="notice notice-info">
        <p>
            <strong><?php esc_html_e('SMS Webhook URL:', 'payhobe'); ?></strong>
            <code><?php echo esc_url(rest_url('payhobe/v1/sms/receive')); ?></code>
            <button type="button" class="button button-small copy-url" data-url="<?php echo esc_url(rest_url('payhobe/v1/sms/receive')); ?>">
                <?php esc_html_e('Copy', 'payhobe'); ?>
            </button>
        </p>
        <p class="description"><?php esc_html_e('Use this URL in your SMS Forwarder app to send messages to PayHobe.', 'payhobe'); ?></p>
    </div>
    
    <!-- Filters -->
    <div class="payhobe-filters">
        <form method="get">
            <input type="hidden" name="page" value="payhobe-sms-logs">
            
            <select name="method">
                <option value=""><?php esc_html_e('All Methods', 'payhobe'); ?></option>
                <option value="bkash" <?php selected($_GET['method'] ?? '', 'bkash'); ?>>bKash</option>
                <option value="nagad" <?php selected($_GET['method'] ?? '', 'nagad'); ?>>Nagad</option>
                <option value="rocket" <?php selected($_GET['method'] ?? '', 'rocket'); ?>>Rocket</option>
                <option value="upay" <?php selected($_GET['method'] ?? '', 'upay'); ?>>Upay</option>
            </select>
            
            <select name="processed">
                <option value=""><?php esc_html_e('All', 'payhobe'); ?></option>
                <option value="yes" <?php selected($_GET['processed'] ?? '', 'yes'); ?>><?php esc_html_e('Processed', 'payhobe'); ?></option>
                <option value="no" <?php selected($_GET['processed'] ?? '', 'no'); ?>><?php esc_html_e('Unprocessed', 'payhobe'); ?></option>
            </select>
            
            <button type="submit" class="button"><?php esc_html_e('Filter', 'payhobe'); ?></button>
        </form>
    </div>
    
    <!-- SMS Logs Table -->
    <?php if (!empty($logs)): ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="column-id"><?php esc_html_e('ID', 'payhobe'); ?></th>
                <th class="column-sender"><?php esc_html_e('Sender', 'payhobe'); ?></th>
                <th class="column-method"><?php esc_html_e('Method', 'payhobe'); ?></th>
                <th class="column-message"><?php esc_html_e('Message', 'payhobe'); ?></th>
                <th class="column-parsed"><?php esc_html_e('Parsed Data', 'payhobe'); ?></th>
                <th class="column-status"><?php esc_html_e('Status', 'payhobe'); ?></th>
                <th class="column-received"><?php esc_html_e('Received', 'payhobe'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): 
                $sender = $log->sender_number ? PayHobe_Encryption::decrypt($log->sender_number) : '—';
                $message = $log->message_body ? PayHobe_Encryption::decrypt($log->message_body) : '—';
            ?>
            <tr>
                <td class="column-id"><?php echo esc_html($log->sms_id); ?></td>
                <td class="column-sender">
                    <code><?php echo esc_html($sender); ?></code>
                </td>
                <td class="column-method">
                    <?php if ($log->payment_method && $log->payment_method !== 'unknown'): ?>
                    <span class="payhobe-method payhobe-method-<?php echo esc_attr($log->payment_method); ?>">
                        <?php echo esc_html(strtoupper($log->payment_method)); ?>
                    </span>
                    <?php else: ?>
                    <span class="payhobe-method">?</span>
                    <?php endif; ?>
                </td>
                <td class="column-message">
                    <div class="sms-message-preview" title="<?php echo esc_attr($message); ?>">
                        <?php echo esc_html(wp_trim_words($message, 20, '...')); ?>
                    </div>
                </td>
                <td class="column-parsed">
                    <?php if ($log->parsed_transaction_id): ?>
                    <strong>TrxID:</strong> <code><?php echo esc_html($log->parsed_transaction_id); ?></code><br>
                    <?php endif; ?>
                    <?php if ($log->parsed_amount): ?>
                    <strong>Amount:</strong> ৳<?php echo esc_html(number_format($log->parsed_amount, 2)); ?><br>
                    <?php endif; ?>
                    <?php if ($log->parsed_sender): ?>
                    <strong>From:</strong> <?php echo esc_html($log->parsed_sender); ?>
                    <?php endif; ?>
                </td>
                <td class="column-status">
                    <?php if ($log->is_processed): ?>
                    <span class="payhobe-status payhobe-status-confirmed">
                        <?php esc_html_e('Processed', 'payhobe'); ?>
                    </span>
                    <?php if ($log->matched_payment_id): ?>
                    <br><small>
                        <a href="<?php echo admin_url('admin.php?page=payhobe-payments&payment_id=' . $log->matched_payment_id); ?>">
                            → Payment #<?php echo esc_html($log->matched_payment_id); ?>
                        </a>
                    </small>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="payhobe-status payhobe-status-pending">
                        <?php esc_html_e('Pending', 'payhobe'); ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td class="column-received">
                    <?php echo esc_html(human_time_diff(strtotime($log->received_at), current_time('timestamp')) . ' ago'); ?>
                    <br><small><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($log->received_at))); ?></small>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="payhobe-empty-state">
        <span class="dashicons dashicons-email"></span>
        <h2><?php esc_html_e('No SMS logs yet', 'payhobe'); ?></h2>
        <p><?php esc_html_e('SMS messages from your MFS accounts will appear here once you configure SMS forwarding.', 'payhobe'); ?></p>
        <a href="<?php echo admin_url('admin.php?page=payhobe-settings&tab=automation'); ?>" class="button button-primary">
            <?php esc_html_e('Configure SMS Integration', 'payhobe'); ?>
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Manual SMS Entry -->
    <div class="payhobe-manual-sms" style="margin-top:30px;">
        <h3><?php esc_html_e('Manual SMS Entry', 'payhobe'); ?></h3>
        <p><?php esc_html_e('Manually enter an SMS message for testing or when forwarding is not available.', 'payhobe'); ?></p>
        
        <form id="manual-sms-form" class="manual-sms-form">
            <div class="form-row">
                <label><?php esc_html_e('Sender Number', 'payhobe'); ?></label>
                <input type="text" name="sender" placeholder="bKash, Nagad, 16216, etc." required>
            </div>
            <div class="form-row">
                <label><?php esc_html_e('Message', 'payhobe'); ?></label>
                <textarea name="message" rows="4" placeholder="<?php esc_attr_e('Paste the full SMS message here...', 'payhobe'); ?>" required></textarea>
            </div>
            <button type="submit" class="button button-primary"><?php esc_html_e('Submit SMS', 'payhobe'); ?></button>
            <span class="spinner"></span>
            <span class="submit-status"></span>
        </form>
    </div>
</div>

<script>
jQuery(function($) {
    // Copy URL
    $('.copy-url').on('click', function() {
        var url = $(this).data('url');
        navigator.clipboard.writeText(url);
        $(this).text('<?php esc_attr_e('Copied!', 'payhobe'); ?>');
        setTimeout(function() {
            $('.copy-url').text('<?php esc_attr_e('Copy', 'payhobe'); ?>');
        }, 2000);
    });
    
    // Manual SMS form
    $('#manual-sms-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $spinner = $form.find('.spinner');
        var $status = $form.find('.submit-status');
        
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $status.text('');
        
        $.ajax({
            url: payhobe_admin.rest_url + 'sms/manual',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            data: {
                sender: $form.find('[name="sender"]').val(),
                message: $form.find('[name="message"]').val()
            },
            success: function(response) {
                $status.html('<span style="color:green;">✓ SMS submitted successfully</span>');
                $form[0].reset();
                // Reload page after 1 second
                setTimeout(function() {
                    location.reload();
                }, 1000);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error';
                $status.html('<span style="color:red;">' + msg + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>
