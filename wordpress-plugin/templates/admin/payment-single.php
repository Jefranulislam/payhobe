<?php
/**
 * PayHobe Admin Single Payment View Template
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$sender_number = $payment->sender_number ? PayHobe_Encryption::decrypt($payment->sender_number) : '';
?>

<div class="wrap payhobe-payment-single">
    <h1>
        <a href="<?php echo admin_url('admin.php?page=payhobe-payments'); ?>" class="page-title-action">← <?php esc_html_e('Back to Payments', 'payhobe'); ?></a>
        <?php printf(esc_html__('Payment #%d', 'payhobe'), $payment->payment_id); ?>
    </h1>
    
    <div class="payhobe-payment-grid">
        <!-- Main Info -->
        <div class="payment-card payment-main">
            <div class="payment-header">
                <span class="payhobe-method payhobe-method-<?php echo esc_attr($payment->payment_method); ?> large">
                    <?php echo esc_html(strtoupper($payment->payment_method)); ?>
                </span>
                <span class="payhobe-status payhobe-status-<?php echo esc_attr($payment->payment_status); ?> large">
                    <?php echo esc_html(ucfirst($payment->payment_status)); ?>
                </span>
            </div>
            
            <div class="payment-amount">
                <span class="currency">৳</span>
                <span class="value"><?php echo esc_html(number_format($payment->amount, 2)); ?></span>
            </div>
            
            <table class="payment-details">
                <tr>
                    <th><?php esc_html_e('Transaction ID', 'payhobe'); ?></th>
                    <td><code><?php echo esc_html($payment->transaction_id ?: '—'); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Sender Number', 'payhobe'); ?></th>
                    <td><?php echo esc_html($sender_number ? PayHobe_Encryption::mask_phone($sender_number) : '—'); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Account Type', 'payhobe'); ?></th>
                    <td><?php echo esc_html(ucfirst($payment->sender_account_type ?: 'personal')); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Created', 'payhobe'); ?></th>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->created_at))); ?></td>
                </tr>
                <?php if ($payment->verified_at): ?>
                <tr>
                    <th><?php esc_html_e('Verified', 'payhobe'); ?></th>
                    <td>
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->verified_at))); ?>
                        <?php if ($payment->verification_source): ?>
                        <br><small>(<?php echo esc_html($payment->verification_source); ?>)</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <?php if ($payment->screenshot_url): ?>
            <div class="payment-screenshot">
                <h4><?php esc_html_e('Payment Screenshot', 'payhobe'); ?></h4>
                <a href="<?php echo esc_url($payment->screenshot_url); ?>" target="_blank">
                    <img src="<?php echo esc_url($payment->screenshot_url); ?>" alt="Payment Screenshot">
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($payment->notes): ?>
            <div class="payment-notes">
                <h4><?php esc_html_e('Notes', 'payhobe'); ?></h4>
                <p><?php echo esc_html($payment->notes); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="payment-sidebar">
            <!-- Actions -->
            <?php if ($payment->payment_status === 'pending'): ?>
            <div class="payment-card">
                <h3><?php esc_html_e('Actions', 'payhobe'); ?></h3>
                <form id="verify-form">
                    <textarea name="notes" placeholder="<?php esc_attr_e('Optional notes...', 'payhobe'); ?>" rows="3"></textarea>
                    <div class="action-buttons">
                        <button type="button" class="button button-primary verify-payment" data-action="confirm" data-id="<?php echo esc_attr($payment->payment_id); ?>">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Confirm Payment', 'payhobe'); ?>
                        </button>
                        <button type="button" class="button reject-payment" data-action="reject" data-id="<?php echo esc_attr($payment->payment_id); ?>">
                            <span class="dashicons dashicons-no"></span>
                            <?php esc_html_e('Reject', 'payhobe'); ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Order Info -->
            <?php if ($payment->order_id): 
                $order = wc_get_order($payment->order_id);
                if ($order):
            ?>
            <div class="payment-card">
                <h3><?php esc_html_e('Order Details', 'payhobe'); ?></h3>
                <table class="payment-details">
                    <tr>
                        <th><?php esc_html_e('Order', 'payhobe'); ?></th>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $payment->order_id . '&action=edit'); ?>">
                                #<?php echo esc_html($payment->order_id); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Status', 'payhobe'); ?></th>
                        <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Total', 'payhobe'); ?></th>
                        <td><?php echo wc_price($order->get_total()); ?></td>
                    </tr>
                </table>
            </div>
            <?php endif; endif; ?>
            
            <!-- Customer Info -->
            <div class="payment-card">
                <h3><?php esc_html_e('Customer', 'payhobe'); ?></h3>
                <table class="payment-details">
                    <tr>
                        <th><?php esc_html_e('Name', 'payhobe'); ?></th>
                        <td><?php echo esc_html($payment->customer_name ?: '—'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Email', 'payhobe'); ?></th>
                        <td><?php echo esc_html($payment->customer_email ?: '—'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Phone', 'payhobe'); ?></th>
                        <td><?php echo esc_html($payment->customer_phone ?: '—'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('IP', 'payhobe'); ?></th>
                        <td><code><?php echo esc_html($payment->customer_ip ?: '—'); ?></code></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Transaction Log -->
    <div class="payment-card" style="margin-top:20px;">
        <h3><?php esc_html_e('Activity Log', 'payhobe'); ?></h3>
        
        <?php if (!empty($logs)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Action', 'payhobe'); ?></th>
                    <th><?php esc_html_e('Details', 'payhobe'); ?></th>
                    <th><?php esc_html_e('User', 'payhobe'); ?></th>
                    <th><?php esc_html_e('Time', 'payhobe'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $log->action_type))); ?></strong></td>
                    <td>
                        <?php 
                        $meta = json_decode($log->meta_data, true);
                        if (!empty($meta['notes'])) {
                            echo esc_html($meta['notes']);
                        }
                        if (!empty($meta['old_status']) && !empty($meta['new_status'])) {
                            printf(
                                '%s → %s',
                                esc_html(ucfirst($meta['old_status'])),
                                esc_html(ucfirst($meta['new_status']))
                            );
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($log->performed_by) {
                            $user = get_user_by('ID', $log->performed_by);
                            echo esc_html($user ? $user->display_name : 'User #' . $log->performed_by);
                        } else {
                            echo '<em>' . esc_html__('System', 'payhobe') . '</em>';
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="payhobe-empty"><?php esc_html_e('No activity recorded yet.', 'payhobe'); ?></p>
        <?php endif; ?>
    </div>
</div>
