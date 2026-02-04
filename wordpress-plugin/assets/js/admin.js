/**
 * PayHobe Admin JavaScript
 *
 * @package PayHobe
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    // Initialize
    $(document).ready(function() {
        PayHobeAdmin.init();
    });
    
    var PayHobeAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initForms();
        },
        
        bindEvents: function() {
            // Verify payment buttons
            $(document).on('click', '.verify-payment, .reject-payment', this.handleVerifyPayment);
            
            // Ajax settings forms
            $(document).on('submit', '.payhobe-settings-form[data-ajax-save]', this.handleSettingsSave);
        },
        
        initForms: function() {
            // Auto-save indication
            $('.payhobe-settings-form input, .payhobe-settings-form select, .payhobe-settings-form textarea').on('change', function() {
                $(this).closest('form').addClass('has-changes');
            });
        },
        
        handleVerifyPayment: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var action = $btn.data('action');
            var paymentId = $btn.data('id');
            var confirmMsg = action === 'confirm' 
                ? payhobe_admin.strings.confirm_verify 
                : payhobe_admin.strings.confirm_reject;
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            var notes = '';
            var $form = $('#verify-form');
            if ($form.length) {
                notes = $form.find('textarea[name="notes"]').val();
            }
            
            $btn.prop('disabled', true).text(payhobe_admin.strings.saving);
            
            $.post(payhobe_admin.ajax_url, {
                action: 'payhobe_verify_payment',
                nonce: payhobe_admin.nonce,
                payment_id: paymentId,
                verify_action: action,
                notes: notes
            }, function(response) {
                if (response.success) {
                    // Reload page to show updated status
                    location.reload();
                } else {
                    alert(response.data.message || payhobe_admin.strings.error);
                    $btn.prop('disabled', false).text(action === 'confirm' ? 'Verify' : 'Reject');
                }
            }).fail(function() {
                alert(payhobe_admin.strings.error);
                $btn.prop('disabled', false).text(action === 'confirm' ? 'Verify' : 'Reject');
            });
        },
        
        handleSettingsSave: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text(payhobe_admin.strings.saving);
            
            $.post(payhobe_admin.ajax_url, {
                action: 'payhobe_save_settings',
                nonce: payhobe_admin.nonce,
                settings: $form.serializeObject()
            }, function(response) {
                if (response.success) {
                    $btn.text(payhobe_admin.strings.saved);
                    $form.removeClass('has-changes');
                    
                    setTimeout(function() {
                        $btn.text(originalText);
                    }, 2000);
                } else {
                    alert(response.data.message || payhobe_admin.strings.error);
                }
            }).fail(function() {
                alert(payhobe_admin.strings.error);
            }).always(function() {
                $btn.prop('disabled', false);
            });
        }
    };
    
    // Utility: Serialize form to object
    $.fn.serializeObject = function() {
        var obj = {};
        var arr = this.serializeArray();
        
        $.each(arr, function() {
            // Handle settings[key] format
            var match = this.name.match(/^settings\[(.+)\]$/);
            if (match) {
                obj[match[1]] = this.value;
            } else {
                obj[this.name] = this.value;
            }
        });
        
        return obj;
    };
    
    // Expose for external use
    window.PayHobeAdmin = PayHobeAdmin;
    
})(jQuery);
