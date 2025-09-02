document.addEventListener('DOMContentLoaded', () => {
  const skuMap = window.llmsgaaSkuMap || {};
  const detailModal = document.getElementById('llmsgaa-pass-modal');
  const redeemModal = document.getElementById('llmsgaa-redeem-modal');

  // Check if modals exist
  if (!detailModal || !redeemModal) {
    console.error('Required modals not found');
    return;
  }

  // ========== MODAL UTILITY FUNCTIONS ==========
  
  // Generic modal open/close functions
  function openModal(modal) {
    modal.style.display = 'block';
    modal.classList.add('is-visible');
  }

  function closeModal(modal) {
    modal.style.display = 'none';
    modal.classList.remove('is-visible');
  }

  // ========== PASS DETAILS MODAL ==========
  
  // View pass details
  document.querySelectorAll('.llmsgaa-pass-details').forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      const title = el.dataset.title;
      const date = el.dataset.date;
      const email = el.dataset.email;

      const itemsRaw = el.dataset.items || '[]';
      let items = [];

      try {
        items = JSON.parse(itemsRaw);
        if (!Array.isArray(items)) {
          items = [];
        }
      } catch (err) {
        console.warn('Invalid items JSON:', itemsRaw, err);
        items = [];
      }

      let html = `<h3>${title}</h3>`;
      html += `<p><strong>Date Purchased:</strong> ${date}</p>`;
      html += `<p><strong>Buyer Email:</strong> ${email}</p>`;
      if (items.length) {
        html += '<h4>Items:</h4><ul>';
        items.forEach(i => {
          const label = skuMap[i.sku] || i.sku || 'Unknown';
          html += `<li>${label} (${i.sku}): ${i.quantity} seats</li>`;
        });
        html += '</ul>';
      } else {
        html += '<p>No items found.</p>';
      }

      detailModal.querySelector('.llmsgaa-modal-body').innerHTML = html;
      openModal(detailModal);
    });
  });

  // ========== REDEEM MODAL FUNCTIONS ==========
  
  // Function to open redeem modal with animation and setup
  function openRedeemModal(passId) {
    const passIdInput = redeemModal.querySelector('[name="pass_id"]');
    if (passIdInput) {
      passIdInput.value = passId;
    }
    
    // Set minimum date to today and default value
    const dateInput = redeemModal.querySelector('#llmsgaa-start-date');
    if (dateInput) {
      const today = new Date().toISOString().split('T')[0];
      dateInput.setAttribute('min', today);
      dateInput.value = today; // Default to today
    }
    
    // Open modal with animation
    redeemModal.style.display = 'flex'; // Use flex for centering
    redeemModal.classList.add('is-visible');
    
    // Focus on date input after modal opens
    setTimeout(() => {
      if (dateInput) {
        dateInput.focus();
      }
    }, 100);
  }

  // Function to close redeem modal with cleanup
  function closeRedeemModal() {
    redeemModal.style.display = 'none';
    redeemModal.classList.remove('is-visible');
    
    // Reset form
    const form = redeemModal.querySelector('#llmsgaa-redeem-form');
    if (form) {
      form.reset();
    }
  }

  // ========== REDEEM BUTTON HANDLERS ==========
  
  // Handle redeem button clicks
  document.querySelectorAll('.llmsgaa-redeem-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const passId = btn.dataset.passId;
      
      if (!passId) {
        console.error('No pass ID found for redeem button');
        return;
      }
      
      openRedeemModal(passId);
    });
  });

  // ========== MODAL CLOSE HANDLERS ==========
  
  // Close buttons for both modals
  document.querySelectorAll('.llmsgaa-modal-close').forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      // Determine which modal to close
      if (el.closest('#llmsgaa-pass-modal')) {
        closeModal(detailModal);
      } else if (el.closest('#llmsgaa-redeem-modal')) {
        closeRedeemModal();
      }
    });
  });

  // Handle cancel button specifically for redeem modal
  const cancelBtn = redeemModal.querySelector('.llmsgaa-modal-cancel');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', (e) => {
      e.preventDefault();
      closeRedeemModal();
    });
  }

  // Close modals when clicking outside
  redeemModal.addEventListener('click', (e) => {
    if (e.target === redeemModal) {
      closeRedeemModal();
    }
  });

  detailModal.addEventListener('click', (e) => {
    if (e.target === detailModal) {
      closeModal(detailModal);
    }
  });

  // Close modals with ESC key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (redeemModal.classList.contains('is-visible')) {
        closeRedeemModal();
      }
      if (detailModal.style.display === 'block') {
        closeModal(detailModal);
      }
    }
  });

  // ========== FORM ENHANCEMENTS ==========
  
  // Add loading state to form submission
  const redeemForm = document.getElementById('llmsgaa-redeem-form');
  if (redeemForm) {
    redeemForm.addEventListener('submit', function(e) {
      const submitBtn = this.querySelector('.llmsgaa-btn-primary');
      if (submitBtn) {
        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.llmsgaa-btn-text');
        if (btnText) {
          btnText.textContent = 'Processing...';
        } else {
          submitBtn.textContent = 'Processing...';
        }
      }
    });
  }

  // Date picker enhancement - show day of week
  const dateInput = document.getElementById('llmsgaa-start-date');
  if (dateInput) {
    dateInput.addEventListener('change', function() {
      const date = new Date(this.value);
      const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
      const hint = this.parentElement.querySelector('.llmsgaa-form-hint');
      if (hint && !isNaN(date)) {
        const dayName = days[date.getDay()];
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        const formattedDate = date.toLocaleDateString('en-US', options);
        hint.textContent = `Access will begin on ${dayName}, ${formattedDate}`;
      }
    });
  }
});

// ========== CART REPEATER FUNCTIONALITY ==========
// (For Shopify Test Shortcode)

document.addEventListener('DOMContentLoaded', function () {
  document.body.addEventListener('click', function (e) {
    if (e.target.matches('.llmsgaa-add-row')) {
      e.preventDefault();
      const row = e.target.closest('tr').cloneNode(true);
      row.querySelectorAll('input, select').forEach(input => input.value = '');
      const tbody = document.querySelector('#llmsgaa-assign-rows tbody');
      if (tbody) {
        tbody.appendChild(row);
      }
    }
  });
});

// First, make sure the updateBulkActionsBar function exists
function updateBulkActionsBar() {
    const selectedCount = $('.member-checkbox:checked').length;
    
    if (selectedCount > 0) {
        // Show bulk actions bar if you have one
        $('#bulk-actions-bar').slideDown(200);
        $('#selected-count').text(selectedCount);
        
        // Show and update bulk assign button
        $('#llmsgaa-bulk-assign-btn').show();
        $('#llmsgaa-bulk-assign-btn').removeClass('btn-primary').addClass('btn-success');
        $('#llmsgaa-bulk-assign-btn').html(`<span class="btn-icon">📋</span> Bulk Assign (${selectedCount} selected)`);
    } else {
        // Hide bulk actions bar
        $('#bulk-actions-bar').slideUp(200);
        
        // Hide bulk assign button
        $('#llmsgaa-bulk-assign-btn').hide();
        $('#llmsgaa-bulk-assign-btn').removeClass('btn-success').addClass('btn-primary');
        $('#llmsgaa-bulk-assign-btn').html('<span class="btn-icon">📋</span> Bulk Assign');
    }
}

// Make sure this function is also defined (it might be missing)
function updateBulkAssignButton() {
    const selectedCount = $('.member-checkbox:checked').length;
    const bulkBtn = $('#llmsgaa-bulk-assign-btn');
    
    if (selectedCount > 0) {
        bulkBtn.show(); // Make sure it's visible
        bulkBtn.removeClass('btn-primary').addClass('btn-success');
        bulkBtn.html(`<span class="btn-icon">📋</span> Bulk Assign (${selectedCount} selected)`);
    } else {
        bulkBtn.hide(); // Hide when nothing selected
        bulkBtn.removeClass('btn-success').addClass('btn-primary');
        bulkBtn.html('<span class="btn-icon">📋</span> Bulk Assign');
    }
}

// Individual Checkbox Handler
// Individual Checkbox Handler
jQuery(document).on('change', '.member-checkbox', function() {
    updateBulkActionsBar();
    updateBulkAssignButton();
    
    // Update select-all checkbox state
    const totalCheckboxes = jQuery('.member-checkbox').length;
    const checkedCheckboxes = jQuery('.member-checkbox:checked').length;
    
    jQuery('#select-all-members').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
    jQuery('#select-all-members').prop('checked', checkedCheckboxes === totalCheckboxes && totalCheckboxes > 0);
});

// Select All Checkbox Handler
$(document).on('change', '#select-all-members', function() {
    $('.member-checkbox').prop('checked', this.checked);
    updateBulkActionsBar(); // Use the correct function name
    updateBulkAssignButton(); // Also update the button directly
});

// Make sure the Bulk Assign button click handler is properly set up
$(document).on('click', '#llmsgaa-bulk-assign-btn', function(e) {
    e.preventDefault();
    
    // Get selected members
    const selectedEmails = $('.member-checkbox:checked').map(function() {
        return this.value;
    }).get();
    
    if (selectedEmails.length === 0) {
        alert('Please select at least one member to assign licenses to.');
        return;
    }
    
    console.log('Selected emails for bulk assign:', selectedEmails); // Debug log
    showBulkAssignModal(selectedEmails);
});

// Also add a debug check on page load to make sure the button exists
$(document).ready(function() {
    // Check if bulk assign button exists
    if ($('#llmsgaa-bulk-assign-btn').length === 0) {
        console.error('Bulk Assign button not found! Make sure the HTML includes the button.');
    } else {
        console.log('Bulk Assign button found and ready.');
        // Initially hide it
        $('#llmsgaa-bulk-assign-btn').hide();
    }
    
    // Check if checkboxes exist
    const checkboxCount = $('.member-checkbox').length;
    console.log(`Found ${checkboxCount} member checkboxes.`);
});