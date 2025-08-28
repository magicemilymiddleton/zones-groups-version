
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Note following text changes on customer-facing output (DS 8/15/25)
// ' seats)' -> ')'
// v2.3b
// 'license' -> 'Access Pass'
// 2.3c 
// 'Member' -> 'User' 
// ( $course . ' - ' . $count . ' Access Pass' . ($count > 1 ? 'es' : '') . ' available to assign' )
// remove  <strong>Products:</strong>
// 2.3d
// adjust Your Orders content
// 2.3 e
// adjust Users area
// change View button to Remove, put in last column
// 2.3f
// move Cancel invite into Access Passes column
// 2.3g
// move Cancel Invite and Remove User to Role column
// 2.4h
// reincorporate some styling in Orders area (renew badge)
// 2.4i
// restore some icons, text refinements in modals
// 2.4j
// 
// 2.4k
// 
// modify sticky header
// hide Remove User button until glitches are fixed
// 2.4l
// Added Total Users summary
// 2.4m
// added check to prevent Admin from changing themself to user
// moved total Users inside 





// Ensure all data is available
$group_id = $group_id ?? get_the_ID();
$passes   = $passes   ?? [];

// Get data using our new functions
$all_members = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::get_all_group_members( $group_id );
$available_licenses = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::get_available_licenses( $group_id );
?>


<div id="llmsgaa-unified-wrapper" class="space-y-6">

<div class="llmsgaa-box">
  <h2 class="text-xl font-semibold">Your Orders</h2>

  <!-- Keep existing modal HTML -->
  <div id="llmsgaa-pass-modal" class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div style="border:2px solid #666; border-radius:10px; background:#f9f9f9; padding:20px; position:relative;">
      <span class="llmsgaa-modal-close" style="cursor:pointer; position:absolute; top:10px; right:14px; font-size:24px;">&times;</span>
      <div class="llmsgaa-modal-body"></div>
    </div>
  </div>

<!-- Replace the existing redeem modal in passes.php with this improved version -->

<div id="llmsgaa-redeem-modal" class="llmsgaa-modal-overlay" style="position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; display: none; align-items: center !important; justify-content: center !important;">
  <div class="llmsgaa-modal-container" style="position: relative !important; margin: auto !important;">
    <div class="llmsgaa-modal-header">
      <h3 class="llmsgaa-modal-title">Select Start Date</h3>
      <button type="button" class="llmsgaa-modal-close" aria-label="Close">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    
    <div class="llmsgaa-modal-body">
      <p class="llmsgaa-modal-description">
        Confirm today's Start Date to begin immediately, or select a later date. All Access Passes in this Order will activate on the selected date and run for one year.
      </p>
      
      <form id="llmsgaa-redeem-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'llmsgaa_redeem_pass_action', 'llmsgaa_redeem_pass_nonce' ); ?>
        <input type="hidden" name="action" value="llmsgaa_redeem_pass" />
        <input type="hidden" name="pass_id" value="" />
        
        <div class="llmsgaa-form-group">
          <label for="llmsgaa-start-date" class="llmsgaa-form-label">
            Start Date
          </label>
          <input 
            type="date" 
            id="llmsgaa-start-date"
            name="start_date" 
            required 
            class="llmsgaa-form-input"
            min="<?php echo date('Y-m-d'); ?>"
          />
          <span class="llmsgaa-form-hint">Access will begin on this date</span>
        </div>
        
        <div class="llmsgaa-modal-footer">
          <button type="button" class="llmsgaa-btn llmsgaa-btn-secondary llmsgaa-modal-cancel">
            Cancel
          </button>
          <button type="submit" class="llmsgaa-btn llmsgaa-btn-primary">
            <span class="llmsgaa-btn-text">Confirm Start Date</span>
            <svg class="llmsgaa-btn-icon " width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 1.414L10.586 9.5H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z"/>
            </svg>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
/* Modal Overlay */
.llmsgaa-modal-overlay {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100% !important;
  height: 100% !important;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 99999;
  display: none;
  align-items: center !important;
  justify-content: center !important;
  padding: 20px;
  animation: llmsgaa-fade-in 0.2s ease-out;
}

.llmsgaa-modal-overlay.is-visible {
  display: flex !important;
}

/* Modal Container */
.llmsgaa-modal-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  width: 100%;
  max-width: 440px;
  max-height: 90vh;
  overflow: hidden;
  animation: llmsgaa-slide-up 0.3s ease-out;
  position: relative !important;
  margin: auto !important;
}

/* Modal Header */
.llmsgaa-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 24px 0;
  border-bottom: none;
}

.llmsgaa-modal-title {
  font-size: 24px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.llmsgaa-modal-close {
  background: none;
  border: none;
  padding: 8px;
  cursor: pointer;
  color: #6b7280;
  border-radius: 6px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.llmsgaa-modal-close:hover {
  background: #f3f4f6;
  color: #111827;
}

/* Modal Body */
.llmsgaa-modal-body {
  padding: 20px 24px 24px;
}

.llmsgaa-modal-description {
  color: #6b7280;
  font-size: 14px;
  line-height: 1.5;
  margin: 0 0 24px 0;
}

/* Form Elements */
.llmsgaa-form-group {
  margin-bottom: 24px;
}

.llmsgaa-form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}

.llmsgaa-form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 15px;
  color: #111827;
  background: white;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.llmsgaa-form-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.llmsgaa-form-hint {
  display: block;
  font-size: 12px;
  color: #9ca3af;
  margin-top: 6px;
}

/* Modal Footer */
.llmsgaa-modal-footer {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
}

/* Buttons */
.llmsgaa-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 500;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  line-height: 1;
}

.llmsgaa-btn-primary {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
  box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
}

.llmsgaa-btn-primary:hover {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
  transform: translateY(-1px);
}

.llmsgaa-btn-secondary {
  background: white;
  color: #6b7280;
  border: 1px solid #e5e7eb;
}

.llmsgaa-btn-secondary:hover {
  background: #f9fafb;
  color: #374151;
  border-color: #d1d5db;
}

.llmsgaa-btn-icon {
  width: 16px;
  height: 16px;
}

/* Animations */
@keyframes llmsgaa-fade-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes llmsgaa-slide-up {
  from {
    transform: translateY(20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

/* Responsive */
@media (max-width: 480px) {
  .llmsgaa-modal-container {
    max-width: 100%;
    margin: 20px;
  }
  
  .llmsgaa-modal-header {
    padding: 20px 20px 0;
  }
  
  .llmsgaa-modal-body {
    padding: 16px 20px 20px;
  }
  
  .llmsgaa-modal-title {
    font-size: 20px;
  }
}
</style>


 <!-- Orders Table, replacement code 8/15/25-->
  <div class="overflow-x-auto">
    <table class="llmsgaa-table w-full text-sm">
      <thead>
        <tr>
          <th style="width: 16%;">Purchase Date</th>
          <th style="width: 60%;">Information</th>
          <th style="width: 24%;">Activation</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $sku_map = get_option( 'llmsgaa_sku_map', array() );

        foreach ( $passes as $p ):
          $items = get_post_meta( $p->ID, 'llmsgaa_pass_items', true );

/** code from v2.3 re-inserted */
          if ( is_string( $items ) ) { $items = json_decode( $items, true ); }
$total_seats = array_sum( wp_list_pluck( (array) $items, 'quantity' ) );
$assigned_licenses = get_post_meta( $p->ID, 'llmsgaa_assigned_licenses', true );
$used_seats = is_array( $assigned_licenses ) ? count( $assigned_licenses ) : 0;
$available_seats = $total_seats - $used_seats;

          
          $order_number = get_post_meta( $p->ID, 'order_number', true ) ?: get_the_title( $p );
          $buyer_email  = get_post_meta( $p->ID, 'buyer_id', true );
          $is_redeemed  = get_post_meta( $p->ID, 'llmsgaa_redeemed', true );


$is_renewal = false;

          // Build product lines
          $products = [];
          if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
              $product_info = '';
 if ( isset( $item['sku'] ) && stripos( $item['sku'], 'renew' ) !== false ) {
  $is_renewal = true;
}
             
              if ( isset( $item['sku'] ) && isset( $sku_map[ $item['sku'] ] ) ) {
                $product_id = $sku_map[ $item['sku'] ];
                $product_title = get_the_title( $product_id );
                if ( $product_title ) {
                  $product_info = $product_title;
                  if ( isset( $item['quantity'] ) && $item['quantity'] > 1 ) {
                    $product_info .= ' (' . intval($item['quantity']) . ')';
                  }
                }
              }
              if ( ! $product_info ) {
                $product_info = $item['label'] ?? $item['sku'] ?? '';
                if ( isset( $item['quantity'] ) && $item['quantity'] > 1 ) {
                  $product_info .= ' (' . intval($item['quantity']) . ')';
                }
              }
              if ( $product_info ) {
                $products[] = $product_info;
              }
            }
          }
        ?>
        <tr>
          <td style="vertical-align: top;">
            <?php echo esc_html( get_the_date( 'M j, Y', $p->ID ) ); ?>
          </td>
          <td>
            <div style="font-weight: 700; font-style: bold;">
<!-- add renewal badge -->
 <?php echo esc_html( $order_number ); ?>
  <?php if ( $is_renewal ): ?>
    <span style="display: inline-block; background-color: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; margin-left: 8px;">RENEWAL</span>
  <?php endif; ?>
              
              <?php if ( $buyer_email ): ?>
                <span style="font-weight: normal;">&nbsp;by&nbsp;</span>
                <span style="font-weight: normal; color: #2e3e80; "><?php echo esc_html( $buyer_email ); ?></span>
              <?php endif; ?>
            </div>
            <?php if ( ! empty($products) ): ?>
              <div style="margin: 2px 0 0 0;">
                <?php foreach ( $products as $product ): ?>
                  <div style="margin-left: 10px;">&bull; <?php echo esc_html( $product ); ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <td style="vertical-align: middle;">
            <?php if ( ! $is_redeemed ): ?>
              <button
                type="button"
                class="llmsgaa-redeem-btn"
                data-pass-id="<?php echo esc_attr( $p->ID ); ?>"
                style="font-weight: bold; background: #ffe066; color: #444; border: none; border-radius: 5px; padding: 6px 16px; cursor: pointer; margin-bottom: 2px;">
                Choose Start Date
              </button>
            <?php else: ?>
              <span class="text-green-600 font-medium" style="background:#e6f7e2; color:#287b36; padding:5px 16px; border-radius:4px; font-weight:bold;">Activated</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>


<!-- Available Licenses Summary -->
<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded" style="margin-bottom: 10px;">
    <h3 class="text-sm font-semibold">Available Access Passes</h3>
    <ul class="mt-2 text-sm" style="margin: 2px 0; padding-left: 20px; font-size: .95rem;">
        <?php 
        if ( !empty( $available_licenses ) ) {
            $license_summary = [];
            foreach ( $available_licenses as $license ) {
                $key = $license->course_title . ' (Start: ' . $license->start_date_formatted . ')';
                $license_summary[$key] = ($license_summary[$key] ?? 0) + 1;
            }
            foreach ( $license_summary as $course => $count ): 
                // Split course and start date
                if ( preg_match( '/^(.*?) \(Start: (.*?)\)$/', $course, $matches ) ) {
                    $title = $matches[1];
                    $start = $matches[2];
                } else {
                    $title = $course;
                    $start = '';
                }
            ?>
                <li style="margin-bottom: 0.5rem; line-height: 1.4;">
                    <span><?php echo esc_html( $title ); ?></span>
                    <?php if ( $start ): ?>
                        <span style="color: #2e3e80;"> (Start: <?php echo esc_html( $start ); ?>)</span>
                    <?php endif; ?>
                    –
                    <strong><?php echo esc_html( $count ); ?></strong>
                    <?php echo esc_html( ' Access Pass' . ($count > 1 ? 'es' : '') . ' available' ); ?>
                </li>
            <?php endforeach;
        } else { ?>
            <li style="color: #856404;">No Access Passes available to assign</li>
        <?php } ?>
    </ul>
</div>
<!-- close Available Licenses -->

</div> 
<!-- Close Your Orders section -->



<!-- Members Management Header -->
<div class="llmsgaa-box">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
    <h2 class="text-xl font-semibold">Users & Access Pass Management</h2>
    
    <!-- Action Buttons -->
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">

      <!-- Add Member Button -->
      <button id="llmsgaa-add-member-btn" class="btn btn-secondary">
        <span class="btn-icon">➕</span> Add User
      </button>
      
      <!-- CSV Import Button -->
      <button id="llmsgaa-csv-import-btn" class="btn btn-secondary">
        <span class="btn-icon">📁</span> Import Users via CSV
      </button>
      
    <!-- Bulk Assign Button -->
      <button id="llmsgaa-bulk-assign-btn" class="btn btn-primary" style="display: none; margin-left: 2rem;">
        <span class="btn-icon">📋</span> Bulk Assign
      </button>       
    </div>
    

  </div>
  
  <!-- Bulk Actions Bar (shows when members are selected) -->
  <div id="bulk-actions-bar" style="display: none; padding: 15px; background: #e3f2fd; border-radius: 6px; margin-bottom: 15px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <strong><span id="selected-count">0</span> member(s) selected</strong>
      </div>
      <div style="display: flex; gap: 10px;">
        <button id="bulk-assign-licenses-btn" class="btn btn-sm btn-success">
          <span class="btn-icon">🎫</span> Assign Passes
        </button>
        <button id="bulk-remove-members-btn" class="btn btn-sm btn-danger">
          <span class="btn-icon">🗑</span> Remove Selected
        </button>
        <button id="clear-selection-btn" class="btn btn-sm btn-outline">
          <span class="btn-icon">✖</span> Clear Selection
        </button>
      </div>
    </div>
  </div>
  
        <!-- Available Licenses Summary (if you want to show it here too) -->
  <?php if ( isset( $available_licenses ) && count( $available_licenses ) > 0 ): ?>
    <div class="mt-2 text-sm" style="font-size: 1rem; margin-bottom: 5px; padding-left: 0px;">
        Total Users: <?php echo count( $all_members ); ?></li>
    </div>
  <?php endif; ?>

  
    <!-- Members Table -->
    <div class="overflow-x-auto">
      <table class="llmsgaa-table w-full text-sm">

      <thead>
  <tr>
    <th><input type="checkbox" id="select-all-members" /></th>
    <th>Name / Email</th>
    <th>Role</th>
    <th>Last Login</th>
    <th>Access Passes</th>
    <!--<th>Licenses</th> -->
    <th>Assign/Remove Passes</th>
  </tr>
</thead>
        <tbody>
          <?php if ( empty( $all_members ) ): ?>
            <tr>
<td colspan="6" class="text-center text-gray-500 py-4">
                No members found. Add your first user using the "Add User" button above.
              </td>
            </tr>
          <?php else: ?>
<?php foreach ( $all_members as $member ): 
  // Get detailed course access for this member
  $course_access = LLMSGAA\Feature\Shortcodes\UnifiedMemberManager::get_member_course_access( $group_id, $member['email'] );
?>
<tr class="member-row" data-email="<?php echo esc_attr( $member['email'] ); ?>" data-user-id="<?php echo esc_attr( $member['user_id'] ?: '' ); ?>">
  <td>
    <input type="checkbox" class="member-checkbox" value="<?php echo esc_attr( $member['email'] ); ?>" />
  </td>
  <td>
    <div>
      <strong><?php echo esc_html( $member['name'] ); ?></strong><br>
      <span class="text-gray-600"><?php echo esc_html( $member['email'] ); ?></span>
    </div>
  </td>
  <td>

<!-- check current user to disable Admin from changing themself to User -->
<?php
$current_user_id = get_current_user_id();
$is_self = (int) $member['user_id'] === $current_user_id;
?>
<select class="role-select border rounded px-2 py-1" data-email="<?php echo esc_attr( $member['email'] ); ?>" data-user-id="<?php echo esc_attr( $member['user_id'] ?: '' ); ?>">
    <option value="member" <?php selected( $member['role'], 'member' ); ?> <?php echo $is_self ? 'disabled' : ''; ?>>User<?php echo $is_self ? '' : ''; ?></option>
    <option value="admin" <?php selected( $member['role'], 'admin' ); ?>>Admin</option>
</select>

<!--replaced this code
    <select class="role-select border rounded px-2 py-1" data-email="<?php echo esc_attr( $member['email'] ); ?>" data-user-id="<?php echo esc_attr( $member['user_id'] ?: '' ); ?>">
        <option value="member" <?php selected( $member['role'], 'member' ); ?>>User</option>
        <option value="admin" <?php selected( $member['role'], 'admin' ); ?>>Admin</option>
    </select>
 -->   
    
<!--  supress Admin badge
    <?php if ( $member['role'] === 'admin' ): ?>
        <span class="role-badge" style="background: #007cba; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; margin-left: 5px;">ADMIN</span>
    <?php endif; ?>
    -->

 <!-- Cancel Invite and Remove buttons moved to last login class=btn-xs--> 
     <div>
      <?php if ( $member['status'] === 'pending' ): ?>
      <button class="btn btn-xs btn-danger cancel-invite-btn" title="Cancel invitation email and remove Access Passes" data-invite-id="<?php echo esc_attr( $member['invite_id'] ); ?>">
        <span class="btn-icon" >✖</span> Cancel Invite
      </button>
    <?php else: ?>
<!--   Hide Remove User button. Removing User does not unenroll from Course/Membership associated with Group   
<button class="btn btn-xs btn-danger remove-member-btn" title="Delete User and remove Access Passes" data-user-id="<?php echo esc_attr( $member['user_id'] ); ?>">
        <span class="btn-icon">🗑</span> Remove User
      </button>
      -->
    <?php endif; ?>
    </div>

    
  </td>
<td>
  <?php 
  // Get the last login from the member array
  $last_login = $member['last_login'] ?? 'Never';
  
  // Determine color and icon based on recency
  $color_class = '';
  $icon = '';
  
  if ($last_login === 'Just now' || strpos($last_login, 'min ago') !== false) {
      $color_class = 'color: #059669; '; // Green - online/recent
      // $icon = '🟢';
  } elseif (strpos($last_login, 'hour') !== false || $last_login === 'Yesterday') {
      $color_class = 'color: #2563eb;'; // Blue - recent
  } elseif (strpos($last_login, 'days ago') !== false) {
      $color_class = 'color: #6b7280;'; // Gray - few days
  } elseif (strpos($last_login, 'week') !== false) {
      $color_class = 'color: #ea580c;'; // Orange - weeks
  } elseif ($last_login === 'Never') {
      $color_class = 'color: #dc2626;'; // Red - never
      // $icon = '⚠️';
  } elseif ($last_login === 'Invite pending') {
      $color_class = 'color: #ca8a04; font-weight: 600;'; // Yellow - pending
      // $icon = '📧';
  } else {
      $color_class = 'color: #6b7280;'; // Gray - old dates
  }
  ?>
  <span style="<?php echo $color_class; ?> font-size: 13px;">
    <?php echo esc_html($last_login); ?> <?php echo $icon; ?>
  </span>
   

   
</td>
  
  <!-- NEW: Course Access & Dates Column -->
  <td class="course-access-cell">
    <?php if ( empty( $course_access ) ): ?>
      <div class="no-access">
        <span class="text-gray-500 text-sm">No Access Passes</span>
      </div>
    <?php else: ?>
      <div class="course-list">
        <?php foreach ( $course_access as $access ): ?>
          <div class="course-item">
            <div class="course-name">
              <strong><?php echo esc_html( $access['course_title'] ); ?></strong>
              <span class="status-indicator"><?php echo $access['status_indicator']; ?></span>
            </div>
            <div class="course-dates">
              <?php if ( $access['start_date'] ): ?>
                <span class="date-range">
                  <?php echo esc_html( $access['start_date'] ); ?>
                  <?php if ( $access['end_date'] ): ?>
                    → <?php echo esc_html( $access['end_date'] ); ?>
                  <?php else: ?>
                    → Ongoing
                  <?php endif; ?>
                </span>
              <?php else: ?>
                <span class="date-range text-gray-500">No dates set</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </td>
  
  <td class="action-buttons">
    <button class="btn btn-sm btn-primary assign-license-btn" title="Assign Access Pass(es) to User" data-email="<?php echo esc_attr( $member['email'] ); ?>">
      <span class="btn-icon" ></span> Assign
    </button>
      
  <!-- View licenses, button now called Remove and moved into Actions column --> 
  <!--  <span class="license-count font-medium"><?php echo esc_html( $member['license_count'] ); ?></span> -->
    <?php if ( $member['license_count'] > 0 ): ?>
      <button class="btn btn-xs view-licenses-btn btn-danger" title="Remove Access Pass(es) from User" data-email="<?php echo esc_attr( $member['email'] ); ?>">
        <span class="btn-icon"></span> Remove
      </button>
    <?php endif; ?>     
</td>
</tr>
<?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modals and JavaScript will go here next -->
<style>
/* Enhanced Button System */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  text-decoration: none;
}

.btn:active {
  transform: translateY(0);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Button Sizes */
/* DS: btn-xs force size */
.btn-xs {
  margin-top: .3rem !important;
  padding: 4px 8px !important;
  font-size: 12px !important;
  gap: 4px;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
  gap: 4px;
}

/* Button Colors */
.btn-primary {
  background: linear-gradient(135deg, #0073aa, #005a87);
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #005a87, #004568);
  color: white;
}

.btn-success {
  background: linear-gradient(135deg, #28a745, #1e7e34);
  color: white;
}

.btn-success:hover {
  background: linear-gradient(135deg, #1e7e34, #155724);
  color: white;
}

/* button restyled */
.btn-danger {
  background: #eee; 
  color: #333;
  border: 1px solid #dc3545;
}

.btn-danger:hover {
  background: linear-gradient(135deg, #c82333, #a71e2a);
  color: white;
}

.btn-secondary {
  background: #00965e;
  color: white;
}

.btn-secondary:hover {
  background: linear-gradient(135deg, #545b62, #383d41);
  color: white;
}

.btn-outline {
  background: transparent;
  border: 1px solid #dee2e6;
  color: #495057;
}

.btn-outline:hover {
  background: #f8f9fa;
  border-color: #adb5bd;
  color: #495057;
}

/* Button Icons */
.btn-icon {
  font-size: 0.9em;
  line-height: 1;
}

/* Action Buttons Container DS: changed display: flex to inline-flex */
.action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.action-buttons .btn {
  margin: 0;
}

/* Modal Buttons */
.modal-btn-primary {
  background: linear-gradient(135deg, #0073aa, #005a87);
  color: white;
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modal-btn-primary:hover {
  background: linear-gradient(135deg, #005a87, #004568);
  transform: translateY(-1px);
}

.modal-btn-secondary {
  background: #6c757d;
  color: white;
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-right: 10px;
}

.modal-btn-secondary:hover {
  background: #545b62;
  transform: translateY(-1px);
}

/* Existing styles (keep these) */
.llmsgaa-box {
  background: white;
  border: 1px solid #e1e5e9;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
}

.llmsgaa-table {
  border-collapse: collapse;
}

.llmsgaa-table th,
.llmsgaa-table td {
  border: 1px solid #e1e5e9;
  padding: 8px 12px;
  text-align: left;
  vertical-align: middle;
}

.llmsgaa-table th {
  background-color: #f8f9fa;
  font-weight: 600;
}

.member-row:hover {
  background-color: #f8f9fa;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .action-buttons {
    flex-direction: column;
    align-items: stretch;
    gap: 4px;
  }
  
  .action-buttons .btn {
    width: 100%;
    justify-content: center;
  }
  
  .flex.gap-3 {
    flex-direction: column;
    gap: 8px;
  }
  
  .flex.gap-3 .btn {
    width: 100%;
    justify-content: center;
  }

  /* Add this to your existing CSS section */

/* Table Scrolling */
.overflow-x-auto {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.llmsgaa-table {
  min-width: 1200px; /* Ensure table is wide enough to trigger scroll */
  border-collapse: collapse;
}

/* Course Access Column Styling */
.course-access-cell {
  min-width: 250px;
  max-width: 300px;
  padding: 8px 12px;
}

.course-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.course-item {
  padding: 8px;
  background: #f8f9fa;
  border-radius: 4px;
  border-left: 3px solid #0073aa;
  font-size: 12px;
}

.course-name {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  font-weight: 600;
  color: #333;
}

.status-indicator {
  font-size: 10px;
  white-space: nowrap;
}

.course-dates {
  color: #666;
  font-size: 11px;
}

.date-range {
  display: inline-block;
  padding: 2px 6px;
  background: #e9ecef;
  border-radius: 3px;
  white-space: nowrap;
}

.no-access {
  text-align: center;
  padding: 16px 8px;
  color: #6c757d;
  font-style: italic;
}

/* Status-based border colors */
.course-item.expired {
  border-left-color: #dc3545;
}

.course-item.active {
  border-left-color: #28a745;
}

.course-item.pending {
  border-left-color: #ffc107;
}

/* Responsive table improvements */
@media (max-width: 768px) {
  .llmsgaa-table {
    min-width: 800px; /* Smaller minimum on mobile */
  }
  
  .course-access-cell {
    min-width: 200px;
    max-width: 250px;
  }
  
  .course-item {
    padding: 6px;
    font-size: 11px;
  }
}

/* Scroll hint for users */
.overflow-x-auto::after {
  content: "← Scroll horizontally to see all columns →";
  display: block;
  text-align: center;
  font-size: 12px;
  color: #6c757d;
  padding: 8px;
  background: #f8f9fa;
  border-top: 1px solid #dee2e6;
}

@media (min-width: 1300px) {
  .overflow-x-auto::after {
    display: none; /* Hide scroll hint on large screens */
  }
}
}

/* Additional styles for bulk selection */
.member-checkbox {
  cursor: pointer;
  transform: scale(1.2);
}

#select-all-members {
  cursor: pointer;
  transform: scale(1.2);
}

tr.selected {
  background-color: #e3f2fd !important;
}

.llmsgaa-bulk-actions-container {
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

#bulk-actions-bar {
  animation: slideDown 0.3s ease;
}

#llmsgaa-bulk-assign-btn:hover,
#bulk-assign-licenses-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}


/* Style Modifications by DStirling 8/15/25 */

.llmsgaa-btn-icon {
  width: 16px;
  height: 16px;
  display: none;
}
.img-emoji {
display: none;
}
.llmsgaa-btn-icon {
display: none;
}

/* .llmsgaa-box {
    border-radius: 6px;
    padding: .5em 1em ;
    border: 0;
    font-size: .9rem;
    line-height: 1.1;
}
*/


</style>

<script>
// Pass PHP data to JavaScript
window.llmsgaa_group_id = <?php echo json_encode( $group_id ); ?>;
window.llmsgaa_nonce = <?php echo json_encode( wp_create_nonce( 'llmsgaa_unified_actions' ) ); ?>;
window.groupId = <?php echo json_encode( $group_id ); ?>;
window.ajaxNonce = '<?php echo wp_create_nonce( 'llmsgaa_unified_actions' ); ?>';

if (typeof ajaxurl === 'undefined') {
    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
}

jQuery(document).ready(function($) {
    console.log('Initializing bulk assign system...');
    
    // ========== CORE FUNCTIONS (DEFINED ONCE) ==========
    
    // Update bulk actions bar
    window.updateBulkActionsBar = function() {
        const selectedCount = $('.member-checkbox:checked').length;
        console.log('updateBulkActionsBar called, selected:', selectedCount);
        
        if (selectedCount > 0) {
            $('#bulk-actions-bar').slideDown(200);
            $('#selected-count').text(selectedCount);
            $('#llmsgaa-bulk-assign-btn').show()
                .removeClass('button-secondary').addClass('button-primary')
                .html(`📋 Bulk Assign (${selectedCount} selected)`);
            $('#bulk-assign-licenses-btn').html(`Assign Access Passes (${selectedCount})`);
        } else {
            $('#bulk-actions-bar').slideUp(200);
            $('#llmsgaa-bulk-assign-btn').hide();
        }
    };
    
    window.updateBulkAssignButton = function() {
        updateBulkActionsBar();
    };
    
    // Show success message
    window.showSuccessMessage = function(message) {
        $('.llmsgaa-success-message').remove();
        const successDiv = $(`
            <div class="llmsgaa-success-message" style="
                position: fixed; 
                top: 50px; 
                right: 20px; 
                z-index: 10000; 
                padding: 12px 20px; 
                background: #d4edda; 
                border: 1px solid #c3e6cb; 
                border-radius: 6px; 
                color: #155724;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                font-weight: 500;
            ">
                ✅ ${message}
            </div>
        `);
        $('body').append(successDiv);
        successDiv.hide().fadeIn(200);
        setTimeout(function() {
            successDiv.fadeOut(300, function() {
                successDiv.remove();
            });
        }, 4000);
    };
    
    // Update member role display
    window.updateMemberRoleDisplay = function(memberRow, newRole) {
        const existingBadge = memberRow.find('.role-badge');
        existingBadge.remove();
        
        if (newRole === 'admin') {
            const adminBadge = $('<span class="role-badge" style="background: #007cba; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; margin-left: 5px;">ADMIN</span>');
            memberRow.find('strong').after(adminBadge);
        }
    };
    
    // Close modal
    window.closeModal = function() {
        $('#llmsgaa-modal').remove();
    };
    
    window.closeModalAndReload = function() {
        closeModal();
        location.reload();
    };
    
    window.closeBulkModal = function() {
        $('#llmsgaa-bulk-modal').fadeOut(200, function() {
            $(this).remove();
        });
    };
    
    // ========== BULK ASSIGN MODAL (SINGLE DEFINITION) ==========
    
 window.showBulkAssignModal = function(selectedEmails) {
    console.log('Loading bulk assign modal for:', selectedEmails);
    console.log('Current values:', {
        group_id: window.llmsgaa_group_id,
        groupId: window.groupId,
        nonce: window.ajaxNonce,
        ajaxurl: ajaxurl
    });
    
    // First, let's make sure we have the required values
    const group_id = window.llmsgaa_group_id || window.groupId || <?php echo isset($group_id) ? $group_id : '0'; ?>;
    const nonce = window.ajaxNonce || '<?php echo wp_create_nonce("llmsgaa_unified_actions"); ?>';
    
    console.log('Using values:', { group_id: group_id, nonce: nonce });
    
    if (!group_id || group_id === 0) {
        alert('Error: Group ID is not set');
        return;
    }
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'llmsgaa_get_available_licenses',
            group_id: group_id,
            nonce: nonce
        },
        success: function(response) {
            console.log('Success response:', response);
            if (response.success && response.data) {
                // Continue with showing the modal
                showBulkAssignModalContent(selectedEmails, response.data);
            } else {
                alert('No licenses available: ' + (response.data || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Full error details:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                getAllResponseHeaders: xhr.getAllResponseHeaders()
            });
            
            // Try to parse WordPress AJAX error
            try {
                if (xhr.responseText) {
                    // Check if it's HTML (WordPress error page)
                    if (xhr.responseText.includes('<html') || xhr.responseText.includes('<!DOCTYPE')) {
                        console.error('WordPress returned an HTML error page. Check PHP error logs.');
                        alert('Server error. Please check PHP error logs.');
                    } else {
                        // Try to parse as JSON
                        const response = JSON.parse(xhr.responseText);
                        console.error('Parsed error response:', response);
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                }
            } catch(e) {
                console.error('Could not parse error response:', xhr.responseText);
                alert('Server error: ' + xhr.responseText.substring(0, 100));
            }
        }
    });
};
    
    // Build bulk modal
window.showBulkAssignModal = function(selectedEmails) {
    console.log('Loading bulk assign modal for:', selectedEmails);
    
    const group_id = window.llmsgaa_group_id || window.groupId;
    const nonce = window.ajaxNonce;
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'llmsgaa_get_available_licenses',
            group_id: group_id,
            nonce: nonce
        },
        success: function(response) {
            console.log('Success response:', response);
            if (response.success && response.data && response.data.length > 0) {
                // Create and show the modal with the licenses
                const licenses = response.data;
                const memberCount = selectedEmails.length;
                const licenseCount = licenses.length;
                
                // Determine how many will get licenses
                const willBeAssigned = Math.min(memberCount, licenseCount);
                const willNotBeAssigned = Math.max(0, memberCount - licenseCount);
                
                // Group licenses by course
                const groupedLicenses = {};
                licenses.forEach(license => {
                    const key = license.course_title + ' (Start: ' + license.start_date_formatted + ')';
                    if (!groupedLicenses[key]) {
                        groupedLicenses[key] = [];
                    }
                    groupedLicenses[key].push(license);
                });
                
                // Build the modal HTML
                let modalHTML = `
                    <div id="bulk-assign-modal" style="
                        display: none;
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.5);
                        z-index: 99999;
                        animation: fadeIn 0.3s ease;
                    ">
                        <div style="
                            position: relative;
                            max-width: 600px;
                            max-height: 80vh;
                            margin: 50px auto;
                            background: white;
                            padding: 30px;
                            border-radius: 8px;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                            overflow-y: auto;
                            animation: slideUp 0.3s ease;
                        ">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h2 style="margin: 0; color: #333; font-size: 22px;">
                                    📋 Bulk Access Pass Assignment
                                </h2>
                                <button onclick="closeBulkModal()" style="
                                    background: none;
                                    border: none;
                                    font-size: 24px;
                                    cursor: pointer;
                                    color: #999;
                                    padding: 0;
                                    width: 30px;
                                    height: 30px;
                                ">×</button>
                            </div>`;
                
                // Add assignment summary
                if (licenseCount === 0) {
                    modalHTML += `
                        <div style="
                            background: #f8d7da;
                            color: #721c24;
                            padding: 12px;
                            border-radius: 6px;
                            margin-bottom: 20px;
                        ">
                            <strong>⚠️ No Available Access Passes</strong><br>
                            All Access Passes for this group have been assigned.
                        </div>`;
                } else if (willBeAssigned === memberCount) {
                    modalHTML += `
                        <div style="
                            background: #d4edda;
                            color: #155724;
                            padding: 12px;
                            border-radius: 6px;
                            margin-bottom: 20px;
                        ">
                            ✅ All ${memberCount} selected Users will receive Access Passes.
                        </div>`;
                } else {
                    modalHTML += `
                        <div style="
                            background: #fff3cd;
                            color: #856404;
                            padding: 12px;
                            border-radius: 6px;
                            margin-bottom: 20px;
                        ">
                            <strong>⚠️ Limited Access Passes Available</strong><br>
                            • ${willBeAssigned} User(s) will receive Access Passes<br>
                            • ${willNotBeAssigned} User(s) will not receive Access Passes (not enough available)<br>
                            <small style="margin-top: 5px; display: block;">
                                Access Passes will be assigned in the Users members are listed.
                            </small>
                        </div>`;
                }
                
                // Show available licenses
                modalHTML += `
                    <div style="margin: 20px 0;">
                        <h3 style="font-size: 16px; margin-bottom: 10px;">Available Access Passes (${licenseCount} total):</h3>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px;">`;
                
                for (const [courseKey, courseLicenses] of Object.entries(groupedLicenses)) {
                    modalHTML += `
                        <div style="padding: 5px 0;">
                            ✓ ${courseKey}: <strong>${courseLicenses.length}</strong> available
                        </div>`;
                }
                
                modalHTML += `
                        </div>
                    </div>`;
                
                // Show assignment order
                modalHTML += `
                    <div style="margin: 20px 0;">
                        <h3 style="font-size: 16px; margin-bottom: 10px;">Assignment Order:</h3>
                        <ol style="
                            background: #f8f9fa;
                            padding: 15px 15px 15px 35px;
                            border-radius: 4px;
                            margin: 0;
                        ">`;
                
                selectedEmails.forEach((email, index) => {
                    const willGetLicense = index < licenseCount;
                    modalHTML += `
                        <li style="padding: 3px 0; color: ${willGetLicense ? '#28a745' : '#dc3545'};">
                            ${email} ${willGetLicense ? '✓' : '✗ No license available'}
                        </li>`;
                });
                
                modalHTML += `
                        </ol>
                    </div>`;
                
                // Add action buttons
                if (licenseCount > 0) {
                    modalHTML += `
                        <div style="
                            display: flex;
                            justify-content: flex-end;
                            gap: 10px;
                            margin-top: 20px;
                            padding-top: 20px;
                            border-top: 1px solid #dee2e6;
                        ">
                            <button onclick="closeBulkModal()" style="
                                background: #6c757d;
                                color: white;
                                padding: 10px 20px;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                                font-size: 14px;
                            ">Cancel</button>
<button id="bulk-assign-confirm-btn" style="
    background: #28a745;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
">
    Assign ${willBeAssigned} License${willBeAssigned !== 1 ? 's' : ''}
</button>
                                Assign ${willBeAssigned} License${willBeAssigned !== 1 ? 's' : ''}
                            </button>
                        </div>`;
                } else {
                    modalHTML += `
                        <div style="text-align: center; margin-top: 20px;">
                            <button onclick="closeBulkModal()" style="
                                background: #6c757d;
                                color: white;
                                padding: 10px 30px;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                            ">Close</button>
                        </div>`;
                }
                
                modalHTML += `
                        </div>
                    </div>
                    <style>
                        @keyframes fadeIn {
                            from { opacity: 0; }
                            to { opacity: 1; }
                        }
                        @keyframes slideUp {
                            from { transform: translateY(20px); opacity: 0; }
                            to { transform: translateY(0); opacity: 1; }
                        }
                    </style>`;
                
                // Remove any existing modal
                $('#bulk-assign-modal').remove();
                
                // Add the new modal to the page
                $('body').append(modalHTML);
                
                // Show the modal with animation
                $('#bulk-assign-modal').fadeIn(300);

                // Show the modal with animation
$('#bulk-assign-modal').fadeIn(300);

// Attach click handler to the assign button
$('#bulk-assign-confirm-btn').off('click').on('click', function() {
    processBulkAssignment(selectedEmails, licenses.map(l => l.ID));
});
                
            } else {
                alert('No Access Passes available for assignment.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading licenses:', xhr.responseText);
            alert('Error loading licenses. Please try again.');
        }
    });
};


window.closeBulkModal = function() {
    $('#bulk-assign-modal').fadeOut(300, function() {
        $(this).remove();
    });
};

    
    // Modal helper functions
    window.createModalHeader = function() {
        return `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: #333; font-size: 22px;">
                    📋 Bulk License Assignment
                </h2>
                <button onclick="closeBulkModal()" style="
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #999;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                ">×</button>
            </div>
        `;
    };
    
    window.createAssignmentSummary = function(memberCount, willBeAssigned, willNotBeAssigned, totalAvailable) {
        if (totalAvailable === 0) {
            return `
                <div style="
                    background: #f8d7da;
                    color: #721c24;
                    padding: 12px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                ">
                    <strong>⚠️ No Available Access Passes</strong><br>
                    All Access Passes for this group have been assigned.
                </div>
            `;
        }
        
        if (willBeAssigned === memberCount) {
            return `
                <div style="
                    background: #d4edda;
                    color: #155724;
                    padding: 12px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                ">
                    <strong>✅ All ${memberCount} selected member(s) will receive Access Passes.
                </div>
            `;
        }
        
        if (willBeAssigned < memberCount) {
            return `
                <div style="
                    background: #fff3cd;
                    color: #856404;
                    padding: 12px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                ">
                    <strong>⚠️ Limited Access Passes Available</strong><br>
                    • ${willBeAssigned} member(s) will receive Access Passes<br>
                    • ${willNotBeAssigned} member(s) will NOT receive Access Passes (not enough available)<br>
                    <small style="margin-top: 5px; display: block;">
                        Access Passes will be assigned in the order members are listed.
                    </small>
                </div>
            `;
        }
        
        return `
            <div style="
                background: #cce5ff;
                color: #004085;
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 20px;
            ">
                <strong>ℹ️ More Access Passes Than Users</strong><br>
                All ${memberCount} selected member(s) will receive Access Passes.<br>
                ${totalAvailable - memberCount} pass(es) will remain available.
            </div>
        `;
    };
    
    window.createLicenseGroups = function(groupedOrders, memberCount, totalAvailable) {
        if (totalAvailable === 0) {
            return '';
        }
        
        let groupedHTML = '';
        let assignmentOrder = 1;
        
        const sortedGroups = Object.values(groupedOrders).sort((a, b) => {
            if (a.start_date === 'Flexible Start') return 1;
            if (b.start_date === 'Flexible Start') return -1;
            if (a.start_date < b.start_date) return -1;
            if (a.start_date > b.start_date) return 1;
            return a.course.localeCompare(b.course);
        });
        
        sortedGroups.forEach(group => {
            const count = group.orders.length;
            const dateDisplay = group.start_date === 'Flexible Start' ? 
                'Flexible start date' : 
                `Starts: ${formatDate(group.start_date)}`;
            
            groupedHTML += `
                <div style="
                    margin-bottom: 15px;
                    padding: 12px;
                    background: #f8f9fa;
                    border-left: 4px solid #667eea;
                    border-radius: 4px;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <strong style="color: #333; font-size: 14px;">
                                ${group.course}
                            </strong>
                            <div style="color: #666; font-size: 12px; margin-top: 4px;">
                                ${dateDisplay}
                                ${group.end_date && group.end_date !== 'No expiration' ? 
                                    `<br> Ends: ${formatDate(group.end_date)}` : ''}
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="
                                background: #667eea;
                                color: white;
                                padding: 2px 8px;
                                border-radius: 12px;
                                font-size: 11px;
                                font-weight: bold;
                            ">${count} available</span>
                        </div>
                    </div>
                    <div style="
                        margin-top: 8px;
                        padding-top: 8px;
                        border-top: 1px solid #dee2e6;
                        font-size: 11px;
                        color: #666;
                    ">
                        Will assign to members #${assignmentOrder} - #${Math.min(assignmentOrder + count - 1, memberCount)}
                    </div>
                </div>
            `;
            assignmentOrder += count;
        });
        
        return `
            <div style="margin-bottom: 20px;">
                <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #333;">
                    Available Access Passes (${totalAvailable} total)
                </h3>
                <div style="max-height: 250px; overflow-y: auto;">
                    ${groupedHTML}
                </div>
            </div>
        `;
    };
    
    window.createMemberList = function(selectedEmails, willBeAssigned) {
        return `
            <div style="
                background: #f8f9fa;
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 20px;
                max-height: 150px;
                overflow-y: auto;
            ">
                <strong style="font-size: 13px;">Assignment Order:</strong>
                <ol style="margin: 8px 0 0 20px; font-size: 12px; color: #666;">
                    ${selectedEmails.map((email, index) => `
                        <li style="margin: 2px 0;">
                            ${email}
                            ${index < willBeAssigned ? 
                                '<span style="color: #28a745; font-weight: bold;"> ✓ Will receive license</span>' : 
                                '<span style="color: #dc3545;"> ✗ No license available</span>'}
                        </li>
                    `).join('')}
                </ol>
            </div>
        `;
    };
    
    window.createModalFooter = function(totalAvailable, willBeAssigned, selectedEmails) {
        const encodedEmails = btoa(JSON.stringify(selectedEmails));
        
        return `
            <div style="
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                padding-top: 20px;
                border-top: 1px solid #e0e0e0;
            ">
                <button onclick="closeBulkModal()" style="
                    background: #6c757d;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                ">Cancel</button>
                
                ${totalAvailable > 0 ? `
                    <button onclick="processBulkAssignment('${encodedEmails}')" style="
                        background: #28a745;
                        color: white;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 600;
                    ">
                        Assign ${willBeAssigned} License${willBeAssigned !== 1 ? 's' : ''}
                    </button>
                ` : ''}
            </div>
        `;
    };
    
window.processBulkAssignment = function(memberEmails, licenseIds) {
    console.log('Processing bulk assignment:', { memberEmails, licenseIds });
    
    // Show loading state
    $('#bulk-assign-modal').find('button').prop('disabled', true);
    $('#bulk-assign-confirm-btn').text('Processing...');
    
    // Call the sequential assignment endpoint
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'llmsgaa_bulk_assign_sequential',
            emails: memberEmails,
            group_id: window.llmsgaa_group_id || window.groupId,
            nonce: window.ajaxNonce
        },
        success: function(response) {
            console.log('Bulk assignment response:', response);
            
            if (response.success) {
                // Close modal
                closeBulkModal();
                
                // Just reload the page - no alert
                location.reload();
                
            } else {
                // Handle error case - you might want to keep this alert for errors
                let errorMsg = 'Error occurred during assignment';
                
                if (typeof response.data === 'string') {
                    errorMsg = response.data;
                } else if (typeof response.data === 'object' && response.data !== null) {
                    if (response.data.message) {
                        errorMsg = response.data.message;
                    } else if (response.data.error) {
                        errorMsg = response.data.error;
                    }
                }
                
                alert('Error: ' + errorMsg);  // Keep error alerts or remove this too if you want
                
                // Re-enable buttons
                $('#bulk-assign-modal').find('button').prop('disabled', false);
                $('#bulk-assign-confirm-btn').text('Assign Access Passes');
            }
        },
        error: function(xhr, status, error) {
            console.error('Bulk assignment error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText
            });
            
            // You can remove this alert too if you don't want any error popups
            alert('Error processing assignments. Please check console for details.');
            
            // Re-enable buttons
            $('#bulk-assign-modal').find('button').prop('disabled', false);
            $('#bulk-assign-confirm-btn').text('Assign Access Passes');
        }
    });
};
    
    window.formatDate = function(dateStr) {
        if (!dateStr || dateStr === 'Flexible') return 'Flexible';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
        } catch(e) {
            return dateStr;
        }
    };
    
    // ========== EVENT HANDLERS ==========
    
    // Checkbox handlers
    $(document).on('change', '.member-checkbox', function() {
        const row = $(this).closest('tr, .member-row');
        if (this.checked) {
            row.addClass('selected').css('background-color', '#e3f2fd');
        } else {
            row.removeClass('selected').css('background-color', '');
        }
        updateBulkActionsBar();
        
        const total = $('.member-checkbox').length;
        const checked = $('.member-checkbox:checked').length;
        $('#select-all-members')
            .prop('indeterminate', checked > 0 && checked < total)
            .prop('checked', checked === total && total > 0);
    });
    
    $(document).on('change', '#select-all-members', function() {
        const isChecked = this.checked;
        $('.member-checkbox').each(function() {
            $(this).prop('checked', isChecked);
            const row = $(this).closest('tr, .member-row');
            if (isChecked) {
                row.addClass('selected').css('background-color', '#e3f2fd');
            } else {
                row.removeClass('selected').css('background-color', '');
            }
        });
        updateBulkActionsBar();
    });
    
    // Bulk assign button
    $(document).on('click', '#llmsgaa-bulk-assign-btn, #bulk-assign-licenses-btn', function(e) {
        e.preventDefault();
        const selectedEmails = $('.member-checkbox:checked').map(function() {
            return this.value;
        }).get();
        
        if (selectedEmails.length === 0) {
            alert('Please select at least one member');
            return;
        }
        
        showBulkAssignModal(selectedEmails);
    });
    
    // Clear selection
    $(document).on('click', '#clear-selection-btn', function(e) {
        e.preventDefault();
        $('.member-checkbox').prop('checked', false);
        $('#select-all-members').prop('checked', false);
        $('.member-row, tr').removeClass('selected').css('background-color', '');
        updateBulkActionsBar();
    });
    
    // Bulk remove button
    $(document).on('click', '#bulk-remove-members-btn', function(e) {
        e.preventDefault();
        const selectedCount = $('.member-checkbox:checked').length;
        if (selectedCount === 0) {
            alert('Please select at least one member');
            return;
        }
        if (confirm(`Remove ${selectedCount} selected member(s)?`)) {
            alert('Remove functionality not yet implemented');
        }
    });
    
    // Add Member Button
    $('#llmsgaa-add-member-btn').on('click', function(e) {
        e.preventDefault();
        showAddMemberModal();
    });
    
    // Assign License Button
    $('.assign-license-btn').on('click', function(e) {
        e.preventDefault();
        const email = $(this).data('email');
        const memberName = $(this).closest('.member-row').find('strong').text();
        loadAndShowLicenses(email, memberName);
    });
    
    // View Licenses Button
    $('.view-licenses-btn').on('click', function(e) {
        e.preventDefault();
        const email = $(this).data('email');
        const memberName = $(this).closest('.member-row').find('strong').text();
        showMemberLicenses(email, memberName);
    });
    
    // CSV Import Button
    $(document).on('click', '#llmsgaa-csv-import-btn', function(e) {
        e.preventDefault();
        showCSVImportModal();
    });
    
    // Store initial role values
    $('.role-select').each(function() {
        $(this).data('previous-value', $(this).val());
    });
    
    // ========== MODAL FUNCTIONS ==========
    
    function showModal(html) {
        closeModal();
        $('body').append(`
            <div id="llmsgaa-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                    ${html}
                </div>
            </div>
        `);
    }
    
    function showAddMemberModal() {
        const html = `
            <h3>Add New User</h3>
            <form id="add-member-form">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email Address:</label>
                    <input type="email" id="member-email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Role:</label>
                    <select id="member-role" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <option value="member">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div style="text-align: right; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" onclick="closeModal()" class="modal-btn-secondary">Cancel</button>
                    <button type="submit" class="modal-btn-primary">Add User</button>
                </div>
            </form>
        `;
        showModal(html);
        
        $('#add-member-form').on('submit', function(e) {
            e.preventDefault();
            const email = $('#member-email').val();
            const role = $('#member-role').val();
            
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('⏳ Adding...');
            
            $.post(ajaxurl, {
                action: 'llmsgaa_add_member',
                group_id: groupId,
                email: email,
                role: role,
                nonce: ajaxNonce
            }, function(response) {
                if (response.success) {
                    showSuccessMessage('User added successfully!');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('Error: ' + response.data);
                    submitBtn.prop('disabled', false).html(originalText);
                }
            }).fail(function() {
                alert('Network error occurred');
                submitBtn.prop('disabled', false).html(originalText);
            });
        });
    }
    
function loadAndShowLicenses(email, memberName) {
    console.log('Loading licenses for group:', window.llmsgaa_group_id || groupId);
    
    $.post(ajaxurl, {
        action: 'llmsgaa_get_available_licenses',
        group_id: window.llmsgaa_group_id || groupId,
        nonce: window.ajaxNonce || window.llmsgaa_nonce
    })
    .done(function(response) {
        console.log('Success response:', response);
        if (response.success) {
            showAssignLicenseModal(email, memberName, response.data);
        } else {
            console.error('Error in response:', response.data);
            alert('Error: ' + response.data);
        }
    })
    .fail(function(xhr, status, error) {
        console.error('AJAX request failed:', {
            status: status,
            error: error,
            responseText: xhr.responseText,
            responseJSON: xhr.responseJSON
        });
        
        // Try to parse the error
        if (xhr.responseJSON && xhr.responseJSON.data) {
            alert('Error: ' + xhr.responseJSON.data);
        } else {
            alert('Error loading licenses. Check console for details.');
        }
    });
}
    

function showAssignLicenseModal(email, memberName, licenses) {
    let html = `<h4>Assign Access Passes</h4>`;
    html += `<div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; font-size: .95rem; color: #444;">${email}</div>`;

    if (licenses.length === 0) {
        html += '<div style="text-align: center; padding: 40px; color: #666;">No available Access Passes to assign.</div>';
        html += '<div style="text-align: right; border-top: 1px solid #eee; padding-top: 15px;">';
        html += '<button onclick="closeModal()" class="modal-btn-secondary">Close</button></div>';
    } else {
        const groupedLicenses = groupLicensesByCourseAndDate(licenses);

       // html += '<div style="margin-bottom: 15px; font-weight: 600;">Select Access Passes:</div>';
        html += '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px; padding: 10px;">';

        Object.keys(groupedLicenses).forEach(groupKey => {
            const group = groupedLicenses[groupKey];

            html += '<div style="margin-bottom: 15px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; background: #fafafa; transition: all 0.2s;" onmouseover="this.style.background=\'#f0f8ff\'; this.style.borderColor=\'#007cba\';" onmouseout="this.style.background=\'#fafafa\'; this.style.borderColor=\'#e0e0e0\';">';
            html += '<div style="display: flex; align-items: start; gap: 12px;">';

            // Left: course info
            html += '<div style="flex: 1;">';
            html += `<div style="font-weight: 600; color: #333; margin-bottom: 4px; font-size: 15px;">${group.courseTitle}</div>`;
            html += `<div style="color: #666; font-size: 13px;">Starts: ${group.startDate || 'Not set'}</div>`;
            if (group.endDate) {
                html += `<div style="color: #666; font-size: 13px;">Ends: ${group.endDate}</div>`;
            }
            html += `<div style="color: #004ea7; font-size: 13px; margin-top: 4px;">✨ ${group.licenses.length} Access Pass${group.licenses.length > 1 ? 'es' : ''} available</div>`;
            html += '</div>';

            // Right: checkbox (always shown)
            html += '<div style="min-width: 40px; text-align: center; padding-top: 8px;">';
            html += `<input type="checkbox" class="license-single-checkbox" data-group="${groupKey}" style="transform: scale(1.3); cursor: pointer;">`;
            html += '</div>';

            html += '</div>'; // close inner flex box
            html += `<div class="license-group-data" data-group="${groupKey}" data-licenses='${JSON.stringify(group.licenses.map(l => l.ID))}'></div>`;
            html += '</div>'; // close license group box
        });

        html += '</div>'; // close scroll box

        html += '<div id="license-selection-summary" style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 6px; display: none;">';
        html += '<strong>Selected:</strong> <span id="selected-license-count">0</span>';
        html += '</div>';

        html += '<div style="text-align: right; border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">';
        html += '<button onclick="closeModal()" class="modal-btn-secondary">Cancel</button>';
        html += `<button onclick="assignGroupedLicenses('${email}')" class="modal-btn-primary">Assign Selected</button>`;
        html += '</div>';
    }

    showModal(html);

    setTimeout(() => {
        updateLicenseSelectionSummary();
        $('.license-single-checkbox').on('change', function () {
            updateLicenseSelectionSummary();
        });
    }, 100);
}




    
    function groupLicensesByCourseAndDate(licenses) {
        const groups = {};
        licenses.forEach(license => {
            const groupKey = `${license.product_id}_${license.start_date || 'no-date'}`;
            if (!groups[groupKey]) {
                groups[groupKey] = {
                    courseTitle: license.course_title,
                    productId: license.product_id,
                    startDate: license.start_date_formatted || 'Not set',
                    endDate: license.end_date_formatted || null,
                    licenses: []
                };
            }
            groups[groupKey].licenses.push(license);
        });
        return groups;
    }
    
    function updateLicenseSelectionSummary() {
        let totalSelected = 0;
        $('.license-quantity-selector').each(function() {
            totalSelected += parseInt($(this).val()) || 0;
        });
        $('.license-single-checkbox:checked').each(function() {
            totalSelected += 1;
        });
        $('#selected-license-count').text(totalSelected);
        if (totalSelected > 0) {
            $('#license-selection-summary').show();
        } else {
            $('#license-selection-summary').hide();
        }
    }
    
    window.assignGroupedLicenses = function(email) {
        const selectedLicenses = [];
        
        $('.license-quantity-selector').each(function() {
            const quantity = parseInt($(this).val()) || 0;
            if (quantity > 0) {
                const groupKey = $(this).data('group');
                const licenseIds = JSON.parse($(`.license-group-data[data-group="${groupKey}"]`).attr('data-licenses'));
                for (let i = 0; i < quantity && i < licenseIds.length; i++) {
                    selectedLicenses.push(licenseIds[i]);
                }
            }
        });
        
        $('.license-single-checkbox:checked').each(function() {
            const groupKey = $(this).data('group');
            const licenseIds = JSON.parse($(`.license-group-data[data-group="${groupKey}"]`).attr('data-licenses'));
            if (licenseIds.length > 0) {
                selectedLicenses.push(licenseIds[0]);
            }
        });
        
        if (selectedLicenses.length === 0) {
            alert('Please select at least one license to assign.');
            return;
        }
        
        const submitBtn = $('.modal-btn-primary');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('⏳ Assigning...');
        
        $.post(ajaxurl, {
            action: 'llmsgaa_assign_licenses',
            email: email,
            license_ids: selectedLicenses,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                showSuccessMessage(response.data);
                closeModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Error: ' + response.data);
                submitBtn.prop('disabled', false).html(originalText);
            }
        }).fail(function() {
            alert('Network error occurred');
            submitBtn.prop('disabled', false).html(originalText);
        });
    };
    
    function showMemberLicenses(email, memberName) {
        $.post(ajaxurl, {
            action: 'llmsgaa_get_member_licenses',
            group_id: groupId,
            email: email,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                let html = `<h3>Access Passes for ${memberName}</h3>`;
                html += `<div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; font-size: 13px; color: #666;">📧 ${email}</div>`;
                
                if (response.data.length === 0) {
                    html += '<div style="text-align: center; padding: 40px; color: #666;">No Access Passes assigned yet.</div>';
                } else {
                    html += '<div style="max-height: 300px; overflow-y: auto;">';
                    response.data.forEach(license => {
                        html += '<div style="padding: 15px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 6px; background: #fafafa;">';
                        html += `<div style="font-weight: 600; color: #333; margin-bottom: 5px;">${license.course_title}</div>`;
                        html += `<div style="font-size: 13px; color: #666; margin-bottom: 8px;">Start: ${license.start_date_formatted || 'Not set'}</div>`;
                        if (license.end_date_formatted) {
                            html += `<div style="font-size: 13px; color: #666; margin-bottom: 8px;">End: ${license.end_date_formatted}</div>`;
                        }
                        html += `<div style="font-size: 13px; color: #666; margin-bottom: 10px;">Status: ${license.status || 'Active'}</div>`;
                        html += `<button onclick="removeLicense(${license.ID})" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#c82333'" onmouseout="this.style.background='#dc3545'">🗑 Remove License</button>`;
                        html += '</div>';
                    });
                    html += '</div>';
                }
                
                html += '<div style="text-align: right; border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">';
                html += '<button onclick="closeModal()" class="modal-btn-primary">Close</button></div>';
                showModal(html);
            } else {
                alert('Error: ' + response.data);
            }
        });
    }
    
    function showCSVImportModal() {
        const html = `
            <h3>Import Users from CSV</h3>
            
            <div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #856404;">CSV Format Instructions</h4>
                <p style="margin: 0 0 10px 0; color: #856404; font-size: 14px;">
                    Your CSV should have one column with header: <strong>email</strong>
                </p>
                <div style="background: #fff; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #333;">
                    email<br>
                    john@example.com<br>
                    sarah@example.com<br>
                    mike@example.com
                </div>
            </div>
            
            <form id="csv-import-form" enctype="multipart/form-data">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Select CSV File:</label>
                    <input type="file" id="csv-file" accept=".csv" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Default Role for New Users:</label>
                    <select id="csv-default-role" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="member">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" id="csv-assign-licenses" style="transform: scale(1.2);">
                        <span style="font-weight: 600;">Assign Access Passes during import</span>
                    </label>
                    <small style="color: #666; margin-left: 30px; display: block; margin-top: 5px;">
                        Access Passes will be assigned in order. If you have fewer Access Passes than members, some won't get assigned.
                    </small>
                </div>
                
                <div style="text-align: right; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" onclick="closeModal()" class="modal-btn-secondary">Cancel</button>
                    <button type="submit" class="modal-btn-primary">Import Users</button>
                </div>
            </form>
        `;
        
        showModal(html);
        
        $('#csv-import-form').on('submit', function(e) {
            e.preventDefault();
            processCSVImport();
        });
    }
    
    function processCSVImport() {
        const fileInput = document.getElementById('csv-file');
        const defaultRole = $('#csv-default-role').val();
        const assignLicenses = $('#csv-assign-licenses').is(':checked');
        
        if (!fileInput.files[0]) {
            alert('Please select a CSV file.');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'llmsgaa_import_csv');
        formData.append('csv_file', fileInput.files[0]);
        formData.append('default_role', defaultRole);
        formData.append('assign_licenses', assignLicenses ? '1' : '0');
        formData.append('group_id', groupId);
        formData.append('nonce', ajaxNonce);
        
        const submitBtn = $('#csv-import-form button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('⏳ Processing CSV...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showCSVResults(response.data);
                } else {
                    alert('Error: ' + response.data);
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('Network error occurred');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }
    
    function showCSVResults(results) {
        let html = `
            <h3>CSV Import Results</h3>
            
            <div style="margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #155724;">${results.added}</div>
                        <div style="font-size: 12px; color: #155724;">Users Added</div>
                    </div>
                    <div style="text-align: center; padding: 15px; background: #fff3cd; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #856404;">${results.existing}</div>
                        <div style="font-size: 12px; color: #856404;">Already Users</div>
                    </div>
                    <div style="text-align: center; padding: 15px; background: #e3f2fd; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #1565c0;">${results.licenses_assigned}</div>
                        <div style="font-size: 12px; color: #1565c0;">Access Passes Assigned</div>
                    </div>
                    ${results.errors > 0 ? `
                    <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #721c24;">${results.errors}</div>
                        <div style="font-size: 12px; color: #721c24;">Errors</div>
                    </div>` : ''}
                </div>
            </div>
        `;
        
        if (results.messages && results.messages.length > 0) {
            html += '<div style="margin-bottom: 20px;">';
            html += '<h4 style="margin-bottom: 10px;">📋 Detailed Results:</h4>';
            html += '<div style="max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 6px; font-size: 13px;">';
            results.messages.forEach(message => {
                const icon = message.includes('Error') ? '❌' : message.includes('exists') ? '⚠️' : '✅';
                html += `<div style="margin-bottom: 5px;">${icon} ${message}</div>`;
            });
            html += '</div>';
            html += '</div>';
        }
        
        html += '<div style="text-align: right; border-top: 1px solid #eee; padding-top: 15px;">';
        html += '<button onclick="closeModalAndReload()" class="modal-btn-primary">🎉 Done - Refresh Page</button>';
        html += '</div>';
        
        showModal(html);
    }
    
    window.removeLicense = function(orderId) {
        if (!confirm('Are you sure you want to remove this license?')) return;
        
        $.post(ajaxurl, {
            action: 'llmsgaa_unassign_license',
            order_id: orderId,
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                alert('License removed successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    };
    
    // Initialize
    updateBulkActionsBar();
    console.log('✅ Bulk assign system fully loaded and initialized!');
});

// ========== ROLE CHANGE AND MEMBER MANAGEMENT HANDLERS ==========

jQuery(document).on('change', '.role-select', function() {
    const selectElement = jQuery(this);
    const newRole = selectElement.val();
    const memberRow = selectElement.closest('.member-row');
    const email = memberRow.data('email');
    const userId = memberRow.data('user-id');
    const memberName = memberRow.find('strong').text();
    
    if (!confirm(`Are you sure you want to change ${memberName}'s role to ${newRole}?`)) {
        selectElement.val(selectElement.data('previous-value') || 'member');
        return;
    }
    
    selectElement.prop('disabled', true);
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'llmsgaa_update_member_role',
            user_id: userId,
            group_id: groupId,
            email: email,
            role: newRole,
            nonce: ajaxNonce
        },
        success: function(response) {
            if (response.success) {
                showSuccessMessage(`${memberName}'s role updated to ${newRole}`);
                selectElement.data('previous-value', newRole);
                updateMemberRoleDisplay(memberRow, newRole);
            } else {
                selectElement.val(selectElement.data('previous-value') || 'member');
                alert('Error updating role: ' + response.data);
            }
        },
        error: function(xhr, status, error) {
            selectElement.val(selectElement.data('previous-value') || 'member');
            alert('AJAX error: ' + error);
        },
        complete: function() {
            selectElement.prop('disabled', false);
        }
    });
});

jQuery(document).on('click', '.remove-member-btn', function(e) {
    e.preventDefault();
    
    const button = jQuery(this);
    const memberRow = button.closest('.member-row');
    const email = memberRow.data('email');
    const userId = memberRow.data('user-id');
    const memberName = memberRow.find('strong').text();
    
    const confirmMessage = `Are you sure you want to remove ${memberName} (${email}) from this group?\n\nThis will:\n- Remove them from the group\n- Unassign all their Access Passes\n- Cannot be undone`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    const originalText = button.text();
    button.prop('disabled', true).text('Removing...');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'llmsgaa_remove_member',
            user_id: userId,
            group_id: groupId,
            email: email,
            nonce: ajaxNonce
        },
        success: function(response) {
            if (response.success) {
                showSuccessMessage(`${memberName} removed from group`);
                memberRow.fadeOut(300, function() {
                    memberRow.remove();
                    const remainingRows = jQuery('.member-row').length;
                    if (remainingRows === 0) {
                        const emptyMessage = `
                            <tr>
                                <td colspan="7" class="text-center text-gray-500 py-4">
                                    No users found. Add your first member using the "Add User" button above.
                                </td>
                            </tr>
                        `;
                        jQuery('.llmsgaa-table tbody').html(emptyMessage);
                    }
                });
            } else {
                alert('Error removing member: ' + response.data);
                button.prop('disabled', false).text(originalText);
            }
        },
        error: function(xhr, status, error) {
            console.error('Remove member AJAX error:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            alert('Error removing member: ' + error);
            button.prop('disabled', false).text(originalText);
        }
    });
});

jQuery(document).on('click', '.cancel-invite-btn', function(e) {
    e.preventDefault();
    
    const button = jQuery(this);
    const memberRow = button.closest('.member-row');
    const email = memberRow.data('email');
    const inviteId = button.data('invite-id');
    
    if (!confirm(`Are you sure you want to cancel the invitation for ${email}?`)) {
        return;
    }
    
    const originalText = button.text();
    button.prop('disabled', true).text('Cancelling...');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'llmsgaa_cancel_invite',
            invite_id: inviteId,
            group_id: groupId,
            email: email,
            nonce: ajaxNonce
        },
        success: function(response) {
            if (response.success) {
                showSuccessMessage(`Invitation for ${email} cancelled`);
                memberRow.fadeOut(300, function() {
                    memberRow.remove();
                    const remainingRows = jQuery('.member-row').length;
                    if (remainingRows === 0) {
                        const emptyMessage = `
                            <tr>
                                <td colspan="7" class="text-center text-gray-500 py-4">
                                    No members found. Add your first member using the "Add User" button above.
                                </td>
                            </tr>
                        `;
                        jQuery('.llmsgaa-table tbody').html(emptyMessage);
                    }
                });
            } else {
                alert('Error cancelling invitation: ' + response.data);
                button.prop('disabled', false).text(originalText);
            }
        },
        error: function(xhr, status, error) {
            console.error('Cancel invite AJAX error:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            alert('Error cancelling invitation: ' + error);
            button.prop('disabled', false).text(originalText);
        }
    });
});
</script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.location.pathname.startsWith('/group/')) {
      var widget = document.querySelector('[data-section="sidebar-widgets-header1"]');
      if (widget) {
        widget.style.display = 'none';
      }
    }
  });
</script>

<!-- change sticky header  on this page  not working 8/17/25-->
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.location.pathname.startsWith('/group/')) {
    const headerWidget = document.querySelector('[data-section="sidebar-widgets-header1"]');
    if (headerWidget) {
      const heading = headerWidget.querySelector('h1, h2, h3, .wp-block-heading, .widget-title, .kadence-blocks-advancedheading');
      if (heading) {
        heading.textContent = 'Manage Group'; // Replace with shorter fallback
      }
    }
  }
});
</script>
