jQuery(document).ready(function($) {
    
    // Debug: Log when script loads
    console.log('LLMSGAA Help popup script loaded');
    
    // Handle Help link click - multiple selectors to ensure we catch it
    $(document).on('click', 'a', function(e) {
        // Check multiple conditions to identify the Help link
        var linkText = $(this).text().trim();
        var linkHref = $(this).attr('href');
        
        // Debug: Log all link clicks
        if (linkText === 'Help' || linkText === 'help') {
            console.log('Help link clicked:', linkText, linkHref);
        }
        
        // Check if this is the Help link by text or class
        if ((linkText === 'Group Management Help' || linkText === 'help') && (linkHref === '#' || linkHref === '' || !linkHref)) {
            e.preventDefault();
            e.stopPropagation();
            openHelpPopup();
            return false;
        }
        
        // Also check for the class
        if ($(this).hasClass('llmsgaa-help-popup-trigger')) {
            e.preventDefault();
            e.stopPropagation();
            openHelpPopup();
            return false;
        }
    });
    
    // Function to open help popup
    function openHelpPopup() {
        console.log('Opening help popup...');
        
        // Check if popup already exists
        if ($('#llmsgaa-help-popup').length > 0) {
            $('#llmsgaa-help-popup').fadeIn(200);
            return;
        }
        
        // Create overlay
        const overlay = $('<div id="llmsgaa-help-overlay" class="llmsgaa-help-overlay"></div>');
        
        // Create popup container
        const popup = $('<div id="llmsgaa-help-popup" class="llmsgaa-help-popup">' +
            '<div class="llmsgaa-help-popup-inner">' +
                '<div class="llmsgaa-help-popup-header">' +
                    '<h2>Help</h2>' +
                    '<button class="llmsgaa-help-popup-close" aria-label="Close">&times;</button>' +
                '</div>' +
                '<div class="llmsgaa-help-popup-content">' +
                    '<div class="llmsgaa-help-loading">' +
                        '<span class="llmsgaa-spinner"></span>' +
                        '<p>Loading help content...</p>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>');
        
        // Append to body
        $('body').append(overlay).append(popup);
        
        // Fade in
        overlay.fadeIn(200);
        popup.fadeIn(200);
        
        // Load content via AJAX
        $.ajax({
            url: llmsgaa_help.ajax_url,
            type: 'POST',
            data: {
                action: 'llmsgaa_get_help_content',
                nonce: llmsgaa_help.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.llmsgaa-help-popup-content').html(response.data.content);
                } else {
                    $('.llmsgaa-help-popup-content').html(
                        '<div class="llmsgaa-help-error">' +
                            '<p>' + (response.data.message || 'Error loading help content.') + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function() {
                $('.llmsgaa-help-popup-content').html(
                    '<div class="llmsgaa-help-error">' +
                        '<p>Error loading help content. Please try again.</p>' +
                    '</div>'
                );
            }
        });
    }
    
    // Close popup handlers
    $(document).on('click', '.llmsgaa-help-popup-close, #llmsgaa-help-overlay', function() {
        closeHelpPopup();
    });
    
    // Close on ESC key
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            closeHelpPopup();
        }
    });
    
    // Function to close help popup
    function closeHelpPopup() {
        $('#llmsgaa-help-popup').fadeOut(200, function() {
            $(this).remove();
        });
        $('#llmsgaa-help-overlay').fadeOut(200, function() {
            $(this).remove();
        });
    }
    
    // Prevent closing when clicking inside popup content
    $(document).on('click', '.llmsgaa-help-popup-inner', function(e) {
        e.stopPropagation();
    });
    
    // Handle clicking on popup background
    $(document).on('click', '#llmsgaa-help-popup', function(e) {
        if (e.target === this) {
            closeHelpPopup();
        }
    });
});