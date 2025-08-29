/**
 * Single License Activation Frontend Handler
 * 
 * Manages the popup wizard for single license self-activation
 * 
 * @package LLMSGAA
 */

(function($) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function() {
        
        // Cache DOM elements
        const $modal = $('#llmsgaa-activation-modal');
        const $activateBtn = $('#llmsgaa-activate-license-btn');
        const $modalClose = $('.llmsgaa-modal-close');
        const $wizardSteps = $('.llmsgaa-wizard-step');
        
        // Get pass data from the container
        const $container = $('.llmsgaa-single-license-activation');
        let passData = {};
        
        try {
            const passDataStr = $container.attr('data-pass-data');
            if (passDataStr) {
                passData = JSON.parse(passDataStr);
            }
        } catch(e) {
            console.error('Failed to parse pass data:', e);
        }

        // State management
        let currentStep = 1;
        let userChoice = null;
        
        /**
         * Show specific wizard step
         */
        function showStep(stepId) {
            $wizardSteps.hide();
            $('#' + stepId).fadeIn(300);
        }
        
        /**
         * Open the modal
         */
        function openModal() {
            // Update product title in the modal
            if (passData && passData.product_title) {
                $('.product-title').text(passData.product_title);
            }
            
            // Reset to first step
            currentStep = 1;
            userChoice = null;
            showStep('llmsgaa-wizard-step-1');
            
            // Show modal with fade effect
            $modal.fadeIn(300);
            
            // Prevent body scroll
            $('body').css('overflow', 'hidden');
        }
        
        /**
         * Close the modal
         */
        function closeModal() {
            $modal.fadeOut(300);
            $('body').css('overflow', '');
            
            // Reset forms
            $('#llmsgaa-self-activation-form')[0].reset();
            $('#llmsgaa-gift-form')[0].reset();
        }
        
        /**
         * Show loading state
         */
        function showLoading() {
            showStep('llmsgaa-wizard-loading');
        }
        
        /**
         * Show success state
         */
        function showSuccess(message) {
            $('.llmsgaa-success-message').text(message);
            showStep('llmsgaa-wizard-success');
        }
        
        /**
         * Show error message
         */
        function showError(message) {
            alert('Error: ' + message); // You can replace this with a better error display
        }
        
        // Event Handlers
        
        // Open modal when activate button is clicked
        $activateBtn.on('click', function(e) {
            e.preventDefault();
            openModal();
        });
        
        // Close modal handlers
        $modalClose.on('click', function(e) {
            e.preventDefault();
            closeModal();
        });
        
        // Close on overlay click
        $modal.on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.is(':visible')) {
                closeModal();
            }
        });
        
        // Handle wizard choice buttons (Self vs Gift)
        $('.llmsgaa-wizard-option').on('click', function() {
            userChoice = $(this).data('choice');
            
            if (userChoice === 'self') {
                showStep('llmsgaa-wizard-step-2-self');
            } else if (userChoice === 'gift') {
                showStep('llmsgaa-wizard-step-2-gift');
            }
        });
        
        // Handle back buttons
        $('.llmsgaa-wizard-back').on('click', function(e) {
            e.preventDefault();
            showStep('llmsgaa-wizard-step-1');
        });
        
        // Handle self-activation form submission
        $('#llmsgaa-self-activation-form').on('submit', function(e) {
            e.preventDefault();
            
            const startDate = $('#llmsgaa-start-date').val();
            
            if (!startDate) {
                showError('Please select a start date');
                return;
            }
            
            if (!passData || !passData.pass_id) {
                showError('Invalid license data');
                return;
            }
            
            // Show loading state
            showLoading();
            
            // Make AJAX request
            $.ajax({
                url: llmsgaa_single.ajax_url,
                type: 'POST',
                data: {
                    action: 'llmsgaa_activate_single_license',
                    nonce: llmsgaa_single.nonce,
                    pass_id: passData.pass_id,
                    start_date: startDate
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        
                        // Hide the activation button since license is now activated
                        $container.fadeOut();
                        
                        // Optionally redirect after a delay
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 3000);
                        }
                    } else {
                        showError(response.data || 'Failed to activate license');
                        showStep('llmsgaa-wizard-step-2-self');
                    }
                },
                error: function() {
                    showError('Network error. Please try again.');
                    showStep('llmsgaa-wizard-step-2-self');
                }
            });
        });
        
        // Handle gift form submission
        $('#llmsgaa-gift-form').on('submit', function(e) {
            e.preventDefault();
            
            const recipientEmail = $('#llmsgaa-recipient-email').val();
            const personalMessage = $('#llmsgaa-recipient-message').val();
            
            if (!recipientEmail) {
                showError('Please enter recipient email');
                return;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(recipientEmail)) {
                showError('Please enter a valid email address');
                return;
            }
            
            if (!passData || !passData.pass_id) {
                showError('Invalid license data');
                return;
            }
            
            // Show loading state
            showLoading();
            
            // Make AJAX request
            $.ajax({
                url: llmsgaa_single.ajax_url,
                type: 'POST',
                data: {
                    action: 'llmsgaa_gift_single_license',
                    nonce: llmsgaa_single.nonce,
                    pass_id: passData.pass_id,
                    recipient_email: recipientEmail,
                    personal_message: personalMessage
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        
                        // Hide the activation button since license is now gifted
                        $container.fadeOut();
                    } else {
                        showError(response.data || 'Failed to send gift');
                        showStep('llmsgaa-wizard-step-2-gift');
                    }
                },
                error: function() {
                    showError('Network error. Please try again.');
                    showStep('llmsgaa-wizard-step-2-gift');
                }
            });
        });
        
        // Handle success modal close
        $('.llmsgaa-modal-close-success').on('click', function(e) {
            e.preventDefault();
            closeModal();
        });
        
        // Date input enhancement - show day of week
        $('#llmsgaa-start-date').on('change', function() {
            const date = new Date(this.value);
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dayOfWeek = days[date.getDay()];
            
            // Update help text with day of week
            const $helpText = $(this).siblings('.llmsgaa-help-text');
            if (dayOfWeek) {
                $helpText.text('Your access will begin on ' + dayOfWeek + ', ' + this.value);
            }
        });
        
        // Auto-focus on first input when step changes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const $target = $(mutation.target);
                    if ($target.hasClass('llmsgaa-wizard-step') && $target.is(':visible')) {
                        // Focus on first input in the visible step
                        setTimeout(function() {
                            $target.find('input:first').focus();
                        }, 100);
                    }
                }
            });
        });
        
        // Observe each wizard step for visibility changes
        $wizardSteps.each(function() {
            observer.observe(this, { attributes: true });
        });
        
        // Add animation classes for smooth transitions
        $modal.addClass('llmsgaa-modal-animated');
        
        // Validate date is not in the past
        $('#llmsgaa-start-date').on('blur', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                $(this).val(today.toISOString().split('T')[0]);
                showError('Start date cannot be in the past');
            }
        });
        
        // Add keyboard navigation support
        $(document).on('keydown', function(e) {
            if (!$modal.is(':visible')) return;
            
            // Tab navigation enhancement
            if (e.key === 'Tab') {
                const $focusableElements = $modal.find('button:visible, input:visible, textarea:visible, .llmsgaa-modal-close:visible');
                const $focused = $(':focus');
                const focusedIndex = $focusableElements.index($focused);
                
                if (e.shiftKey) {
                    // Shift+Tab - move backwards
                    if (focusedIndex <= 0) {
                        e.preventDefault();
                        $focusableElements.last().focus();
                    }
                } else {
                    // Tab - move forwards
                    if (focusedIndex === $focusableElements.length - 1) {
                        e.preventDefault();
                        $focusableElements.first().focus();
                    }
                }
            }
        });
        
        // Initialize tooltips if needed
        if ($.fn.tooltip) {
            $('[data-toggle="tooltip"]').tooltip();
        }
        
        // Handle window resize to keep modal centered
        $(window).on('resize', function() {
            if ($modal.is(':visible')) {
                // Modal should auto-center with CSS, but you can add adjustments here if needed
            }
        });
        
        // Cleanup on page unload
        $(window).on('beforeunload', function() {
            if ($modal.is(':visible')) {
                closeModal();
            }
        });
        
    });
    
})(jQuery);