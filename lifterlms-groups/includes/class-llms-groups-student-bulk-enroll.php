<?php
/**
 * Bulk Enrollment
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.2.0
 */

defined( 'ABSPATH' ) || exit;

class LLMS_Groups_Student_Bulk_Enroll {

	/**
	 * Admin notices
	 *
	 * @var string[]
	 */
	public $admin_notices = array();

	/**
	 * Product (Group) ID
	 *
	 * @var int
	 */
	public $product_id = 0;

	/**
	 * Product Post Title
	 *
	 * @var string
	 */
	public $product_title = '';

	/**
	 * User IDs
	 *
	 * @var int
	 */
	public $user_ids = array();

	public function __construct() {
		// Hook into extra ui on users table to display product selection.
		add_action( 'manage_users_extra_tablenav', array( $this, 'display_product_selection_for_bulk_users' ) );

		// Hook into users table screen to process bulk enrollment.
		add_action( 'admin_head-users.php', array( $this, 'maybe_enroll_users_in_product' ) );

		// Display enrollment results as notices.
		add_action( 'admin_notices', array( $this, 'display_notices' ) );

		add_filter( 'manage_users_custom_column', array( $this, 'add_group_enrollments_count' ), 10, 3 );
	}

	public function add_group_enrollments_count( $output, $col_name, $user_id ) {
		if ( 'llms-enrollments' !== $col_name ) {
			return $output;
		}

		$student     = llms_get_student( $user_id );
		$enrollments = $student->get_enrollments( 'llms_group' );
		$int         = llms_groups()->get_integration();
		$output     .= '<br>' . $int->get_option( 'post_name_plural', 'Groups' ) . ': ' . $enrollments['found'];

		return $output;
	}

	/**
	 * Displays ui for selecting product to bulk enroll users into
	 *
	 * @param   string $which
	 */
	public function display_product_selection_for_bulk_users( $which ) {

		if ( ! current_user_can( 'manage_lifterlms' ) ) {
			return;
		}

		// The attributes need to be different for top and bottom of the table.
		$id     = 'bottom' === $which ? 'llms_bulk_enroll_group_product2' : 'llms_bulk_enroll_group_product';
		$submit = 'bottom' === $which ? 'llms_bulk_group_enroll2' : 'llms_bulk_group_enroll';

		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="_llms_bulk_enroll_product">
				<?php esc_html_e( 'Choose Group', 'lifterlms-groups' ); ?>
			</label>
			<select id="<?php echo esc_attr( $id ); ?>" data-placeholder="<?php esc_html_e( 'Choose Group', 'lifterlms-groups' ); ?>" class="llms-posts-select2 llms-bulk-enroll-product" data-post-type="llms_group" name="<?php echo esc_attr( $id ); ?>" style="min-width:200px;max-width:auto;">
			</select>
			<input type="submit" name="<?php echo esc_attr( $submit ); ?>" id="<?php echo esc_attr( $submit ); ?>" class="button" value="<?php esc_attr_e( 'Enroll', 'lifterlms' ); ?>">
		</div>
		<?php
	}

	/**
	 * Conditionally enrolls multiple users into a product
	 *
	 * @return  void
	 */
	public function maybe_enroll_users_in_product() {

		// Verify bulk enrollment request.
		$do_bulk_enroll = $this->_bottom_else_top( 'llms_bulk_group_enroll' );

		// Bail if this is not a bulk enrollment request.
		if ( empty( $do_bulk_enroll ) ) {
			return;
		}

		// Get the product (group) to enroll users in.
		$this->product_id = $this->_bottom_else_top( 'llms_bulk_enroll_group_product', FILTER_VALIDATE_INT );

		if ( empty( $this->product_id ) ) {
			$message = __( 'Please select a Group to enroll users into!', 'lifterlms-groups' );
			$this->generate_notice( 'error', $message );
			return;
		}

		if ( ! current_user_can( 'manage_group_members', $this->product_id ) ) {
			$message = __( 'You do not have permission to enroll users into this group.', 'lifterlms-groups' );
			$this->generate_notice( 'error', $message );
			return;
		}

		// Get the product title for notices.
		$this->product_title = get_the_title( $this->product_id );

		// Get all the user ids to enroll.
		$this->user_ids = filter_input( INPUT_GET, 'users', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		if ( empty( $this->user_ids ) ) {
			$message = sprintf( __( 'Please select users to enroll into <em>%s</em>.', 'lifterlms' ), $this->product_title );
			$this->generate_notice( 'error', $message );
			return;
		}

		$this->enroll_users_in_product();
	}

	/**
	 * Retrieves submitted inputs
	 *
	 * @param   string $param The input key
	 * @param   mixed  $validation Validation filter constant
	 * @return  mixed The submitted input value
	 */
	private function _bottom_else_top( $param, $validation = FILTER_DEFAULT ) {

		$return_val = false;

		// Get the value of the input displayed at the bottom of users table.
		$bottom_value = filter_input( INPUT_GET, $param . '2', $validation );

		// Get the value of input displayed at the top of users table.
		$top_value = filter_input( INPUT_GET, $param, $validation );

		// Prefer top over bottom, just like WordPress does.
		if ( ! empty( $bottom_value ) ) {
			$return_val = $bottom_value;
		}
		if ( ! empty( $top_value ) ) {
			$return_val = $top_value;
		}

		return $return_val;
	}

	/**
	 * Enrolls multiple users into a product
	 */
	private function enroll_users_in_product() {

		// Get user information from user ids.
		$users = $this->get_users( $this->user_ids );

		// Bail if for some reason, no users are found (because they were deleted in the bg?).
		if ( empty( $users ) ) {
			$message = sprintf( __( 'No such users found. Cannot enroll into <em>%s</em>.', 'lifterlms' ), $this->product_title );
			$this->generate_notice( 'error', $message );
			return;
		}

		$group = new LLMS_Group( $this->product_id );

		// Create manual enrollment trigger.
		$trigger = 'admin_' . get_current_user_id();

		foreach ( $users as $user ) {
			$this->enroll( $user, $trigger );
		}

		// Clear the cache by fetching the updated group members count.
		$group->get_members_count( false );
	}

	/**
	 * Get user details from user IDs

	 * @param   array $user_ids WP user IDs
	 * @return  array User details
	 */
	private function get_users( $user_ids ) {

		// Prepare query arguments.
		$user_query_args = array(
			'include' => $user_ids,
			// We need display names for notices.
			'fields'  => array( 'ID', 'display_name' ),
		);

		$user_query = new WP_User_Query( $user_query_args );

		$results = $user_query->get_results();

		return empty( $results ) ? false : $results;
	}

	/**
	 * Enrolls a user into the selected product
	 *
	 * @param   stdClass $user User object
	 * @param   string   $trigger Enrollment trigger string
	 */
	private function enroll( $user, $trigger ) {
		global $wpdb;

		$enrolled = false;

		$user = get_user_by( 'ID', $user->ID );

		// Get group invitation for this user's email, if it exists.
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}lifterlms_group_invitations WHERE email = %s AND group_id = %d LIMIT 1", $user->user_email, $this->product_id ) );

		if ( $id ) {
			$invitation = llms_groups()->invitations()->get( $id );

			$enrolled = LLMS_Groups_Enrollment::add( $user->ID, $this->product_id, $trigger );

			if ( $enrolled ) {
				$invitation->delete();
			}

			$this->generate_notice( 'error', __( 'Invitation found.', 'lifterlms-groups' ) . ' ' . sprintf( __( 'Failed to enroll <em>%1$1s</em> into <em>%2$2s</em> or user is already a member of the group course/membership..', 'lifterlms-groups' ), $user->display_name, $this->product_title ) );

			return;
		}

		// There's no invitation for the user, see if the group has any seats left.
		$group = new LLMS_Group( $this->product_id );
		if ( ! $group->has_open_seats( false ) ) {
			$this->generate_notice( 'error', sprintf( __( 'Failed to enroll <em>%1$1s</em> into <em>%2$2s</em>.', 'lifterlms-groups' ), $user->display_name, $this->product_title ) . ' ' . __( 'No open seats available.', 'lifterlms-groups' ) );

			return;
		}

		// Enroll into LifterLMS product.
		$enrolled = LLMS_Groups_Enrollment::add( $user->ID, $this->product_id, $trigger );

		// Figure out notice type based on enrollment success.
		$type = ( ! $enrolled ) ? 'error' : 'success';

		// Figure out notice message string based on notice type.
		$success_fail_string = ( ! $enrolled ) ? __( 'Failed to enroll <em>%1$1s</em> into <em>%2$2s</em>.', 'lifterlms-groups' ) : __( 'Successfully enrolled <em>%1$1s</em> into <em>%2$2s</em>.', 'lifterlms-groups' );

		// Get formatted message with username and product title.
		$message = sprintf( $success_fail_string, $user->display_name, $this->product_title );

		// Generate a notice for display.
		$this->generate_notice( $type, $message );
	}

	/**
	 * Generates admin notice markup
	 *
	 * @param   string $type Type of notice 'error' or 'success'
	 * @param   string $message Notice message
	 */
	public function generate_notice( $type, $message ) {
		ob_start();
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
			<p><?php echo wp_kses_post( $message ); ?></p>
		</div>
		<?php
		$notice                = ob_get_clean();
		$this->admin_notices[] = $notice;
	}

	/**
	 * Displays all generated notices
	 *
	 * @return  void
	 * @since   1.2.0
	 */
	public function display_notices() {
		if ( empty( $this->admin_notices ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Notices are escaped in generate_notice().
		echo implode( "\n", $this->admin_notices );
	}
}

return new LLMS_Groups_Student_Bulk_Enroll();
