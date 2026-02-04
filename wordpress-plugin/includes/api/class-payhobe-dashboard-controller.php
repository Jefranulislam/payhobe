<?php
/**
 * PayHobe Dashboard Controller
 *
 * Handles dashboard-related API endpoints
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard Controller class
 */
class PayHobe_Dashboard_Controller {
    
    /**
     * Route namespace
     */
    private $namespace;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->namespace = PayHobe_REST_API::get_namespace();
    }
    
    /**
     * Register routes
     */
    public function register_routes() {
        // Dashboard overview
        register_rest_route($this->namespace, '/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_overview'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Dashboard stats
        register_rest_route($this->namespace, '/dashboard/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'period' => array(
                    'type' => 'string',
                    'default' => '7days',
                    'enum' => array('today', '7days', '30days', 'this_month', 'last_month', 'custom')
                ),
                'date_from' => array('type' => 'string'),
                'date_to' => array('type' => 'string')
            )
        ));
        
        // Recent payments
        register_rest_route($this->namespace, '/dashboard/recent-payments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_recent_payments'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'limit' => array('type' => 'integer', 'default' => 10)
            )
        ));
        
        // Pending payments
        register_rest_route($this->namespace, '/dashboard/pending', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pending_payments'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
        
        // Chart data
        register_rest_route($this->namespace, '/dashboard/chart', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_chart_data'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'period' => array('type' => 'string', 'default' => '7days'),
                'type' => array('type' => 'string', 'default' => 'daily', 'enum' => array('daily', 'hourly'))
            )
        ));
        
        // Activity log
        register_rest_route($this->namespace, '/dashboard/activity', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_activity_log'),
            'permission_callback' => array($this, 'check_merchant_permission'),
            'args' => array(
                'limit' => array('type' => 'integer', 'default' => 20)
            )
        ));
        
        // System health
        register_rest_route($this->namespace, '/dashboard/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_system_health'),
            'permission_callback' => array($this, 'check_merchant_permission')
        ));
    }
    
    /**
     * Check merchant permission
     */
    public function check_merchant_permission($request) {
        $user_id = PayHobe_REST_API::authenticate_request($request);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        if (!PayHobe_REST_API::is_merchant($user_id)) {
            return new WP_Error('payhobe_forbidden', __('Access denied.', 'payhobe'), array('status' => 403));
        }
        
        return true;
    }
    
    /**
     * Get dashboard overview
     */
    public function get_overview($request) {
        global $wpdb;
        
        $payments_table = PayHobe_Database::get_table_name('payments');
        $sms_table = PayHobe_Database::get_table_name('sms_logs');
        
        $today = date('Y-m-d 00:00:00');
        $week_ago = date('Y-m-d 00:00:00', strtotime('-7 days'));
        
        // Today's stats
        $today_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as count,
                SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as confirmed_amount,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM $payments_table 
            WHERE created_at >= %s
        ", $today));
        
        // This week's stats
        $week_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as count,
                SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as confirmed_amount
            FROM $payments_table 
            WHERE created_at >= %s
        ", $week_ago));
        
        // Total stats
        $total_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as count,
                SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as confirmed_amount
            FROM $payments_table
        ");
        
        // Unprocessed SMS count
        $unprocessed_sms = $wpdb->get_var("
            SELECT COUNT(*) FROM $sms_table WHERE is_processed = 0
        ");
        
        // Pending payments requiring action
        $pending_payments = $wpdb->get_var("
            SELECT COUNT(*) FROM $payments_table WHERE payment_status = 'pending'
        ");
        
        // Methods breakdown (this week)
        $by_method = $wpdb->get_results($wpdb->prepare("
            SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as amount
            FROM $payments_table 
            WHERE created_at >= %s
            GROUP BY payment_method
        ", $week_ago));
        
        $methods_data = array();
        foreach ($by_method as $m) {
            $methods_data[$m->payment_method] = array(
                'count' => (int) $m->count,
                'amount' => (float) $m->amount
            );
        }
        
        return PayHobe_REST_API::success_response(array(
            'today' => array(
                'payments' => (int) $today_stats->count,
                'amount' => (float) $today_stats->confirmed_amount,
                'pending' => (int) $today_stats->pending_count
            ),
            'week' => array(
                'payments' => (int) $week_stats->count,
                'amount' => (float) $week_stats->confirmed_amount
            ),
            'total' => array(
                'payments' => (int) $total_stats->count,
                'amount' => (float) $total_stats->confirmed_amount
            ),
            'alerts' => array(
                'pending_payments' => (int) $pending_payments,
                'unprocessed_sms' => (int) $unprocessed_sms
            ),
            'by_method' => $methods_data,
            'currency' => get_option('payhobe_currency', 'BDT')
        ));
    }
    
    /**
     * Get detailed stats
     */
    public function get_stats($request) {
        $period = $request->get_param('period');
        list($date_from, $date_to) = $this->get_date_range($period, $request);
        
        $stats = PayHobe_Database::get_payment_stats(array(
            'date_from' => $date_from,
            'date_to' => $date_to
        ));
        
        // Calculate success rate
        $success_rate = 0;
        if ($stats['summary']->total_payments > 0) {
            $success_rate = round(
                ($stats['summary']->confirmed_payments / $stats['summary']->total_payments) * 100,
                1
            );
        }
        
        return PayHobe_REST_API::success_response(array(
            'period' => array(
                'from' => $date_from,
                'to' => $date_to
            ),
            'summary' => array(
                'total_payments' => (int) $stats['summary']->total_payments,
                'confirmed' => (int) $stats['summary']->confirmed_payments,
                'pending' => (int) $stats['summary']->pending_payments,
                'failed' => (int) $stats['summary']->failed_payments,
                'total_amount' => (float) $stats['summary']->total_amount,
                'pending_amount' => (float) $stats['summary']->pending_amount,
                'success_rate' => $success_rate
            ),
            'by_method' => $stats['by_method'],
            'currency' => get_option('payhobe_currency', 'BDT')
        ));
    }
    
    /**
     * Get recent payments
     */
    public function get_recent_payments($request) {
        $limit = min($request->get_param('limit'), 50);
        
        $payments = PayHobe_Database::get_payments(array(
            'limit' => $limit,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ));
        
        $formatted = array_map(function($payment) {
            return array(
                'id' => $payment->payment_id,
                'order_id' => $payment->order_id,
                'method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id,
                'amount' => (float) $payment->amount,
                'status' => $payment->payment_status,
                'created_at' => $payment->created_at
            );
        }, $payments);
        
        return PayHobe_REST_API::success_response($formatted);
    }
    
    /**
     * Get pending payments
     */
    public function get_pending_payments($request) {
        $payments = PayHobe_Database::get_payments(array(
            'status' => 'pending',
            'limit' => 100,
            'orderby' => 'created_at',
            'order' => 'ASC'
        ));
        
        $formatted = array_map(function($payment) {
            $age_minutes = round((time() - strtotime($payment->created_at)) / 60);
            
            return array(
                'id' => $payment->payment_id,
                'order_id' => $payment->order_id,
                'method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id,
                'sender' => $payment->sender_number 
                    ? PayHobe_Encryption::mask_phone($payment->sender_number)
                    : null,
                'amount' => (float) $payment->amount,
                'customer_email' => $payment->customer_email,
                'created_at' => $payment->created_at,
                'age_minutes' => $age_minutes,
                'urgent' => $age_minutes > 60
            );
        }, $payments);
        
        return PayHobe_REST_API::success_response($formatted);
    }
    
    /**
     * Get chart data
     */
    public function get_chart_data($request) {
        global $wpdb;
        
        $period = $request->get_param('period');
        $type = $request->get_param('type');
        
        list($date_from, $date_to) = $this->get_date_range($period, $request);
        
        $table = PayHobe_Database::get_table_name('payments');
        
        if ($type === 'hourly') {
            // Hourly data for today
            $data = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00') as period,
                    COUNT(*) as count,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as amount
                FROM $table 
                WHERE created_at >= %s AND created_at <= %s
                GROUP BY DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00')
                ORDER BY period ASC
            ", $date_from, $date_to));
        } else {
            // Daily data
            $data = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    DATE(created_at) as period,
                    COUNT(*) as count,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as amount,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM $table 
                WHERE created_at >= %s AND created_at <= %s
                GROUP BY DATE(created_at)
                ORDER BY period ASC
            ", $date_from, $date_to));
        }
        
        // Format for charting
        $labels = array();
        $payments = array();
        $amounts = array();
        $pending = array();
        
        foreach ($data as $row) {
            $labels[] = $row->period;
            $payments[] = (int) $row->count;
            $amounts[] = (float) $row->amount;
            if (isset($row->pending)) {
                $pending[] = (int) $row->pending;
            }
        }
        
        return PayHobe_REST_API::success_response(array(
            'labels' => $labels,
            'datasets' => array(
                'payments' => $payments,
                'amounts' => $amounts,
                'pending' => $pending
            )
        ));
    }
    
    /**
     * Get activity log
     */
    public function get_activity_log($request) {
        global $wpdb;
        
        $limit = min($request->get_param('limit'), 100);
        $table = PayHobe_Database::get_table_name('transaction_logs');
        
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, p.transaction_id, p.payment_method, p.amount
            FROM $table l
            LEFT JOIN " . PayHobe_Database::get_table_name('payments') . " p 
                ON l.payment_id = p.payment_id
            ORDER BY l.created_at DESC
            LIMIT %d
        ", $limit));
        
        $formatted = array_map(function($log) {
            return array(
                'id' => $log->log_id,
                'payment_id' => $log->payment_id,
                'action' => $log->action,
                'old_status' => $log->old_status,
                'new_status' => $log->new_status,
                'transaction_id' => $log->transaction_id,
                'method' => $log->payment_method,
                'amount' => $log->amount ? (float) $log->amount : null,
                'notes' => $log->notes,
                'performed_by' => $log->performed_by,
                'created_at' => $log->created_at
            );
        }, $logs);
        
        return PayHobe_REST_API::success_response($formatted);
    }
    
    /**
     * Get system health
     */
    public function get_system_health($request) {
        global $wpdb;
        
        $health = array(
            'status' => 'healthy',
            'checks' => array()
        );
        
        // Database connection
        $health['checks']['database'] = array(
            'name' => 'Database Connection',
            'status' => $wpdb->check_connection() ? 'ok' : 'error',
            'message' => $wpdb->check_connection() ? 'Connected' : 'Connection failed'
        );
        
        // Tables exist
        $tables = array('payments', 'mfs_config', 'sms_logs', 'api_tokens', 'transaction_logs');
        $tables_ok = true;
        foreach ($tables as $table) {
            $table_name = PayHobe_Database::get_table_name($table);
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if (!$exists) {
                $tables_ok = false;
                break;
            }
        }
        $health['checks']['tables'] = array(
            'name' => 'Database Tables',
            'status' => $tables_ok ? 'ok' : 'error',
            'message' => $tables_ok ? 'All tables exist' : 'Some tables are missing'
        );
        
        // WooCommerce
        $wc_active = class_exists('WooCommerce');
        $health['checks']['woocommerce'] = array(
            'name' => 'WooCommerce',
            'status' => $wc_active ? 'ok' : 'warning',
            'message' => $wc_active ? 'Active' : 'Not active'
        );
        
        // Encryption
        $encryption_ok = extension_loaded('openssl');
        $health['checks']['encryption'] = array(
            'name' => 'Encryption (OpenSSL)',
            'status' => $encryption_ok ? 'ok' : 'error',
            'message' => $encryption_ok ? 'Available' : 'OpenSSL not available'
        );
        
        // Payment methods configured
        $merchant_id = get_option('payhobe_merchant_user_id');
        $configs = PayHobe_Database::get_mfs_config($merchant_id);
        $enabled_count = 0;
        foreach ($configs as $config) {
            if ($config->is_enabled) {
                $enabled_count++;
            }
        }
        $health['checks']['payment_methods'] = array(
            'name' => 'Payment Methods',
            'status' => $enabled_count > 0 ? 'ok' : 'warning',
            'message' => $enabled_count > 0 ? "$enabled_count method(s) enabled" : 'No methods enabled'
        );
        
        // SMS webhook
        $webhook_secret = get_option('payhobe_sms_webhook_secret');
        $health['checks']['sms_webhook'] = array(
            'name' => 'SMS Webhook',
            'status' => !empty($webhook_secret) ? 'ok' : 'warning',
            'message' => !empty($webhook_secret) ? 'Configured' : 'Not configured'
        );
        
        // Disk space for uploads
        $upload_dir = wp_upload_dir();
        $free_space = @disk_free_space($upload_dir['basedir']);
        $health['checks']['disk_space'] = array(
            'name' => 'Disk Space',
            'status' => $free_space > 100 * 1024 * 1024 ? 'ok' : 'warning',
            'message' => $free_space ? size_format($free_space) . ' free' : 'Unknown'
        );
        
        // Determine overall status
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'error') {
                $health['status'] = 'error';
                break;
            } elseif ($check['status'] === 'warning' && $health['status'] !== 'error') {
                $health['status'] = 'warning';
            }
        }
        
        $health['version'] = PAYHOBE_VERSION;
        $health['php_version'] = PHP_VERSION;
        $health['wp_version'] = get_bloginfo('version');
        $health['wc_version'] = $wc_active ? WC()->version : null;
        
        return PayHobe_REST_API::success_response($health);
    }
    
    /**
     * Get date range from period
     */
    private function get_date_range($period, $request) {
        $date_to = date('Y-m-d 23:59:59');
        
        switch ($period) {
            case 'today':
                $date_from = date('Y-m-d 00:00:00');
                break;
            case '7days':
                $date_from = date('Y-m-d 00:00:00', strtotime('-6 days'));
                break;
            case '30days':
                $date_from = date('Y-m-d 00:00:00', strtotime('-29 days'));
                break;
            case 'this_month':
                $date_from = date('Y-m-01 00:00:00');
                break;
            case 'last_month':
                $date_from = date('Y-m-01 00:00:00', strtotime('first day of last month'));
                $date_to = date('Y-m-t 23:59:59', strtotime('last day of last month'));
                break;
            case 'custom':
                $date_from = $request->get_param('date_from') ?: date('Y-m-d 00:00:00', strtotime('-7 days'));
                $date_to = $request->get_param('date_to') ?: date('Y-m-d 23:59:59');
                break;
            default:
                $date_from = date('Y-m-d 00:00:00', strtotime('-6 days'));
        }
        
        return array($date_from, $date_to);
    }
}
