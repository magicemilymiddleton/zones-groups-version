<?php
/**
 * Group directory
 *
 * File description.
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Directory class.
 *
 * @since 1.0.0-beta.1
 */
class LLMS_Groups_Directory {

	/**
	 * Class instance.
	 *
	 * @var self $instance The class instance.
	 */
	private static $instance;

	/**
	 * Get the class instance.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Static constructor.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0 Return the class instance.
	 *
	 * @return self
	 */
	public static function init(): self {
		add_action( 'wp', array( __CLASS__, 'handle_404' ) );
		add_filter( 'the_content', array( __CLASS__, 'output_directory' ) );

		return self::get_instance();
	}

	/**
	 * Retrieve the ID of the directory page as configured in the global integration settings.
	 *
	 * In order to return a page ID the directory visibility must not be "closed" and a page must be selected.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @return int|false The `WP_Post` id of the page or `false` if not configured or disabled.
	 */
	public static function get_page() {

		$int       = llms_groups()->get_integration();
		$visible   = 'closed' !== $int->get_option( 'visibility' );
		$directory = $int->get_option( 'directory_page_id' );

		return ( $visible && $directory && get_page( $directory ) ) ? absint( $directory ) : false;
	}

	/**
	 * Setup 404s when the current visitor is not allowed to access the directory.
	 *
	 * Sets 404 status on the global `$wp_query` and sends 404 status headers when a logged out user
	 * attempts to access the directory when it's in "private" visibility mode.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @return null|bool Returns `null` on non directory pages, `true` when a 404 is served, and `false` otherwise.
	 */
	public static function handle_404() {

		if ( ! self::is_directory() ) {
			return null;
		}

		$visibility = llms_groups()->get_integration()->get_option( 'visibility' );
		if ( 'private' === $visibility && ! get_current_user_id() ) {

			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();

			return true;

		}

		return false;
	}

	/**
	 * Determine if the the post is the Group Directory page.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param int|null $id WP_Post ID to test against. Uses the id from the global `$post` if none is supplied.
	 * @return boolean
	 */
	public static function is_directory( $id = null ) {

		$id = $id ? $id : get_the_ID();
		return ( absint( $id ) === self::get_page() );
	}

	/**
	 * Get groups list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional arguments.
	 * @return string
	 */
	public static function get_groups_list( array $args = array() ): string {
		// Temporarily hijack the main `WP_Query`.
		global $wp_query;

		$temp = $wp_query;

		if ( ! get_current_user_id() ) {
			// Basically don't show anything to the public since groups are either private or closed.
			$args = array_merge(
				array(
					'post_type'      => 'llms_group',
					'posts_per_page' => 10,
					'paged'          => max( 1, $wp_query->get( 'paged' ) ),
					'meta_key'       => '_llms_visibility', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => array( 'closed', 'private' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'meta_compare'   => 'NOT IN',
				),
				$args
			);
		} else {
			$student_group_ids = llms_get_student() ? llms_get_student()->get_enrollments( 'llms_group', array( 'limit' => 500 ) )['results'] : array();

			// Only show groups that are not closed unless the student is a member.
			if ( ! isset( $args['post__in'] ) || ! $args['post__in'] ) {
				// Start with just private visibility groups.
				$args['post__in'] = get_posts(
					array(
						'post_type'      => 'llms_group',
						'posts_per_page' => 500,
						'meta_query'     => array(
							array(
								'key'     => '_llms_visibility',
								'value'   => 'private',
								'compare' => '=',
							),
						),
						'fields'         => 'ids',
					)
				);

				// Add in groups the user is in regardless of visibility, so this is the only way Closed groups will show.
				$args['post__in'] = array_merge( $args['post__in'], $student_group_ids );
			} else {
				if ( ! isset( $args['post__in'] ) ) {
					$args['post__in'] = array();
				}

				// Don't show closed groups in the specified groups unless the student is a member. If it's private they don't have to be.
				$closed_groups_to_maybe_include = get_posts(
					array(
						'post_type'      => 'llms_group',
						'posts_per_page' => 500,
						'post__in'       => $args['post__in'],
						'meta_query'     => array(
							array(
								'key'     => '_llms_visibility',
								'value'   => 'closed',
								'compare' => '=',
							),
						),
						'fields'         => 'ids',
					)
				);
				// Get the IDs that are closed but not in the student list of groups.
				$closed_groups_to_exclude = array_diff( $closed_groups_to_maybe_include, $student_group_ids );

				// Remove the exclude IDs from the post__in array.
				$args['post__in'] = array_diff( (array) $args['post__in'], $closed_groups_to_exclude );

				// If there's no posts left, make it so nothing appears.
				if ( empty( $args['post__in'] ) ) {
					$args['post__in'] = array( -1 );
				}
			}

			$args = array_merge(
				array(
					'post_type'      => 'llms_group',
					'posts_per_page' => 10,
					'paged'          => max( 1, $wp_query->get( 'paged' ) ),
				),
				$args
			);
		}

		/**
		 * Customize the default WP_Query parameters used to generate the groups directory.
		 *
		 * @since 1.0.0-beta.1
		 *
		 * @param array $arg Arguments passed to a new `WP_Query()`.
		 */
		$args = apply_filters( 'llms_groups_directory_query', $args );

		$wp_query = new WP_Query( $args ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- I do what I want.

		ob_start();
		do_action( 'llms_groups_directory_loop' );
		$html = ob_get_clean();

		// Restore the initial `WP_Query`.
		$wp_query = $temp; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- I do what I want.
		wp_reset_postdata();

		return $html;
	}

	/**
	 * Outputs the groups directory list.
	 *
	 * This is attached to the `the_content` filter on the directory page. This allows all content stored on the page
	 * to be output and the directory listing/pagination will be appended to the page's content.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0 Added `$args` parameter.
	 *              Move most of the logic into `self::get_groups_list()`.
	 *
	 * @param string $content Existing page content.
	 * @param array  $args    Optional arguments.
	 * @return string
	 */
	public static function output_directory( string $content, array $args = array() ): string {

		// Return early on non-directory pages.
		if ( ! self::is_directory() ) {
			return $content;
		}

		return $content . self::get_groups_list( $args );
	}
}

return LLMS_Groups_Directory::init();
