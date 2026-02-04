/**
 * PayHobe Checkout JavaScript
 *
 * @package PayHobe
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    var PayHobeCheckout = {
        
        init: function() {
            this.bindEvents();
            this.initPolling();
        },
        
        bindEvents: function() {
            // Copy account number
            $(document).on('click', '.payhobe-copy-btn', this.copyToClipboard);
            
            // File upload
            $(document).on('click', '.payhobe-file-upload', function() {
                $(this).find('input[type="file"]').click();
            });
            
            $(document).on('change', '.payhobe-file-upload input[type="file"]', this.handleFileUpload);
            
            // Form validation
            $(document).on('submit', 'form.checkout', this.validatePaymentForm);
            
            // Transaction ID formatting
            $(document).on('input', 'input[name="payhobe_transaction_id"]', this.formatTransactionId);
            
            // Phone number formatting
            $(document).on('input', 'input[name="payhobe_sender_number"]', this.formatPhoneNumber);
        },
        
        copyToClipboard: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var text = $btn.data('copy') || $btn.prev('.payhobe-account-number-text').text();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text.trim()).then(function() {
                    $btn.addClass('copied').text('Copied!');
                    setTimeout(function() {
                        $btn.removeClass('copied').text('Copy');
                    }, 2000);
                });
            } else {
                // Fallback
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(text.trim()).select();
                document.execCommand('copy');
                $temp.remove();
                
                $btn.addClass('copied').text('Copied!');
                setTimeout(function() {
                    $btn.removeClass('copied').text('Copy');
                }, 2000);
            }
        },
        
        handleFileUpload: function(e) {
            var $input = $(this);
            var $container = $input.closest('.payhobe-file-upload');
            var file = this.files[0];
            
            if (!file) return;
            
            // Validate file
            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please upload an image (JPG, PNG, GIF, WebP) or PDF file.');
                $input.val('');
                return;
            }
            
            // Max 5MB
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB.');
                $input.val('');
                return;
            }
            
            // Show filename
            $container.addClass('has-file');
            $container.find('.filename').remove();
            $container.append('<div class="filename">' + file.name + '</div>');
            
            // Show preview for images
            if (file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $container.siblings('.payhobe-screenshot-preview').remove();
                    $container.after('<div class="payhobe-screenshot-preview"><img src="' + e.target.result + '" alt="Preview"></div>');
                };
                reader.readAsDataURL(file);
            }
        },
        
        validatePaymentForm: function(e) {
            var $form = $(this);
            var paymentMethod = $form.find('input[name="payment_method"]:checked').val();
            
            // Only validate PayHobe methods
            if (!paymentMethod || !paymentMethod.startsWith('payhobe_')) {
                return true;
            }
            
            var method = paymentMethod.replace('payhobe_', '');
            var errors = [];
            
            // Transaction ID (required for MFS)
            if (method !== 'bank') {
                var txnId = $form.find('input[name="payhobe_transaction_id"]').val();
                if (!txnId || txnId.trim().length < 5) {
                    errors.push('Please enter a valid Transaction ID.');
                }
            }
            
            // Screenshot (required for bank)
            if (method === 'bank') {
                var screenshot = $form.find('input[name="payhobe_screenshot"]')[0];
                if (!screenshot || !screenshot.files.length) {
                    errors.push('Please upload a payment screenshot.');
                }
            }
            
            // Phone number validation (optional but if provided, validate format)
            var phone = $form.find('input[name="payhobe_sender_number"]').val();
            if (phone && !/^01[3-9]\d{8}$/.test(phone.replace(/\D/g, ''))) {
                errors.push('Please enter a valid Bangladeshi phone number (01XXXXXXXXX).');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                
                // Remove existing errors
                $form.find('.payhobe-validation-errors').remove();
                
                // Show errors
                var $errors = $('<div class="payhobe-message error payhobe-validation-errors"><ul></ul></div>');
                errors.forEach(function(error) {
                    $errors.find('ul').append('<li>' + error + '</li>');
                });
                
                $form.find('.payhobe-payment-form').prepend($errors);
                
                // Scroll to errors
                $('html, body').animate({
                    scrollTop: $errors.offset().top - 100
                }, 300);
                
                return false;
            }
            
            return true;
        },
        
        formatTransactionId: function() {
            var $input = $(this);
            var value = $input.val().toUpperCase().replace(/[^A-Z0-9]/g, '');
            $input.val(value);
        },
        
        formatPhoneNumber: function() {
            var $input = $(this);
            var value = $input.val().replace(/\D/g, '');
            
            // Limit to 11 digits
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            
            $input.val(value);
        },
        
        initPolling: function() {
            // Check if we're on pending status page
            var $pending = $('.payhobe-pending-status');
            if (!$pending.length) return;
            
            var paymentId = $pending.data('payment-id');
            if (!paymentId) return;
            
            var checkStatus = function() {
                $.post(payhobe_checkout.ajax_url, {
                    action: 'payhobe_check_status',
                    payment_id: paymentId
                }, function(response) {
                    if (response.success && response.data.status === 'confirmed') {
                        // Payment confirmed - reload page
                        location.reload();
                    } else if (response.success && response.data.status === 'failed') {
                        // Payment failed
                        $pending.html(
                            '<div class="icon">❌</div>' +
                            '<h3>Payment Failed</h3>' +
                            '<p>Your payment could not be verified. Please contact support.</p>'
                        );
                    }
                });
            };
            
            // Poll every 10 seconds
            setInterval(checkStatus, 10000);
            
            // Also check immediately
            setTimeout(checkStatus, 2000);
        }
    };
    
    // Initialize when DOM ready
    $(document).ready(function() {
        PayHobeCheckout.init();
    });
    
    // Re-init when WooCommerce updates checkout
    $(document.body).on('updated_checkout', function() {
        PayHobeCheckout.init();
    });
    
})(jQuery);
