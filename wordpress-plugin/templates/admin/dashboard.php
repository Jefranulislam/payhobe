<?php
/**
 * PayHobe Admin Dashboard Template
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap payhobe-dashboard">
    <h1><?php esc_html_e('PayHobe Dashboard', 'payhobe'); ?></h1>
    
    <!-- Stats Cards -->
    <div class="payhobe-stats-grid">
        <div class="stats-card">
            <div class="stats-icon" style="background: #4CAF50;">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stats-content">
                <h3><?php echo esc_html(number_format($stats['confirmed_count'] ?? 0)); ?></h3>
                <p><?php esc_html_e('Confirmed Payments', 'payhobe'); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: #FF9800;">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stats-content">
                <h3><?php echo esc_html(number_format($stats['pending_count'] ?? 0)); ?></h3>
                <p><?php esc_html_e('Pending Payments', 'payhobe'); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: #2196F3;">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="stats-content">
                <h3>৳<?php echo esc_html(number_format($stats['total_confirmed'] ?? 0, 2)); ?></h3>
                <p><?php esc_html_e('Total Confirmed', 'payhobe'); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background: #9C27B0;">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="stats-content">
                <h3>৳<?php echo esc_html(number_format($stats['today_amount'] ?? 0, 2)); ?></h3>
                <p><?php esc_html_e('Today\'s Revenue', 'payhobe'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="payhobe-dashboard-grid">
        <!-- Payment Methods Breakdown -->
        <div class="dashboard-card">
            <h2><?php esc_html_e('Payment Methods', 'payhobe'); ?></h2>
            <canvas id="methodsChart" width="400" height="200"></canvas>
        </div>
        
        <!-- Recent Activity -->
        <div class="dashboard-card">
            <h2><?php esc_html_e('Recent Payments', 'payhobe'); ?></h2>
            
            <?php if (!empty($recent_payments)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'payhobe'); ?></th>
                        <th><?php esc_html_e('Method', 'payhobe'); ?></th>
                        <th><?php esc_html_e('Amount', 'payhobe'); ?></th>
                        <th><?php esc_html_e('Status', 'payhobe'); ?></th>
                        <th><?php esc_html_e('Time', 'payhobe'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_payments as $payment): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=payhobe-payments&payment_id=' . $payment->payment_id); ?>">
                                #<?php echo esc_html($payment->payment_id); ?>
                            </a>
                        </td>
                        <td>
                            <span class="payhobe-method payhobe-method-<?php echo esc_attr($payment->payment_method); ?>">
                                <?php echo esc_html(strtoupper($payment->payment_method)); ?>
                            </span>
                        </td>
                        <td>৳<?php echo esc_html(number_format($payment->amount, 2)); ?></td>
                        <td>
                            <span class="payhobe-status payhobe-status-<?php echo esc_attr($payment->payment_status); ?>">
                                <?php echo esc_html(ucfirst($payment->payment_status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(human_time_diff(strtotime($payment->created_at), current_time('timestamp')) . ' ago'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p style="margin-top:15px;">
                <a href="<?php echo admin_url('admin.php?page=payhobe-payments'); ?>" class="button">
                    <?php esc_html_e('View All Payments', 'payhobe'); ?>
                </a>
            </p>
            <?php else: ?>
            <p class="payhobe-empty"><?php esc_html_e('No payments yet.', 'payhobe'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="dashboard-card" style="margin-top:20px;">
        <h2><?php esc_html_e('Quick Actions', 'payhobe'); ?></h2>
        <div class="quick-actions">
            <a href="<?php echo admin_url('admin.php?page=payhobe-payments&status=pending'); ?>" class="button button-primary">
                <span class="dashicons dashicons-clock"></span>
                <?php esc_html_e('Review Pending', 'payhobe'); ?>
                <?php if (($stats['pending_count'] ?? 0) > 0): ?>
                    <span class="count"><?php echo esc_html($stats['pending_count']); ?></span>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=payhobe-mfs-config'); ?>" class="button">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e('MFS Settings', 'payhobe'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=payhobe-sms-logs'); ?>" class="button">
                <span class="dashicons dashicons-email"></span>
                <?php esc_html_e('SMS Logs', 'payhobe'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=payhobe-api-docs'); ?>" class="button">
                <span class="dashicons dashicons-rest-api"></span>
                <?php esc_html_e('API Docs', 'payhobe'); ?>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Methods Chart
    var ctx = document.getElementById('methodsChart');
    if (ctx && typeof Chart !== 'undefined') {
        var methodData = <?php echo json_encode($stats['by_method'] ?? array()); ?>;
        
        var labels = [];
        var data = [];
        var colors = {
            'bkash': '#E2136E',
            'nagad': '#F6921E',
            'rocket': '#8B1D82',
            'upay': '#00A0E3',
            'bank': '#333333'
        };
        var bgColors = [];
        
        for (var method in methodData) {
            labels.push(method.toUpperCase());
            data.push(methodData[method].total || 0);
            bgColors.push(colors[method] || '#999');
        }
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: bgColors
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
</script>
