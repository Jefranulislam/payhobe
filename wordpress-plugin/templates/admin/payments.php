<?php
/**
 * PayHobe Admin Payments List Template
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$current_method = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '';
?>

<div class="wrap payhobe-payments">
    <h1>
        <?php esc_html_e('Payments', 'payhobe'); ?>
        <a href="<?php echo admin_url('admin.php?page=payhobe-payments'); ?>" class="page-title-action">
            <?php esc_html_e('Refresh', 'payhobe'); ?>
        </a>
    </h1>
    
    <!-- Filters -->
    <div class="payhobe-filters">
        <form method="get">
            <input type="hidden" name="page" value="payhobe-payments">
            
            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'payhobe'); ?></option>
                <option value="pending" <?php selected($current_status, 'pending'); ?>><?php esc_html_e('Pending', 'payhobe'); ?></option>
                <option value="confirmed" <?php selected($current_status, 'confirmed'); ?>><?php esc_html_e('Confirmed', 'payhobe'); ?></option>
                <option value="failed" <?php selected($current_status, 'failed'); ?>><?php esc_html_e('Failed', 'payhobe'); ?></option>
            </select>
            
            <select name="method">
                <option value=""><?php esc_html_e('All Methods', 'payhobe'); ?></option>
                <option value="bkash" <?php selected($current_method, 'bkash'); ?>>bKash</option>
                <option value="nagad" <?php selected($current_method, 'nagad'); ?>>Nagad</option>
                <option value="rocket" <?php selected($current_method, 'rocket'); ?>>Rocket</option>
                <option value="upay" <?php selected($current_method, 'upay'); ?>>Upay</option>
                <option value="bank" <?php selected($current_method, 'bank'); ?>>Bank</option>
            </select>
            
            <input type="search" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="<?php esc_attr_e('Search Transaction ID...', 'payhobe'); ?>">
            
            <button type="submit" class="button"><?php esc_html_e('Filter', 'payhobe'); ?></button>
        </form>
        
        <div class="payhobe-export">
            <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=payhobe_export_payments'), 'payhobe_export'); ?>" class="button">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export CSV', 'payhobe'); ?>
            </a>
        </div>
    </div>
    
    <!-- Payments Table -->
    <?php if (!empty($payments)): ?>
    <table class="wp-list-table widefat fixed striped payhobe-table">
        <thead>
            <tr>
                <th class="column-id"><?php esc_html_e('ID', 'payhobe'); ?></th>
                <th class="column-order"><?php esc_html_e('Order', 'payhobe'); ?></th>
                <th class="column-method"><?php esc_html_e('Method', 'payhobe'); ?></th>
                <th class="column-transaction"><?php esc_html_e('Transaction ID', 'payhobe'); ?></th>
                <th class="column-amount"><?php esc_html_e('Amount', 'payhobe'); ?></th>
                <th class="column-status"><?php esc_html_e('Status', 'payhobe'); ?></th>
                <th class="column-customer"><?php esc_html_e('Customer', 'payhobe'); ?></th>
                <th class="column-date"><?php esc_html_e('Date', 'payhobe'); ?></th>
                <th class="column-actions"><?php esc_html_e('Actions', 'payhobe'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
            <tr data-payment-id="<?php echo esc_attr($payment->payment_id); ?>">
                <td class="column-id">
                    <a href="<?php echo admin_url('admin.php?page=payhobe-payments&payment_id=' . $payment->payment_id); ?>">
                        <strong>#<?php echo esc_html($payment->payment_id); ?></strong>
                    </a>
                </td>
                <td class="column-order">
                    <?php if ($payment->order_id): ?>
                    <a href="<?php echo admin_url('post.php?post=' . $payment->order_id . '&action=edit'); ?>">
                        #<?php echo esc_html($payment->order_id); ?>
                    </a>
                    <?php else: ?>
                    —
                    <?php endif; ?>
                </td>
                <td class="column-method">
                    <span class="payhobe-method payhobe-method-<?php echo esc_attr($payment->payment_method); ?>">
                        <?php echo esc_html(strtoupper($payment->payment_method)); ?>
                    </span>
                </td>
                <td class="column-transaction">
                    <code><?php echo esc_html($payment->transaction_id ?: '—'); ?></code>
                </td>
                <td class="column-amount">
                    <strong>৳<?php echo esc_html(number_format($payment->amount, 2)); ?></strong>
                </td>
                <td class="column-status">
                    <span class="payhobe-status payhobe-status-<?php echo esc_attr($payment->payment_status); ?>">
                        <?php echo esc_html(ucfirst($payment->payment_status)); ?>
                    </span>
                    <?php if ($payment->verification_source): ?>
                    <br><small>(<?php echo esc_html($payment->verification_source); ?>)</small>
                    <?php endif; ?>
                </td>
                <td class="column-customer">
                    <?php echo esc_html($payment->customer_name ?: '—'); ?>
                    <?php if ($payment->customer_email): ?>
                    <br><small><?php echo esc_html($payment->customer_email); ?></small>
                    <?php endif; ?>
                </td>
                <td class="column-date">
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->created_at))); ?>
                </td>
                <td class="column-actions">
                    <?php if ($payment->payment_status === 'pending'): ?>
                    <button class="button button-small button-primary verify-payment" data-action="confirm" data-id="<?php echo esc_attr($payment->payment_id); ?>">
                        <?php esc_html_e('Verify', 'payhobe'); ?>
                    </button>
                    <button class="button button-small reject-payment" data-action="reject" data-id="<?php echo esc_attr($payment->payment_id); ?>">
                        <?php esc_html_e('Reject', 'payhobe'); ?>
                    </button>
                    <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=payhobe-payments&payment_id=' . $payment->payment_id); ?>" class="button button-small">
                        <?php esc_html_e('View', 'payhobe'); ?>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php
    $total_pages = ceil($total / 25);
    $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    
    if ($total_pages > 1):
    ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo esc_html(sprintf(_n('%s item', '%s items', $total, 'payhobe'), number_format($total))); ?></span>
            <span class="pagination-links">
                <?php if ($current_page > 1): ?>
                <a class="prev-page button" href="<?php echo add_query_arg('paged', $current_page - 1); ?>">‹</a>
                <?php endif; ?>
                
                <span class="paging-input">
                    <?php echo esc_html($current_page); ?> / <?php echo esc_html($total_pages); ?>
                </span>
                
                <?php if ($current_page < $total_pages): ?>
                <a class="next-page button" href="<?php echo add_query_arg('paged', $current_page + 1); ?>">›</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="payhobe-empty-state">
        <span class="dashicons dashicons-portfolio"></span>
        <h2><?php esc_html_e('No payments found', 'payhobe'); ?></h2>
        <p><?php esc_html_e('Payments will appear here once customers start paying.', 'payhobe'); ?></p>
    </div>
    <?php endif; ?>
</div>
