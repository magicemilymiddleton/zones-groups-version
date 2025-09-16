<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Get current group ID
$group_id = absint( get_queried_object_id() );

// Display success notice if settings were saved
if ( isset( $_GET['updated'] ) ) : ?>
  <div class="notice notice-success">
    <p><?php esc_html_e( 'Group settings saved.', 'llms-groups-access-addon' ); ?></p>
  </div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
  <?php wp_nonce_field( 'llmsgroups_update_group', 'llmsgroups_update_nonce' ); ?>
  <input type="hidden" name="action" value="llmsgroups_update" />
  <input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>" />

  <p>
    <label for="group_title"><?php esc_html_e( 'Group Title', 'llms-groups-access-addon' ); ?></label><br>
    <input
      type="text"
      id="group_title"
      name="group_title"
      class="widefat"
      value="<?php echo esc_attr( get_the_title( $group_id ) ); ?>"
      required
    />
  </p>

  <p>
    <label for="group_slug"><?php esc_html_e( 'Group Slug', 'llms-groups-access-addon' ); ?></label><br>
    <input
      type="text"
      id="group_slug"
      name="group_slug"
      class="widefat"
      value="<?php echo esc_attr( get_post_field( 'post_name', $group_id ) ); ?>"
      pattern="[a-z0-9\-]+"
      required
    />
  </p>

  <p>
    <button type="submit" class="button button-primary">
      <?php esc_html_e( 'Save Settings', 'llms-groups-access-addon' ); ?>
    </button>
  </p>
</form>
