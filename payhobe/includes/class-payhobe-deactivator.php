<?php
/**
 * PayHobe Deactivator
 *
 * Handles plugin deactivation tasks
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deactivator class
 */
class PayHobe_Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Clear all scheduled cron events
     */
    private static function clear_scheduled_events() {
        $events = array(
            'payhobe_cleanup_sms_logs',
            'payhobe_check_pending_payments',
            'payhobe_auto_verify_payments'
        );
        
        foreach ($events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
        }
    }
    
    /**
     * Uninstall the plugin (called from uninstall.php)
     *
     * @param bool $delete_data Whether to delete all data
     */
    public static function uninstall($delete_data = false) {
        if ($delete_data) {
            // Drop database tables
            PayHobe_Database::drop_tables();
            
            // Delete options
            self::delete_options();
            
            // Delete upload directory
            self::delete_upload_directory();
        }
    }
    
    /**
     * Delete all plugin options
     */
    private static function delete_options() {
        global $wpdb;
        
        // Delete all options starting with payhobe_
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'payhobe_%'");
        
        // Clear any transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_payhobe_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_payhobe_%'");
    }
    
    /**
     * Delete upload directory
     */
    private static function delete_upload_directory() {
        $upload_dir = wp_upload_dir();
        $payhobe_dir = $upload_dir['basedir'] . '/payhobe';
        
        if (file_exists($payhobe_dir)) {
            self::recursive_delete($payhobe_dir);
        }
    }
    
    /**
     * Recursively delete directory
     *
     * @param string $dir Directory path
     */
    private static function recursive_delete($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (is_dir($dir . '/' . $object)) {
                        self::recursive_delete($dir . '/' . $object);
                    } else {
                        unlink($dir . '/' . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
