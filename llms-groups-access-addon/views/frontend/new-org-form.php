<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $_GET['new_org_status'] ) && 'success' === $_GET['new_org_status'] ) {
    echo '<p class="llmsgaa-success">'
       . esc_html__( 'Thank you! Your group and Licenses have been created.', 'llms-groups-access-addon' )
       . '</p>';
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
  <?php wp_nonce_field( 'llmsgaa_new_org', 'llmsgaa_new_org_nonce' ); ?>
<input type="hidden" name="action" value="llmsgaa_new_org" />

  <p>
    <label><?php esc_html_e( 'Buyer Email:', 'llms-groups-access-addon' ); ?><br>
    <input type="email" name="new_org[email]" required class="widefat" /></label>
  </p>
  <p>
    <label><?php esc_html_e( 'Buyer Name:', 'llms-groups-access-addon' ); ?><br>
    <input type="text" name="new_org[name]" required class="widefat" /></label>
  </p>

  <fieldset class="llmsgaa-cart">
    <legend><?php esc_html_e( 'Cart Items', 'llms-groups-access-addon' ); ?></legend>
    <table class="llmsgaa-cart-table">
      <thead>
        <tr>
          <th><?php esc_html_e( 'SKU', 'llms-groups-access-addon' ); ?></th>
          <th><?php esc_html_e( 'Quantity', 'llms-groups-access-addon' ); ?></th>
          <th><?php esc_html_e( 'Remove', 'llms-groups-access-addon' ); ?></th>
        </tr>
      </thead>
      <tbody id="llmsgaa-cart-body">
        <tr class="llmsgaa-cart-row">
          <td><input type="text" name="new_org[items][0][sku]" required class="widefat" /></td>
          <td><input type="number" name="new_org[items][0][quantity]" required min="1" class="widefat" /></td>
          <td><button type="button" class="button llmsgaa-remove-row">&times;</button></td>
        </tr>
      </tbody>
    </table>
    <p><button type="button" class="button" id="llmsgaa-add-row"><?php esc_html_e( 'Add Another Item', 'llms-groups-access-addon' ); ?></button></p>
  </fieldset>

  <p>
    <label><?php esc_html_e( 'Organization Name:', 'llms-groups-access-addon' ); ?><br>
    <input type="text" name="new_org[org]" required class="widefat" /></label>
  </p>

  <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Submit Order', 'llms-groups-access-addon' ); ?></button></p>
</form>
