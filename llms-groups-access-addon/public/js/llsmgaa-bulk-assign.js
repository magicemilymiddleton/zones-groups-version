/**
 * File: /public/js/llmsgaa-bulk-assign.js
 * 
 * Handles bulk assignment functionality
 */

jQuery(document).ready(function($) {
    
    // Initialize variables
    let selectedMembers = [];
    let groupId = llmsgaa_bulk.group_id;
    let ajaxUrl = llmsgaa_bulk.ajax_url;
    let ajaxNonce = llmsgaa_bulk.nonce;
    
    // Add checkboxes to member list if they don't exist
    function initializeCheckboxes() {
        // Check if we're on a page with member list
        if ($('.member-item, .llmsgaa-member-row, tr[data-member-email]').length === 0) {
            console.log('No member list found on this page');
            return;
        }
        
        // Add checkbox column header if table exists
        if ($('table thead tr').length && !$('#select-all-members').length) {
            $('table thead tr').prepend('<th><input type="checkbox" id="select-all-members" /></th>');
        }
        
        // Add checkboxes to each member row
        $('.member-item, .llmsgaa-member-row, tr[data-member-email]').each(function() {
            if ($(this).find('.member-checkbox').length === 0) {
                let email = $(this).data('member-email') || $(this).find('[data-email]').data('email') || '';
                if (email) {
                    $(this).prepend('<td><input type="checkbox" class="member-checkbox" value="' + email + '" /></td>');
                }
            }
        });
        
        console.log('Checkboxes initialized');
    }
    
    // Update bulk actions bar
    function updateBulkActionsBar() {
        const selectedCount = $('.member-checkbox:checked').length;
        
        if (selectedCount > 0) {
            // Show bulk actions bar if exists
            if ($('#bulk-actions-bar').length) {
                $('#bulk-actions-bar').slideDown(200);
                $('#selected-count').text(selectedCount);
            }
            
            // Update bulk assign button
            if ($('#llmsgaa-bulk-assign-btn').length) {
                $('#llmsgaa-bulk-assign-btn').show();
                $('#llmsgaa-bulk-assign-btn').removeClass('btn-primary').addClass('btn-success');
                $('#llmsgaa-bulk-assign-btn').html(`<span class="btn-icon">📋</span> Bulk Assign (${selectedCount} selected)`);
            }
        } else {
            // Hide bulk actions
            $('#bulk-actions-bar').slideUp(200);
            $('#llmsgaa-bulk-assign-btn').hide();
        }
    }
    
    // Handle individual checkbox change
    $(document).on('change', '.member-checkbox', function() {
        const email = $(this).val();
        const row = $(this).closest('tr, .member-item');
        
        if ($(this).prop('checked')) {
            row.addClass('selected-for-bulk');
            if (!selectedMembers.includes(email)) {
                selectedMembers.push(email);
            }
        } else {
            row.removeClass('selected-for-bulk');
            selectedMembers = selectedMembers.filter(e => e !== email);
        }
        
        updateBulkActionsBar();
        
        // Update select all checkbox
        const totalCheckboxes = $('.member-checkbox').length;
        const checkedCheckboxes = $('.member-checkbox:checked').length;
        $('#select-all-members').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        $('#select-all-members').prop('checked', checkedCheckboxes === totalCheckboxes);
    });
    
    // Handle select all
    $(document).on('change', '#select-all-members', function() {
        const isChecked = $(this).prop('checked');
        
        $('.member-checkbox').each(function() {
            $(this).prop('checked', isChecked);
            const email = $(this).val();
            const row = $(this).closest('tr, .member-item');
            
            if (isChecked) {
                row.addClass('selected-for-bulk');
                if (!selectedMembers.includes(email)) {
                    selectedMembers.push(email);
                }
            } else {
                row.removeClass('selected-for-bulk');
            }
        });
        
        if (!isChecked) {
            selectedMembers = [];
        }
        
        updateBulkActionsBar();
    });
    
    // Show bulk assign modal
    function showBulkAssignModal(selectedEmails) {
        // First, get available licenses
        $.post(ajaxUrl, {
            action: 'llmsgaa_get_available_licenses',
            group_id: groupId,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                renderBulkAssignModal(selectedEmails, response.data);
            } else {
                alert('Error loading licenses: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            alert('Failed to load licenses. Please try again.');
        });
    }
    
    // Render the modal
    function renderBulkAssignModal(selectedEmails, licenses) {
        // Remove existing modal if any
        $('#llmsgaa-bulk-modal').remove();
        
        const memberCount = selectedEmails.length;
        const licenseCount = licenses.length;
        
        if (licenseCount === 0) {
            alert('No licenses with available seats found for this group.');
            return;
        }
        
        // Build license options HTML
        let licenseOptionsHtml = '';
        licenses.forEach(function(license) {
            licenseOptionsHtml += `
                <div class="license-option">
                    <label>
                        <input type="checkbox" class="license-checkbox" value="${license.id}" data-seats="${license.available_seats}">
                        <strong>${license.title}</strong><br>
                        <small>${license.available_seats} of ${license.total_seats} seats available</small>
                    </label>
                </div>
            `;
        });
        
        // Create modal HTML
        const modalHtml = `
            <div id="llmsgaa-bulk-modal" class="llmsgaa-modal-overlay">
                <div class="llmsgaa-modal">
                    <div class="llmsgaa-modal-header">
                        <h3>Bulk Assign Licenses</h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="llmsgaa-modal-body">
                        <div class="assignment-info">
                            <p>Assigning licenses to <strong>${memberCount} members</strong></p>
                            <div class="selected-emails">
                                ${selectedEmails.map(email => `<span class="email-tag">${email}</span>`).join('')}
                            </div>
                        </div>
                        
                        <div class="license-selection">
                            <h4>Select License(s):</h4>
                            ${licenseOptionsHtml}
                        </div>
                        
                        <div class="assignment-summary" style="display:none;">
                            <p class="warning-message"></p>
                        </div>
                    </div>
                    <div class="llmsgaa-modal-footer">
                        <button class="button cancel-btn">Cancel</button>
                        <button class="button button-primary assign-btn" disabled>Assign Licenses</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        $('body').append(modalHtml);
        
        // Add basic styles if not already present
        if ($('#llmsgaa-bulk-modal-styles').length === 0) {
            $('head').append(`
                <style id="llmsgaa-bulk-modal-styles">
                    .llmsgaa-modal-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0,0,0,0.5);
                        z-index: 99999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .llmsgaa-modal {
                        background: white;
                        border-radius: 8px;
                        max-width: 600px;
                        width: 90%;
                        max-height: 80vh;
                        overflow: auto;
                    }
                    .llmsgaa-modal-header {
                        padding: 20px;
                        border-bottom: 1px solid #ddd;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    .llmsgaa-modal-body {
                        padding: 20px;
                    }
                    .llmsgaa-modal-footer {
                        padding: 20px;
                        border-top: 1px solid #ddd;
                        text-align: right;
                    }
                    .close-modal {
                        background: none;
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                    }
                    .email-tag {
                        display: inline-block;
                        background: #e0e0e0;
                        padding: 4px 8px;
                        margin: 2px;
                        border-radius: 4px;
                        font-size: 12px;
                    }
                    .license-option {
                        padding: 10px;
                        border: 1px solid #ddd;
                        margin: 10px 0;
                        border-radius: 4px;
                    }
                    .license-option:hover {
                        background: #f5f5f5;
                    }
                    .selected-for-bulk {
                        background-color: #e3f2fd !important;
                    }
                    .warning-message {
                        color: #ff9800;
                        font-weight: bold;
                    }
                </style>
            `);
        }
    }
    
    // Handle license checkbox change in modal
    $(document).on('change', '.license-checkbox', function() {
        const selectedLicenses = $('.license-checkbox:checked');
        const totalSeats = Array.from(selectedLicenses).reduce((sum, el) => sum + parseInt($(el).data('seats')), 0);
        const neededSeats = selectedMembers.length;
        
        if (selectedLicenses.length > 0) {
            $('.assign-btn').prop('disabled', false);
            
            if (totalSeats < neededSeats) {
                $('.assignment-summary').show();
                $('.warning-message').text(`⚠️ Warning: You need ${neededSeats} seats but only ${totalSeats} are available in selected licenses.`);
                $('.assign-btn').prop('disabled', true);
            } else {
                $('.assignment-summary').show();
                $('.warning-message').text(`✓ ${totalSeats} seats available for ${neededSeats} members`);
            }
        } else {
            $('.assign-btn').prop('disabled', true);
            $('.assignment-summary').hide();
        }
    });
    
    // Handle assign button click
    $(document).on('click', '.assign-btn', function() {
        const selectedLicenses = $('.license-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedLicenses.length === 0) {
            alert('Please select at least one license');
            return;
        }
        
        // Disable button and show loading
        $(this).prop('disabled', true).text('Assigning...');
        
        // Make AJAX call
        $.post(ajaxUrl, {
            action: 'llmsgaa_bulk_assign_licenses',
            member_emails: selectedMembers,
            license_ids: selectedLicenses,
            group_id: groupId,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                
                // Clear selections
                selectedMembers = [];
                $('.member-checkbox').prop('checked', false);
                $('.selected-for-bulk').removeClass('selected-for-bulk');
                updateBulkActionsBar();
                
                // Close modal
                $('#llmsgaa-bulk-modal').remove();
                
                // Reload page to show updated data
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
                $('.assign-btn').prop('disabled', false).text('Assign Licenses');
            }
        }).fail(function() {
            alert('Failed to assign licenses. Please try again.');
            $('.assign-btn').prop('disabled', false).text('Assign Licenses');
        });
    });
    
    // Handle modal close
    $(document).on('click', '.close-modal, .cancel-btn', function() {
        $('#llmsgaa-bulk-modal').remove();
    });
    
    // Close modal on overlay click
    $(document).on('click', '.llmsgaa-modal-overlay', function(e) {
        if ($(e.target).hasClass('llmsgaa-modal-overlay')) {
            $('#llmsgaa-bulk-modal').remove();
        }
    });
    
    // Handle bulk assign button click
    $(document).on('click', '#llmsgaa-bulk-assign-btn', function(e) {
        e.preventDefault();
        
        if (selectedMembers.length === 0) {
            alert('Please select at least one member');
            return;
        }
        
        showBulkAssignModal(selectedMembers);
    });
    
    // Initialize on page load
    initializeCheckboxes();
    
    // Also check if bulk assign button needs to be added
    if ($('.llmsgaa-actions, .member-actions').length && $('#llmsgaa-bulk-assign-btn').length === 0) {
        $('.llmsgaa-actions, .member-actions').first().append(
            '<button id="llmsgaa-bulk-assign-btn" class="button" style="display:none;margin-left:10px;">📋 Bulk Assign</button>'
        );
    }
    
    console.log('Bulk assign functionality initialized');
});