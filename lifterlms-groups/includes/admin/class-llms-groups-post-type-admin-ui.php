<?php
/**
 * Manage the admin UI for the Groups post type.
 *
 * @package LifterLMS/Classes/Admin
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Post_Type_Admin_UI class
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.5 Improve members and content columns header wording.
 */
class LLMS_Groups_Post_Type_Admin_UI {

	/**
	 * Constructor
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'bulk_actions-edit-' . LLMS_Groups_Post_Type::POST_TYPE, array( $this, 'bulk_edit_actions' ) );

		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );

		add_filter( 'manage_' . LLMS_Groups_Post_Type::POST_TYPE . '_posts_columns', array( $this, 'custom_cols' ) );
		add_action( 'manage_' . LLMS_Groups_Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'custom_cols_content' ), 10, 2 );

		add_action( 'current_screen', array( $this, 'maybe_redirect' ) );
	}

	/**
	 * Modify the bulk edit actions list on the groups post table.
	 *
	 * This removes "Edit" from the list of bulk edit actions.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param array[] $actions List of actions.
	 * @return array[]
	 */
	public function bulk_edit_actions( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	/**
	 * Add custom post table columns.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.5 Improve members and content columns header wording.
	 *
	 * @param string[] $cols Existing post table cols.
	 * @return string[]
	 */
	public function custom_cols( $cols ) {

		unset( $cols['date'] );

		$cols['admin']   = __( 'Primary Admin', 'lifterlms-groups' );
		$cols['members'] = __( 'Members / Seats', 'lifterlms-groups' );
		$cols['content'] = __( 'Course or Membership', 'lifterlms-groups' );

		return $cols;
	}

	/**
	 * Output custom post table column content
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param string $col      Column key.
	 * @param int    $group_id WP_Post ID of the group.
	 * @return void
	 */
	public function custom_cols_content( $col, $group_id ) {

		$group = llms_get_post( $group_id );
		if ( ! $group ) {
			return;
		}

		switch ( $col ) {

			case 'admin':
				$user = new WP_User( $group->get( 'author' ) );
				$url  = get_edit_user_link( $user->ID );
				echo '<a href="' . esc_url( $url ) . '">' . $user->display_name . '</a>';
				break;

			case 'members':
				$seats = $group->get_seats();
				printf( '%1$d / %2$d', $seats['used'], $seats['total'] );
				break;

			case 'content':
				$post = $group->get( 'post_id' );
				echo $post ? '<a href="' . esc_url( get_edit_post_link( $post ) ) . '">' . get_the_title( $post ) . '</a>' : '&ndash;';
				break;

		}
	}

	/**
	 * Redirect group edits to the frontend.
	 *
	 * Any access to post-new.php or edit.php for a group post type is redirected to the frontend where everything
	 * can be edited. This creates a unified edit experience for admins and group managers and keeps our codebase
	 * a bit simpler as we don't need to create two methods of editing all group information.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0 Replaced use of the deprecated `FILTER_SANITIZE_STRING` constant.
	 *
	 * @return false|void
	 */
	public function maybe_redirect() {

		$screen = get_current_screen();

		if ( LLMS_Groups_Post_Type::POST_TYPE === $screen->id ) {

			$id = null;

			if ( 'add' === $screen->action ) {
				$group = llms_create_group();
				$id    = $group->get( 'id' );
			} elseif ( 'edit' === llms_filter_input( INPUT_GET, 'action' ) ) {
				$id = llms_filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
			}

			if ( $id ) {
				llms_redirect_and_exit( get_permalink( $id ) . LLMS_Groups_Profile::get_tab_slug( 'settings' ) );
			}
		}

		return false;
	}

	/**
	 * Modify actions available for group posts via the groups posts table
	 *
	 * Removes the "Quick Edit" link.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param array[] $actions Array of action link html.
	 * @param WP_Post $post    Post object.
	 * @return array[]
	 */
	public function post_row_actions( $actions, $post ) {

		if ( LLMS_Groups_Post_Type::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		// Disable quick edit.
		unset( $actions['inline hide-if-no-js'] );

		return $actions;
	}
}

return new LLMS_Groups_Post_Type_Admin_UI();
