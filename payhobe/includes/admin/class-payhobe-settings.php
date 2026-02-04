<?php
/**
 * PayHobe Settings
 *
 * Handles plugin settings and onboarding
 *
 * @package PayHobe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class
 */
class PayHobe_Settings {
    
    /**
     * Render settings page
     *
     * @param string $tab Current tab
     */
    public function render($tab) {
        $tabs = array(
            'general' => __('General', 'payhobe'),
            'automation' => __('Automation', 'payhobe'),
            'notifications' => __('Notifications', 'payhobe'),
            'api' => __('API Access', 'payhobe'),
            'advanced' => __('Advanced', 'payhobe'),
            'onboarding' => __('Setup Wizard', 'payhobe')
        );
        
        ?>
        <div class="wrap payhobe-settings">
            <h1><?php echo esc_html__('PayHobe Settings', 'payhobe'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_id => $tab_name): ?>
                    <a href="<?php echo admin_url('admin.php?page=payhobe-settings&tab=' . $tab_id); ?>"
                       class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_name); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <div class="payhobe-settings-content">
                <?php
                switch ($tab) {
                    case 'general':
                        $this->render_general_settings();
                        break;
                    case 'automation':
                        $this->render_automation_settings();
                        break;
                    case 'notifications':
                        $this->render_notification_settings();
                        break;
                    case 'api':
                        $this->render_api_settings();
                        break;
                    case 'advanced':
                        $this->render_advanced_settings();
                        break;
                    case 'onboarding':
                        $this->render_onboarding();
                        break;
                    default:
                        $this->render_general_settings();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render general settings
     */
    private function render_general_settings() {
        $merchant_id = get_option('payhobe_merchant_user_id', get_current_user_id());
        $currency = get_option('payhobe_currency', 'BDT');
        
        ?>
        <form class="payhobe-settings-form" data-ajax-save="true">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="payhobe_merchant_user_id"><?php esc_html_e('Merchant Account', 'payhobe'); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_dropdown_users(array(
                            'name' => 'settings[payhobe_merchant_user_id]',
                            'id' => 'payhobe_merchant_user_id',
                            'selected' => $merchant_id,
                            'show_option_none' => __('Select User', 'payhobe'),
                            'role__in' => array('administrator', 'shop_manager')
                        ));
                        ?>
                        <p class="description"><?php esc_html_e('Select the WordPress user who will receive payments and manage the plugin.', 'payhobe'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="payhobe_currency"><?php esc_html_e('Currency', 'payhobe'); ?></label>
                    </th>
                    <td>
                        <select name="settings[payhobe_currency]" id="payhobe_currency">
                            <option value="BDT" <?php selected($currency, 'BDT'); ?>>BDT (৳)</option>
                        </select>
                        <p class="description"><?php esc_html_e('Currency for payments. Currently only BDT is supported.', 'payhobe'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'payhobe'); ?></button>
            </p>
        </form>
        <?php
    }
    
    /**
     * Render automation settings
     */
    private function render_automation_settings() {
        $auto_verify = get_option('payhobe_auto_verify', true);
        $pending_timeout = get_option('payhobe_pending_timeout_hours', 24);
        $sms_webhook = get_option('payhobe_sms_webhook_enabled', true);
        $twilio_enabled = get_option('payhobe_twilio_enabled', false);
        $twilio_sid = get_option('payhobe_twilio_sid', '');
        $twilio_phone = get_option('payhobe_twilio_phone', '');
        
        ?>
        <form class="payhobe-settings-form" data-ajax-save="true">
            <h2><?php esc_html_e('Payment Verification', 'payhobe'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Auto-Verify Payments', 'payhobe'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[payhobe_auto_verify]" value="1" <?php checked($auto_verify); ?>>
                            <?php esc_html_e('Automatically verify payments when matching SMS is received', 'payhobe'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('When enabled, payments will be confirmed automatically if a matching SMS transaction is found.', 'payhobe'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="payhobe_pending_timeout_hours"><?php esc_html_e('Payment Timeout', 'payhobe'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="settings[payhobe_pending_timeout_hours]" id="payhobe_pending_timeout_hours" 
                               value="<?php echo esc_attr($pending_timeout); ?>" min="1" max="168" class="small-text"> <?php esc_html_e('hours', 'payhobe'); ?>
                        <p class="description"><?php esc_html_e('Pending payments will be marked as failed after this time.', 'payhobe'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h2><?php esc_html_e('SMS Integration', 'payhobe'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('SMS Webhook', 'payhobe'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[payhobe_sms_webhook_enabled]" value="1" <?php checked($sms_webhook); ?>>
                            <?php esc_html_e('Enable SMS Webhook for Android SMS Forwarder', 'payhobe'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Webhook URL:', 'payhobe'); ?>
                            <code><?php echo esc_url(rest_url('payhobe/v1/sms/receive')); ?></code>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Twilio Integration', 'payhobe'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[payhobe_twilio_enabled]" value="1" <?php checked($twilio_enabled); ?> id="payhobe_twilio_enabled">
                            <?php esc_html_e('Enable Twilio SMS Integration', 'payhobe'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr class="twilio-settings" style="<?php echo $twilio_enabled ? '' : 'display:none;'; ?>">
                    <th scope="row">
                        <label for="payhobe_twilio_sid"><?php esc_html_e('Twilio Account SID', 'payhobe'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[payhobe_twilio_sid]" id="payhobe_twilio_sid" 
                               value="<?php echo esc_attr($twilio_sid); ?>" class="regular-text">
                    </td>
                </tr>
                
                <tr class="twilio-settings" style="<?php echo $twilio_enabled ? '' : 'display:none;'; ?>">
                    <th scope="row">
                        <label for="payhobe_twilio_token"><?php esc_html_e('Twilio Auth Token', 'payhobe'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="settings[payhobe_twilio_token]" id="payhobe_twilio_token" 
                               value="" class="regular-text" placeholder="<?php esc_attr_e('Enter new token to update', 'payhobe'); ?>">
                        <p class="description"><?php esc_html_e('Leave blank to keep existing token.', 'payhobe'); ?></p>
                    </td>
                </tr>
                
                <tr class="twilio-settings" style="<?php echo $twilio_enabled ? '' : 'display:none;'; ?>">
                    <th scope="row">
                        <label for="payhobe_twilio_phone"><?php esc_html_e('Twilio Phone Number', 'payhobe'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="settings[payhobe_twilio_phone]" id="payhobe_twilio_phone" 
                               value="<?php echo esc_attr($twilio_phone); ?>" class="regular-text" placeholder="+1234567890">
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'payhobe'); ?></button>
            </p>
        </form>
        
        <script>
        jQuery(function($) {
            $('#payhobe_twilio_enabled').on('change', function() {
                $('.twilio-settings').toggle(this.checked);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render notification settings
     */
    private function render_notification_settings() {
        $email_enabled = get_option('payhobe_email_notifications', true);
        
        ?>
        <form class="payhobe-settings-form" data-ajax-save="true">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Email Notifications', 'payhobe'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[payhobe_email_notifications]" value="1" <?php checked($email_enabled); ?>>
                            <?php esc_html_e('Send email notifications for payment events', 'payhobe'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Notify merchant and customer when payments are verified.', 'payhobe'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'payhobe'); ?></button>
            </p>
        </form>
        <?php
    }
    
    /**
     * Render API settings
     */
    private function render_api_settings() {
        $user_id = get_current_user_id();
        $token_info = PayHobe_Database::get_user_api_token($user_id);
        
        ?>
        <div class="payhobe-api-settings">
            <h2><?php esc_html_e('API Access', 'payhobe'); ?></h2>
            
            <p><?php esc_html_e('Use API tokens to authenticate requests from your Next.js dashboard or other applications.', 'payhobe'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('REST API Endpoint', 'payhobe'); ?></th>
                    <td>
                        <code><?php echo esc_url(rest_url('payhobe/v1/')); ?></code>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Current API Token', 'payhobe'); ?></th>
                    <td>
                        <?php if ($token_info): ?>
                            <p>
                                <strong><?php echo esc_html($token_info->token_name); ?></strong><br>
                                <small><?php esc_html_e('Created:', 'payhobe'); ?> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token_info->created_at))); ?></small>
                            </p>
                            <p class="description"><?php esc_html_e('Token is hidden for security. Generate a new one if needed.', 'payhobe'); ?></p>
                        <?php else: ?>
                            <p class="description"><?php esc_html_e('No API token generated yet.', 'payhobe'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Generate New Token', 'payhobe'); ?></th>
                    <td>
                        <input type="text" id="token_name" placeholder="<?php esc_attr_e('Token name (optional)', 'payhobe'); ?>" class="regular-text">
                        <button type="button" class="button" id="regenerate_token"><?php esc_html_e('Generate New Token', 'payhobe'); ?></button>
                        
                        <div id="new_token_display" style="display:none; margin-top:15px;">
                            <div class="notice notice-warning inline">
                                <p><strong><?php esc_html_e('Copy your new token now! It won\'t be shown again.', 'payhobe'); ?></strong></p>
                            </div>
                            <input type="text" id="new_token_value" class="large-text" readonly>
                            <button type="button" class="button" id="copy_token"><?php esc_html_e('Copy', 'payhobe'); ?></button>
                        </div>
                    </td>
                </tr>
            </table>
            
            <h3><?php esc_html_e('Authentication', 'payhobe'); ?></h3>
            <p><?php esc_html_e('Include the token in your requests using the Authorization header:', 'payhobe'); ?></p>
            <pre><code>Authorization: Bearer YOUR_API_TOKEN</code></pre>
        </div>
        
        <script>
        jQuery(function($) {
            $('#regenerate_token').on('click', function() {
                if (!confirm('<?php esc_attr_e('This will revoke your existing token. Continue?', 'payhobe'); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php esc_attr_e('Generating...', 'payhobe'); ?>');
                
                $.post(payhobe_admin.ajax_url, {
                    action: 'payhobe_regenerate_token',
                    nonce: payhobe_admin.nonce,
                    token_name: $('#token_name').val() || 'API Token'
                }, function(response) {
                    if (response.success) {
                        $('#new_token_value').val(response.data.token);
                        $('#new_token_display').show();
                    } else {
                        alert(response.data.message);
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('<?php esc_attr_e('Generate New Token', 'payhobe'); ?>');
                });
            });
            
            $('#copy_token').on('click', function() {
                $('#new_token_value').select();
                document.execCommand('copy');
                $(this).text('<?php esc_attr_e('Copied!', 'payhobe'); ?>');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render advanced settings
     */
    private function render_advanced_settings() {
        $debug_mode = get_option('payhobe_debug_mode', false);
        
        ?>
        <form class="payhobe-settings-form" data-ajax-save="true">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Debug Mode', 'payhobe'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="settings[payhobe_debug_mode]" value="1" <?php checked($debug_mode); ?>>
                            <?php esc_html_e('Enable debug logging', 'payhobe'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Logs will be written to WooCommerce logs.', 'payhobe'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h2><?php esc_html_e('Data Management', 'payhobe'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Export Data', 'payhobe'); ?></th>
                    <td>
                        <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=payhobe_export_data'), 'payhobe_export'); ?>" class="button">
                            <?php esc_html_e('Export All Payment Data', 'payhobe'); ?>
                        </a>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Clear SMS Logs', 'payhobe'); ?></th>
                    <td>
                        <button type="button" class="button" id="clear_sms_logs">
                            <?php esc_html_e('Clear Old SMS Logs (30+ days)', 'payhobe'); ?>
                        </button>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'payhobe'); ?></button>
            </p>
        </form>
        <?php
    }
    
    /**
     * Render onboarding wizard
     */
    private function render_onboarding() {
        $step = isset($_GET['step']) ? absint($_GET['step']) : 1;
        $setup_complete = get_option('payhobe_setup_complete', false);
        
        ?>
        <div class="payhobe-onboarding">
            <h2><?php esc_html_e('PayHobe Setup Wizard', 'payhobe'); ?></h2>
            
            <?php if ($setup_complete): ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e('Setup is complete! You can reconfigure settings below.', 'payhobe'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="payhobe-wizard-steps">
                <div class="wizard-step <?php echo $step >= 1 ? 'active' : ''; ?>" data-step="1">
                    <span class="step-number">1</span>
                    <span class="step-title"><?php esc_html_e('Welcome', 'payhobe'); ?></span>
                </div>
                <div class="wizard-step <?php echo $step >= 2 ? 'active' : ''; ?>" data-step="2">
                    <span class="step-number">2</span>
                    <span class="step-title"><?php esc_html_e('MFS Accounts', 'payhobe'); ?></span>
                </div>
                <div class="wizard-step <?php echo $step >= 3 ? 'active' : ''; ?>" data-step="3">
                    <span class="step-number">3</span>
                    <span class="step-title"><?php esc_html_e('SMS Setup', 'payhobe'); ?></span>
                </div>
                <div class="wizard-step <?php echo $step >= 4 ? 'active' : ''; ?>" data-step="4">
                    <span class="step-number">4</span>
                    <span class="step-title"><?php esc_html_e('Dashboard', 'payhobe'); ?></span>
                </div>
            </div>
            
            <div class="wizard-content">
                <?php
                switch ($step) {
                    case 1:
                        $this->render_onboarding_step1();
                        break;
                    case 2:
                        $this->render_onboarding_step2();
                        break;
                    case 3:
                        $this->render_onboarding_step3();
                        break;
                    case 4:
                        $this->render_onboarding_step4();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Onboarding Step 1: Welcome
     */
    private function render_onboarding_step1() {
        ?>
        <div class="wizard-step-content">
            <h3><?php esc_html_e('Welcome to PayHobe!', 'payhobe'); ?></h3>
            
            <p><?php esc_html_e('PayHobe enables you to accept payments via Bangladeshi Mobile Financial Services (MFS) including bKash, Nagad, Rocket, Upay, and bank transfers.', 'payhobe'); ?></p>
            
            <h4><?php esc_html_e('How it works:', 'payhobe'); ?></h4>
            <ol>
                <li><?php esc_html_e('Configure your MFS account numbers', 'payhobe'); ?></li>
                <li><?php esc_html_e('Customers send payments directly to your accounts', 'payhobe'); ?></li>
                <li><?php esc_html_e('Payments are verified automatically via SMS parsing or manually by you', 'payhobe'); ?></li>
                <li><?php esc_html_e('WooCommerce orders are updated automatically', 'payhobe'); ?></li>
            </ol>
            
            <h4><?php esc_html_e('Requirements:', 'payhobe'); ?></h4>
            <ul>
                <li><?php esc_html_e('WooCommerce installed and activated', 'payhobe'); ?> 
                    <?php echo class_exists('WooCommerce') ? '<span class="dashicons dashicons-yes" style="color:green;"></span>' : '<span class="dashicons dashicons-no" style="color:red;"></span>'; ?>
                </li>
                <li><?php esc_html_e('At least one MFS account (bKash, Nagad, etc.)', 'payhobe'); ?></li>
                <li><?php esc_html_e('(Optional) Android phone for SMS forwarding', 'payhobe'); ?></li>
            </ul>
            
            <p class="wizard-actions">
                <a href="<?php echo admin_url('admin.php?page=payhobe-settings&tab=onboarding&step=2'); ?>" class="button button-primary button-hero">
                    <?php esc_html_e('Get Started', 'payhobe'); ?> →
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Onboarding Step 2: MFS Accounts
     */
    private function render_onboarding_step2() {
        ?>
        <div class="wizard-step-content">
            <h3><?php esc_html_e('Configure Your MFS Accounts', 'payhobe'); ?></h3>
            
            <p><?php esc_html_e('Enter your MFS account details. Enable at least one payment method to continue.', 'payhobe'); ?></p>
            
            <div class="mfs-accordion">
                <?php
                $methods = array(
                    'bkash' => array('name' => 'bKash', 'color' => '#E2136E'),
                    'nagad' => array('name' => 'Nagad', 'color' => '#F6921E'),
                    'rocket' => array('name' => 'Rocket', 'color' => '#8B1D82'),
                    'upay' => array('name' => 'Upay', 'color' => '#00A0E3'),
                    'bank' => array('name' => 'Bank Transfer', 'color' => '#333')
                );
                
                foreach ($methods as $method => $info):
                    $config = PayHobe_Database::get_mfs_config(get_current_user_id(), $method);
                    $is_enabled = $config && $config->is_enabled;
                    $account = $config ? PayHobe_Encryption::decrypt($config->account_number) : '';
                ?>
                <div class="mfs-method" data-method="<?php echo esc_attr($method); ?>">
                    <div class="mfs-method-header" style="border-left-color: <?php echo esc_attr($info['color']); ?>">
                        <label>
                            <input type="checkbox" class="mfs-enable" <?php checked($is_enabled); ?>>
                            <strong><?php echo esc_html($info['name']); ?></strong>
                        </label>
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    <div class="mfs-method-body" style="<?php echo $is_enabled ? '' : 'display:none;'; ?>">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Account Number', 'payhobe'); ?></th>
                                <td>
                                    <input type="text" class="mfs-account-number regular-text" 
                                           value="<?php echo esc_attr($account); ?>" 
                                           placeholder="01XXXXXXXXX">
                                </td>
                            </tr>
                            <?php if ($method !== 'bank'): ?>
                            <tr>
                                <th><?php esc_html_e('Account Type', 'payhobe'); ?></th>
                                <td>
                                    <select class="mfs-account-type">
                                        <option value="personal" <?php selected($config && $config->account_type === 'personal'); ?>><?php esc_html_e('Personal', 'payhobe'); ?></option>
                                        <option value="merchant" <?php selected($config && $config->account_type === 'merchant'); ?>><?php esc_html_e('Merchant', 'payhobe'); ?></option>
                                        <option value="agent" <?php selected($config && $config->account_type === 'agent'); ?>><?php esc_html_e('Agent', 'payhobe'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <th><?php esc_html_e('Bank Name', 'payhobe'); ?></th>
                                <td>
                                    <input type="text" class="mfs-bank-name regular-text" 
                                           value="<?php echo esc_attr($config->bank_name ?? ''); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Account Holder', 'payhobe'); ?></th>
                                <td>
                                    <input type="text" class="mfs-account-holder regular-text" 
                                           value="<?php echo esc_attr($config->account_holder_name ?? ''); ?>">
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        <button type="button" class="button save-mfs-config"><?php esc_html_e('Save', 'payhobe'); ?></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <p class="wizard-actions">
                <a href="<?php echo admin_url('admin.php?page=payhobe-settings&tab=onboarding&step=1'); ?>" class="button">
                    ← <?php esc_html_e('Back', 'payhobe'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=payhobe-settings&tab=onboarding&step=3'); ?>" class="button button-primary">
                    <?php esc_html_e('Continue', 'payhobe'); ?> →
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Onboarding Step 3: SMS Setup
     */
    private function render_onboarding_step3() {
        ?>
        <div class="wizard-step-content">
            <h3><?php esc_html_e('SMS Integration Setup', 'payhobe'); ?></h3>
            
            <p><?php esc_html_e('To enable automatic payment verification, PayHobe can receive SMS notifications from your MFS accounts.', 'payhobe'); ?></p>
            
            <div class="sms-options">
                <div class="sms-option">
                    <h4><span class="dashicons dashicons-smartphone"></span> <?php esc_html_e('Android SMS Forwarder (Recommended)', 'payhobe'); ?></h4>
                    <p><?php esc_html_e('Use an Android app to forward SMS messages to PayHobe automatically.', 'payhobe'); ?></p>
                    
                    <ol>
                        <li><?php esc_html_e('Install "SMS Forwarder" app from Play Store', 'payhobe'); ?></li>
                        <li><?php esc_html_e('Configure webhook URL:', 'payhobe'); ?> <code><?php echo esc_url(rest_url('payhobe/v1/sms/receive')); ?></code></li>
                        <li><?php esc_html_e('Add filter for bKash/Nagad/Rocket sender numbers', 'payhobe'); ?></li>
                        <li><?php esc_html_e('Test by sending a small amount to yourself', 'payhobe'); ?></li>
                    </ol>
                    
                    <a href="https://play.google.com/store/apps/details?id=com.sms.forwarder" target="_blank" class="button">
                        <?php esc_html_e('Get SMS Forwarder App', 'payhobe'); ?>
                    </a>
                </div>
                
                <div class="sms-option">
                    <h4><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Manual Verification', 'payhobe'); ?></h4>
                    <p><?php esc_html_e('You can also verify payments manually from the PayHobe dashboard.', 'payhobe'); ?></p>
                    
                    <ol>
                        <li><?php esc_html_e('Customer submits payment with Transaction ID', 'payhobe'); ?></li>
                        <li><?php esc_html_e('You check the transaction in your MFS app', 'payhobe'); ?></li>
                        <li><?php esc_html_e('Confirm or reject from PayHobe dashboard', 'payhobe'); ?></li>
                    </ol>
                </div>
            </div>
            
            <p class="wizard-actions">
                <a href="<?php echo admin_url('admin.php?page=payhobe-settings&tab=onboarding&step=2'); ?>" class="button">
                    ← <?php esc_html_e('Back', 'payhobe'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=payhobe-settings&tab=onboarding&step=4'); ?>" class="button button-primary">
                    <?php esc_html_e('Continue', 'payhobe'); ?> →
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Onboarding Step 4: Dashboard Setup
     */
    private function render_onboarding_step4() {
        $user_id = get_current_user_id();
        $token_info = PayHobe_Database::get_user_api_token($user_id);
        
        ?>
        <div class="wizard-step-content">
            <h3><?php esc_html_e('Dashboard Setup', 'payhobe'); ?></h3>
            
            <p><?php esc_html_e('PayHobe includes a modern Next.js dashboard for managing payments. You can also use the WordPress admin pages.', 'payhobe'); ?></p>
            
            <h4><?php esc_html_e('Option 1: Use WordPress Admin', 'payhobe'); ?></h4>
            <p><?php esc_html_e('Access payments directly from WordPress:', 'payhobe'); ?></p>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=payhobe'); ?>"><?php esc_html_e('Dashboard', 'payhobe'); ?></a></li>
                <li><a href="<?php echo admin_url('admin.php?page=payhobe-payments'); ?>"><?php esc_html_e('Payments', 'payhobe'); ?></a></li>
            </ul>
            
            <h4><?php esc_html_e('Option 2: Deploy Next.js Dashboard', 'payhobe'); ?></h4>
            <p><?php esc_html_e('For a better experience, deploy the separate Next.js dashboard:', 'payhobe'); ?></p>
            
            <?php if (!$token_info): ?>
                <p><strong><?php esc_html_e('First, generate an API token:', 'payhobe'); ?></strong></p>
                <button type="button" class="button" id="generate_first_token"><?php esc_html_e('Generate API Token', 'payhobe'); ?></button>
                <div id="first_token_display" style="display:none; margin:15px 0;">
                    <input type="text" id="first_token_value" class="large-text" readonly>
                    <button type="button" class="button" id="copy_first_token"><?php esc_html_e('Copy', 'payhobe'); ?></button>
                    <p class="description"><?php esc_html_e('Save this token! Use it to configure your dashboard.', 'payhobe'); ?></p>
                </div>
            <?php else: ?>
                <p class="description"><?php esc_html_e('You already have an API token. Go to Settings → API Access to manage it.', 'payhobe'); ?></p>
            <?php endif; ?>
            
            <h4><?php esc_html_e('API Endpoint', 'payhobe'); ?></h4>
            <code><?php echo esc_url(rest_url('payhobe/v1/')); ?></code>
            
            <hr style="margin:30px 0;">
            
            <div class="notice notice-success inline" style="margin:0;">
                <p><strong><?php esc_html_e('Setup Complete! 🎉', 'payhobe'); ?></strong></p>
                <p><?php esc_html_e('PayHobe is now ready to accept payments.', 'payhobe'); ?></p>
            </div>
            
            <p class="wizard-actions" style="margin-top:20px;">
                <a href="<?php echo admin_url('admin.php?page=payhobe'); ?>" class="button button-primary button-hero">
                    <?php esc_html_e('Go to Dashboard', 'payhobe'); ?>
                </a>
            </p>
        </div>
        
        <?php
        // Mark setup as complete
        update_option('payhobe_setup_complete', true);
        update_option('payhobe_merchant_user_id', get_current_user_id());
        ?>
        
        <script>
        jQuery(function($) {
            $('#generate_first_token').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php esc_attr_e('Generating...', 'payhobe'); ?>');
                
                $.post(payhobe_admin.ajax_url, {
                    action: 'payhobe_regenerate_token',
                    nonce: payhobe_admin.nonce,
                    token_name: 'Dashboard Token'
                }, function(response) {
                    if (response.success) {
                        $('#first_token_value').val(response.data.token);
                        $('#first_token_display').show();
                        $btn.hide();
                    } else {
                        alert(response.data.message || 'Failed to generate token');
                        $btn.prop('disabled', false).text('<?php esc_attr_e('Generate API Token', 'payhobe'); ?>');
                    }
                }).fail(function(xhr, status, error) {
                    console.error('Token generation failed:', status, error);
                    alert('Failed to generate token. Please check browser console for details.');
                    $btn.prop('disabled', false).text('<?php esc_attr_e('Generate API Token', 'payhobe'); ?>');
                });
            });
            
            $('#copy_first_token').on('click', function() {
                $('#first_token_value').select();
                document.execCommand('copy');
                $(this).text('<?php esc_attr_e('Copied!', 'payhobe'); ?>');
            });
        });
        </script>
        <?php
    }
}
