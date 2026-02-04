<?php
/**
 * PayHobe Database Handler
 *
 * Manages custom database tables and operations
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database class
 */
class PayHobe_Database {
    
    /**
     * Get table name with prefix
     *
     * @param string $table Table name
     * @return string Full table name
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'payhobe_' . $table;
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Payments table
        $payments_table = self::get_table_name('payments');
        $payments_sql = "CREATE TABLE $payments_table (
            payment_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            customer_phone VARCHAR(50) DEFAULT NULL,
            payment_method ENUM('bkash', 'rocket', 'nagad', 'upay', 'bank') NOT NULL,
            transaction_id VARCHAR(255) DEFAULT NULL,
            sender_number VARCHAR(50) DEFAULT NULL,
            amount DECIMAL(15, 2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) DEFAULT 'BDT',
            payment_status ENUM('pending', 'confirmed', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
            payment_screenshot BIGINT(20) UNSIGNED DEFAULT NULL,
            verification_source ENUM('sms', 'manual', 'auto') DEFAULT NULL,
            verified_by BIGINT(20) UNSIGNED DEFAULT NULL,
            verified_at DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            meta_data LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (payment_id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY payment_method (payment_method),
            KEY payment_status (payment_status),
            KEY transaction_id (transaction_id),
            KEY sender_number (sender_number),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // MFS Configuration table
        $config_table = self::get_table_name('mfs_config');
        $config_sql = "CREATE TABLE $config_table (
            config_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            method ENUM('bkash', 'rocket', 'nagad', 'upay', 'bank') NOT NULL,
            is_enabled TINYINT(1) DEFAULT 0,
            account_type ENUM('personal', 'merchant', 'agent') DEFAULT 'personal',
            account_number VARCHAR(255) DEFAULT NULL,
            account_name VARCHAR(255) DEFAULT NULL,
            bank_name VARCHAR(255) DEFAULT NULL,
            branch_name VARCHAR(255) DEFAULT NULL,
            routing_number VARCHAR(50) DEFAULT NULL,
            instructions_en TEXT DEFAULT NULL,
            instructions_bn TEXT DEFAULT NULL,
            sms_parser_enabled TINYINT(1) DEFAULT 0,
            sms_keywords TEXT DEFAULT NULL,
            webhook_secret VARCHAR(255) DEFAULT NULL,
            meta_data LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (config_id),
            UNIQUE KEY user_method (user_id, method),
            KEY user_id (user_id),
            KEY method (method),
            KEY is_enabled (is_enabled)
        ) $charset_collate;";
        
        // SMS Logs table
        $sms_table = self::get_table_name('sms_logs');
        $sms_sql = "CREATE TABLE $sms_table (
            sms_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            sender_number VARCHAR(255) NOT NULL,
            message_body LONGTEXT NOT NULL,
            parsed_transaction_id VARCHAR(255) DEFAULT NULL,
            parsed_amount DECIMAL(15, 2) DEFAULT NULL,
            parsed_sender VARCHAR(255) DEFAULT NULL,
            payment_method ENUM('bkash', 'rocket', 'nagad', 'upay', 'bank', 'unknown') DEFAULT 'unknown',
            matched_payment_id BIGINT(20) UNSIGNED DEFAULT NULL,
            is_processed TINYINT(1) DEFAULT 0,
            source ENUM('android_forwarder', 'twilio', 'manual', 'api') DEFAULT 'api',
            received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME DEFAULT NULL,
            raw_data LONGTEXT DEFAULT NULL,
            PRIMARY KEY (sms_id),
            KEY user_id (user_id),
            KEY parsed_transaction_id (parsed_transaction_id),
            KEY matched_payment_id (matched_payment_id),
            KEY is_processed (is_processed),
            KEY received_at (received_at),
            KEY payment_method (payment_method)
        ) $charset_collate;";
        
        // API Tokens table
        $tokens_table = self::get_table_name('api_tokens');
        $tokens_sql = "CREATE TABLE $tokens_table (
            token_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            token_name VARCHAR(255) DEFAULT NULL,
            permissions TEXT DEFAULT NULL,
            last_used_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            is_revoked TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (token_id),
            KEY user_id (user_id),
            KEY token_hash (token_hash),
            KEY is_revoked (is_revoked)
        ) $charset_collate;";
        
        // Transaction Logs table (for audit trail)
        $logs_table = self::get_table_name('transaction_logs');
        $logs_sql = "CREATE TABLE $logs_table (
            log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_id BIGINT(20) UNSIGNED DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            old_status VARCHAR(50) DEFAULT NULL,
            new_status VARCHAR(50) DEFAULT NULL,
            performed_by BIGINT(20) UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (log_id),
            KEY payment_id (payment_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($payments_sql);
        dbDelta($config_sql);
        dbDelta($sms_sql);
        dbDelta($tokens_sql);
        dbDelta($logs_sql);
        
        // Store database version
        update_option('payhobe_db_version', PAYHOBE_VERSION);
    }
    
    /**
     * Drop all plugin tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            'payments',
            'mfs_config',
            'sms_logs',
            'api_tokens',
            'transaction_logs'
        );
        
        foreach ($tables as $table) {
            $table_name = self::get_table_name($table);
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
    }
    
    /**
     * Insert payment record
     *
     * @param array $data Payment data
     * @return int|false Payment ID or false on failure
     */
    public static function insert_payment($data) {
        global $wpdb;
        
        $table = self::get_table_name('payments');
        
        $defaults = array(
            'payment_status' => 'pending',
            'currency' => 'BDT',
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Encrypt sensitive data
        if (!empty($data['sender_number'])) {
            $data['sender_number'] = PayHobe_Encryption::encrypt($data['sender_number']);
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            $payment_id = $wpdb->insert_id;
            do_action('payhobe_payment_created', $payment_id, $data);
            return $payment_id;
        }
        
        return false;
    }
    
    /**
     * Get payment by ID
     *
     * @param int $payment_id Payment ID
     * @param bool $decrypt Whether to decrypt sensitive data
     * @return object|null Payment object or null
     */
    public static function get_payment($payment_id, $decrypt = true) {
        global $wpdb;
        
        $table = self::get_table_name('payments');
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE payment_id = %d",
            $payment_id
        ));
        
        if ($payment && $decrypt) {
            $payment = self::decrypt_payment_data($payment);
        }
        
        return $payment;
    }
    
    /**
     * Get payments with filters
     *
     * @param array $args Query arguments
     * @return array Array of payment objects
     */
    public static function get_payments($args = array()) {
        global $wpdb;
        
        $table = self::get_table_name('payments');
        
        $defaults = array(
            'status' => '',
            'method' => '',
            'order_id' => '',
            'user_id' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
            'decrypt' => true
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'payment_status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['method'])) {
            $where[] = 'payment_method = %s';
            $values[] = $args['method'];
        }
        
        if (!empty($args['order_id'])) {
            $where[] = 'order_id = %d';
            $values[] = $args['order_id'];
        }
        
        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'];
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(transaction_id LIKE %s OR customer_email LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $allowed_orderby = array('created_at', 'amount', 'payment_status', 'payment_method');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        $payments = $wpdb->get_results($wpdb->prepare($sql, $values));
        
        if ($args['decrypt']) {
            foreach ($payments as $key => $payment) {
                $payments[$key] = self::decrypt_payment_data($payment);
            }
        }
        
        return $payments;
    }
    
    /**
     * Count payments with filters
     *
     * @param array $args Query arguments
     * @return int Count
     */
    public static function count_payments($args = array()) {
        global $wpdb;
        
        $table = self::get_table_name('payments');
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'payment_status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['method'])) {
            $where[] = 'payment_method = %s';
            $values[] = $args['method'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (empty($values)) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_clause");
        }
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE $where_clause",
            $values
        ));
    }
    
    /**
     * Update payment
     *
     * @param int $payment_id Payment ID
     * @param array $data Data to update
     * @return bool Success
     */
    public static function update_payment($payment_id, $data) {
        global $wpdb;
        
        $table = self::get_table_name('payments');
        
        // Get old payment for logging
        $old_payment = self::get_payment($payment_id, false);
        
        // Encrypt sensitive data if being updated
        if (!empty($data['sender_number'])) {
            $data['sender_number'] = PayHobe_Encryption::encrypt($data['sender_number']);
        }
        
        $data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table,
            $data,
            array('payment_id' => $payment_id)
        );
        
        if ($result !== false) {
            // Log status changes
            if (isset($data['payment_status']) && $old_payment && $old_payment->payment_status !== $data['payment_status']) {
                self::log_transaction($payment_id, 'status_change', array(
                    'old_status' => $old_payment->payment_status,
                    'new_status' => $data['payment_status']
                ));
            }
            
            do_action('payhobe_payment_updated', $payment_id, $data, $old_payment);
            return true;
        }
        
        return false;
    }
    
    /**
     * Decrypt payment data
     *
     * @param object $payment Payment object
     * @return object Payment with decrypted data
     */
    private static function decrypt_payment_data($payment) {
        if (!empty($payment->sender_number)) {
            $payment->sender_number = PayHobe_Encryption::decrypt($payment->sender_number);
        }
        return $payment;
    }
    
    /**
     * Insert SMS log
     *
     * @param array $data SMS data
     * @return int|false SMS ID or false
     */
    public static function insert_sms_log($data) {
        global $wpdb;
        
        $table = self::get_table_name('sms_logs');
        
        // Encrypt message body
        if (!empty($data['message_body'])) {
            $data['message_body'] = PayHobe_Encryption::encrypt($data['message_body']);
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get SMS logs
     *
     * @param array $args Query arguments
     * @return array SMS logs
     */
    public static function get_sms_logs($args = array()) {
        global $wpdb;
        
        $table = self::get_table_name('sms_logs');
        
        $defaults = array(
            'user_id' => '',
            'is_processed' => '',
            'payment_method' => '',
            'limit' => 50,
            'offset' => 0,
            'decrypt' => true
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }
        
        if ($args['is_processed'] !== '') {
            $where[] = 'is_processed = %d';
            $values[] = (int) $args['is_processed'];
        }
        
        if (!empty($args['payment_method'])) {
            $where[] = 'payment_method = %s';
            $values[] = $args['payment_method'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY received_at DESC LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        $logs = $wpdb->get_results($wpdb->prepare($sql, $values));
        
        if ($args['decrypt']) {
            foreach ($logs as $key => $log) {
                if (!empty($log->message_body)) {
                    $logs[$key]->message_body = PayHobe_Encryption::decrypt($log->message_body);
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Save MFS configuration
     *
     * @param int $user_id User ID
     * @param string $method Payment method
     * @param array $data Configuration data
     * @return bool Success
     */
    public static function save_mfs_config($user_id, $method, $data) {
        global $wpdb;
        
        $table = self::get_table_name('mfs_config');
        
        // Encrypt account number
        if (!empty($data['account_number'])) {
            $data['account_number'] = PayHobe_Encryption::encrypt($data['account_number']);
        }
        
        $data['user_id'] = $user_id;
        $data['method'] = $method;
        $data['updated_at'] = current_time('mysql');
        
        // Check if exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT config_id FROM $table WHERE user_id = %d AND method = %s",
            $user_id,
            $method
        ));
        
        if ($existing) {
            return $wpdb->update($table, $data, array('config_id' => $existing)) !== false;
        } else {
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert($table, $data) !== false;
        }
    }
    
    /**
     * Get MFS configuration
     *
     * @param int $user_id User ID
     * @param string $method Payment method (optional)
     * @param bool $decrypt Whether to decrypt
     * @return object|array|null Configuration
     */
    public static function get_mfs_config($user_id, $method = '', $decrypt = true) {
        global $wpdb;
        
        $table = self::get_table_name('mfs_config');
        
        if (!empty($method)) {
            $config = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d AND method = %s",
                $user_id,
                $method
            ));
            
            if ($config && $decrypt && !empty($config->account_number)) {
                $config->account_number = PayHobe_Encryption::decrypt($config->account_number);
            }
            
            return $config;
        }
        
        $configs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if ($decrypt) {
            foreach ($configs as $key => $config) {
                if (!empty($config->account_number)) {
                    $configs[$key]->account_number = PayHobe_Encryption::decrypt($config->account_number);
                }
            }
        }
        
        return $configs;
    }
    
    /**
     * Log transaction action
     *
     * @param int $payment_id Payment ID
     * @param string $action Action name
     * @param array $data Additional data
     * @return int|false Log ID or false
     */
    public static function log_transaction($payment_id, $action, $data = array()) {
        global $wpdb;
        
        $table = self::get_table_name('transaction_logs');
        
        $log_data = array(
            'payment_id' => $payment_id,
            'action' => $action,
            'old_status' => isset($data['old_status']) ? $data['old_status'] : null,
            'new_status' => isset($data['new_status']) ? $data['new_status'] : null,
            'performed_by' => get_current_user_id() ?: null,
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            'notes' => isset($data['notes']) ? $data['notes'] : null,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $log_data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get transaction logs for a payment
     *
     * @param int $payment_id Payment ID
     * @param int $limit Number of logs to return
     * @return array Array of log objects
     */
    public static function get_transaction_logs($payment_id, $limit = 50) {
        global $wpdb;
        
        $table = self::get_table_name('transaction_logs');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE payment_id = %d ORDER BY created_at DESC LIMIT %d",
            $payment_id,
            $limit
        ));
    }
    
    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Find payment by transaction ID
     *
     * @param string $transaction_id Transaction ID
     * @param string $method Payment method
     * @return object|null Payment or null
     */
    public static function find_payment_by_transaction_id($transaction_id, $method = '') {
        global $wpdb;
        
        $table = self::get_table_name('payments');
        
        if (!empty($method)) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE transaction_id = %s AND payment_method = %s",
                $transaction_id,
                $method
            ));
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE transaction_id = %s",
            $transaction_id
        ));
    }
    
    /**
     * Get unmatched SMS logs for verification
     *
     * @param int $user_id User ID
     * @param int $minutes_ago Look back minutes
     * @return array Unmatched SMS logs
     */
    public static function get_unmatched_sms_logs($user_id, $minutes_ago = 30) {
        global $wpdb;
        
        $table = self::get_table_name('sms_logs');
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$minutes_ago} minutes"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             AND is_processed = 0 
             AND matched_payment_id IS NULL 
             AND received_at >= %s
             ORDER BY received_at DESC",
            $user_id,
            $cutoff
        ));
    }
    
    /**
     * Mark SMS as processed
     *
     * @param int $sms_id SMS log ID
     * @param int $payment_id Matched payment ID
     * @return bool Success
     */
    public static function mark_sms_processed($sms_id, $payment_id = null) {
        global $wpdb;
        
        $table = self::get_table_name('sms_logs');
        
        return $wpdb->update(
            $table,
            array(
                'is_processed' => 1,
                'matched_payment_id' => $payment_id,
                'processed_at' => current_time('mysql')
            ),
            array('sms_id' => $sms_id)
        ) !== false;
    }
    
    /**
     * Create API token
     *
     * @param int $user_id User ID
     * @param string $name Token name
     * @param array $permissions Permissions array
     * @param int $expires_days Days until expiry (0 for never)
     * @return array Token data with plain token
     */
    public static function create_api_token($user_id, $name = '', $permissions = array(), $expires_days = 0) {
        global $wpdb;
        
        $table = self::get_table_name('api_tokens');
        
        $plain_token = PayHobe_Encryption::generate_token(64);
        $token_hash = PayHobe_Encryption::hash($plain_token);
        
        $expires_at = null;
        if ($expires_days > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
        }
        
        $result = $wpdb->insert($table, array(
            'user_id' => $user_id,
            'token_hash' => $token_hash,
            'token_name' => $name,
            'permissions' => json_encode($permissions),
            'expires_at' => $expires_at,
            'created_at' => current_time('mysql')
        ));
        
        if ($result) {
            return array(
                'token_id' => $wpdb->insert_id,
                'token' => $plain_token,
                'expires_at' => $expires_at
            );
        }
        
        return false;
    }
    
    /**
     * Validate API token
     *
     * @param string $token Plain token
     * @return object|false Token data or false
     */
    public static function validate_api_token($token) {
        global $wpdb;
        
        $table = self::get_table_name('api_tokens');
        $token_hash = PayHobe_Encryption::hash($token);
        
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE token_hash = %s 
             AND is_revoked = 0 
             AND (expires_at IS NULL OR expires_at > NOW())",
            $token_hash
        ));
        
        if ($token_data) {
            // Update last used
            $wpdb->update(
                $table,
                array('last_used_at' => current_time('mysql')),
                array('token_id' => $token_data->token_id)
            );
            
            return $token_data;
        }
        
        return false;
    }
    
    /**
     * Revoke API token
     *
     * @param int $token_id Token ID
     * @return bool Success
     */
    public static function revoke_api_token($token_id) {
        global $wpdb;
        
        $table = self::get_table_name('api_tokens');
        
        return $wpdb->update(
            $table,
            array('is_revoked' => 1),
            array('token_id' => $token_id)
        ) !== false;
    }
    
    /**
     * Get user's API tokens
     *
     * @param int $user_id User ID
     * @return array|null Array of token objects or null
     */
    public static function get_user_api_token($user_id) {
        global $wpdb;
        
        $table = self::get_table_name('api_tokens');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND is_revoked = 0 ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
    }
    
    /**
     * Get all user's API tokens
     *
     * @param int $user_id User ID
     * @return array Array of token objects
     */
    public static function get_user_api_tokens($user_id) {
        global $wpdb;
        
        $table = self::get_table_name('api_tokens');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT token_id, token_name, permissions, last_used_at, expires_at, is_revoked, created_at 
             FROM $table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
    }
    
    /**
     * Revoke all tokens for a user
     *
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function revoke_user_tokens($user_id) {
        global $wpdb;
        
        $table = self::get_table_name('api_tokens');
        
        return $wpdb->update(
            $table,
            array('is_revoked' => 1),
            array('user_id' => $user_id, 'is_revoked' => 0)
        ) !== false;
    }
    
    /**
     * Get payment statistics
     *
     * @param array $args Query arguments
     * @return array Statistics
     */
    public static function get_payment_stats($args = array()) {
        global $wpdb;
        
        $table = self::get_table_name('payments');
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Total stats
        $sql = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_payments,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                    SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                    SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as total_amount,
                    SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount
                FROM $table WHERE $where_clause";
        
        if (!empty($values)) {
            $stats = $wpdb->get_row($wpdb->prepare($sql, $values));
        } else {
            $stats = $wpdb->get_row($sql);
        }
        
        // Stats by method
        $method_sql = "SELECT 
                        payment_method,
                        COUNT(*) as count,
                        SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as amount
                       FROM $table WHERE $where_clause GROUP BY payment_method";
        
        if (!empty($values)) {
            $by_method = $wpdb->get_results($wpdb->prepare($method_sql, $values));
        } else {
            $by_method = $wpdb->get_results($method_sql);
        }
        
        return array(
            'summary' => $stats,
            'by_method' => $by_method
        );
    }
}
