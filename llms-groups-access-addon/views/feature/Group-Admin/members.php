<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * View: GroupAdmin Members Table
 */

$group_id = $group_id ?? get_the_ID();
$orders   = $orders ?? [];
$pending  = $pending ?? [];

$pending_emails = array_map( fn( $inv ) => $inv->email, $pending ?? [] );

$active = $open = [];
foreach ( $orders as $order ) {
    $sid   = absint( get_post_meta( $order->ID, 'student_id', true ) );
    $email = sanitize_email( get_post_meta( $order->ID, 'student_email', true ) );

    if ( $sid ) {
        $active[] = $order;
    } elseif ( ! in_array( $email, $pending_emails, true ) ) {
        $open[] = $order;
    }
}
?>

<section class="llms-group-members-overhaul space-y-6" style="border: solid; border-color: #333333; border-radius: 10px; padding: 10px; margin-bottom: 50px;">
  <h2 class="text-xl font-semibold">
    <?php esc_html_e( 'Licenses & Invitations', 'llms-groups-access-addon' ); ?>
  </h2>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=llmsgaa_save_group_seats' ) ); ?>">
    <?php wp_nonce_field( 'llmsgaa_save_group_seats', 'llmsgaa_group_seats_nonce' ); ?>
    <input type="hidden" name="action" value="llmsgaa_save_group_seats">
    <input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>">

    <div class="overflow-x-auto">
      <table class="llmsgaa-table w-full min-w-[1000px] text-sm border rounded-xl">
        <thead class="bg-gray-100">
          <tr>
            <th><?php esc_html_e( 'Email', 'llms-groups-access-addon' ); ?></th>
            <th><?php esc_html_e( 'Name', 'llms-groups-access-addon' ); ?></th>
            <th><?php esc_html_e( 'Status', 'llms-groups-access-addon' ); ?></th>
            <th><?php esc_html_e( 'Course / Membership', 'llms-groups-access-addon' ); ?></th>
            <th><?php esc_html_e( 'Start Date', 'llms-groups-access-addon' ); ?></th>
            <th><?php esc_html_e( 'End Date & Renew', 'llms-groups-access-addon' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( ['active' => $active, 'open' => $open] as $label => $set ) : ?>
            <?php if ( empty( $set ) ) continue; ?>
            <tr><td colspan="6"><strong><?php echo esc_html( ucfirst( $label ) . ' Seats' ); ?></strong></td></tr>

            <?php foreach ( $set as $order ) :
              $order_id = $order->ID;
              $sid      = absint( get_post_meta( $order_id, 'student_id', true ) );
              $user     = $sid ? get_user_by( 'ID', $sid ) : false;

              $email = $user
                ? sanitize_email( $user->user_email )
                : ( get_post_meta( $order_id, 'student_email', true ) ?: get_post_meta( $order_id, '_email_address', true ) );

              $first = trim( $user->first_name ?? '' );
              $last  = trim( $user->last_name ?? '' );
              $name  = trim( "{$first} {$last}" );
              if ( empty( $name ) ) {
                  $name = $user->display_name ?? $user->user_login ?? '';
              }

              $status    = sanitize_text_field( get_post_meta( $order_id, 'status', true ) ) ?: ( $label === 'active' ? 'active' : '—' );
              $course_id = absint( get_post_meta( $order_id, 'product_id', true ) );
              $course    = $course_id ? get_the_title( $course_id ) : '';
              $start_raw = get_post_meta( $order_id, 'start_date', true );
              $end_raw   = get_post_meta( $order_id, 'end_date', true );
              $start     = $start_raw ? date_i18n( 'F j, Y', strtotime( $start_raw ) ) : '';
              $end       = $end_raw ? date_i18n( 'F j, Y', strtotime( $end_raw ) ) : '';
            ?>
            <tr>
              <td>
                <input 
                  type="email" 
                  name="order_email[<?php echo esc_attr( $order_id ); ?>]" 
                  value="<?php echo esc_attr( $email ); ?>" 
                  placeholder="<?php esc_attr_e( 'student@example.com', 'llms-groups-access-addon' ); ?>"
                  class="border rounded px-2 py-1 w-full placeholder:text-[#d3d3d3]"
                />
              </td>
              <td>
                <?php if ( $user ) : ?>
                  <?php echo esc_html( $name ); ?>
                <?php else : ?>
                  &mdash;
                <?php endif; ?>
              </td>
              <td><?php echo esc_html( ucfirst( $status ) ); ?></td>
              <td><?php echo esc_html( $course ); ?></td>
              <td><?php echo esc_html( $start ); ?></td>
              <td>
                <?php echo esc_html( $end ); ?><br>
                <?php if ( $user ) : ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <?php if ( ! empty( $pending ) ) : ?>
          <tr><td colspan="6"><strong><?php esc_html_e( 'Pending Invitations', 'llms-groups-access-addon' ); ?></strong></td></tr>
          <?php foreach ( $pending as $inv ) : ?>
            <tr>
              <td><?php echo esc_html( $inv->email ); ?></td>
              <td>—</td>
              <td><?php esc_html_e( 'Pending', 'llms-groups-access-addon' ); ?></td>
              <td>—</td>
              <td>—</td>
              <td>
                <?php
                $cancel_url = wp_nonce_url(
                  add_query_arg([
                    'action'   => 'llmsgaa_cancel_invite',
                    'group_id' => $group_id,
                    'email'    => rawurlencode( $inv->email ),
                  ], admin_url( 'admin-post.php' )),
                  'llmsgaa_cancel_invite_action',
                  'llmsgaa_cancel_invite_nonce'
                );
                ?>
                <a href="<?php echo esc_url( $cancel_url ); ?>" class="text-red-600 hover:underline text-sm">
                  <?php esc_html_e( 'Cancel Invitation', 'llms-groups-access-addon' ); ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-4 text-right">
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded">
        <?php esc_html_e( 'Update', 'llms-groups-access-addon' ); ?>
      </button>
    </div>
  </form>
</section>
