<?php
/**
 * LifterLMS Groups Profile methods
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.15
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Profile class
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.4 Show affiliated post's type in the Course/Membership option.
 *                     Conditionally 404 group profiles based on group settings and user membership.
 * @since 1.0.0-beta.7 Fixed pagination not working in profile tabs.
 */
class LLMS_Groups_Profile {

	/**
	 * Name of the query var (rewrite tag) used to add endpoints (tabs) to the group profile pages
	 *
	 * @var  string
	 */
	const TAB_QUERY_VAR = 'llms_group_profile_tab';

	/**
	 * Static constructor
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.4 Add `maybe_serve_404()`.
	 * @since 1.0.0-beta.7 Added action 'avoid_redirects_in_paged_tabs' as `parse_query` hook callback.
	 * @return void
	 */
	public static function init() {

		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_action( 'parse_query', array( __CLASS__, 'avoid_redirects_in_paged_tabs' ) );
		add_action( 'wp', array( __CLASS__, 'maybe_serve_404' ) );
	}

	/**
	 * Retrieve the slug/id of the current profile tab being viewed.
	 *
	 * If this is called outside the loop or the $post global is not an
	 * llms_group post type it will always return false.
	 *
	 * If the current tab is not set it will default to the first tab on the
	 * profile's navigation ('about').
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.15 Handle translated tab slugs.
	 *
	 * @return string|false Tab slug/id or false.
	 */
	public static function get_current_tab() {

		if ( ! is_llms_group() ) {
			return false;
		}

		global $wp_query;

		if ( ! empty( $wp_query->query_vars[ self::TAB_QUERY_VAR ] ) ) {
			$tab_id = array_search( $wp_query->query_vars[ self::TAB_QUERY_VAR ], self::get_tab_slugs(), true );
		}

		if ( empty( $tab_id ) ) {
			$tabs   = array_keys( self::get_navigation( false ) );
			$tab_id = empty( $tabs ) ? false : $tabs[0];
		}

		return $tab_id;
	}

	/**
	 * Retrieve group profile navigation links and data.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.15 Add trailing slash to the navigation URLs.
	 * @since 1.0.0-beta.20 Add trailing slash to the navigation URLs, only if the permalink structure requires them.
	 *                      Fixed links when using a permastruct without a trailing slash.
	 * @param boolean $get_active If `true`, add information about the currently active tab.
	 * @return array
	 */
	public static function get_navigation( $get_active = true ) {

		$permalink = trailingslashit( get_permalink() );

		$nav = array(
			'about'   => array(
				'title' => __( 'About', 'lifterlms-groups' ),
				'url'   => user_trailingslashit( $permalink . self::get_tab_slug( 'about' ) ),
			),
			'members' => array(
				'title' => __( 'Members', 'lifterlms-groups' ),
				'url'   => user_trailingslashit( $permalink . self::get_tab_slug( 'members' ) ),
			),
		);

		if ( current_user_can( 'view_group_reporting', get_the_ID() ) ) {
			$nav['reports'] = array(
				'title' => __( 'Reports', 'lifterlms-groups' ),
				'url'   => user_trailingslashit( $permalink . self::get_tab_slug( 'reports' ) ),
			);
		}

		if ( current_user_can( 'manage_group_information', get_the_ID() ) ) {
			$nav['settings'] = array(
				'title' => __( 'Settings', 'lifterlms-groups' ),
				'url'   => user_trailingslashit( $permalink . self::get_tab_slug( 'settings' ) ),
			);
		}

		if ( $get_active ) {
			$curr = self::get_current_tab();
			if ( $curr && isset( $nav[ $curr ] ) ) {
				$nav[ $curr ]['active'] = true;
			}
		}

		return apply_filters( 'llms_groups_profile_navigation', $nav, $get_active );
	}

	/**
	 * Retrieve a translated slug for a given profile tab.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param string $tab Tab id/key.
	 * @return string
	 */
	public static function get_tab_slug( $tab ) {

		$tabs = self::get_tab_slugs();

		return isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : $tab;
	}

	/**
	 * Retrieve the translated tab slugs
	 *
	 * @since 1.0.0-beta.15
	 *
	 * @return array Returns an associative array of tab ID => translated slug.
	 */
	public static function get_tab_slugs() {

		$tabs = array(
			'about'    => _x( 'about', 'Group profile tab slug', 'lifterlms-groups' ),
			'members'  => _x( 'members', 'Group profile tab slug', 'lifterlms-groups' ),
			'reports'  => _x( 'reports', 'Group profile tab slug', 'lifterlms-groups' ),
			'settings' => _x( 'settings', 'Group profile tab slug', 'lifterlms-groups' ),
		);

		/**
		 * Filters the profile tab slugs
		 *
		 * @since 1.0.0-beta.1
		 *
		 * @param array $tabs Associative array of tab slugs.
		 * @return array
		 */
		return apply_filters( 'llms_groups_profile_tab_slugs', $tabs );
	}

	/**
	 * Retrieve an array of fields for the group settings tab
	 *
	 * Each element of the returned array is parsed by `llms_form_field()` to generate
	 * the HTML for the settings.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.4 Show affiliated post's type in the Course/Membership option.
	 * @since 1.0.0-beta.8 Added filter on the return array.
	 *
	 * @param LLMS_Group $group Group object.
	 * @return array[]
	 */
	public static function get_settings( $group ) {

		$int      = llms_groups()->get_integration();
		$singular = $int->get_option( 'post_name_singular', 'Group' );
		$plural   = $int->get_option( 'post_name_plural', 'Groups' );

		$settings = array(
			array(
				'id'       => 'llms_group_title',
				'label'    => __( 'Name', 'lifterlms-groups' ),
				'required' => true,
				'value'    => $group->get( 'title' ),
			),
			array(
				// Translators: %s = Singular group name.
				'description' => sprintf( __( 'Changing this value will change the %s URL and may cause errors for anyone with saved bookmarks.', 'lifterlms-groups' ), $singular ),
				'id'          => 'llms_group_slug',
				'label'       => __( 'Web Address Slug', 'lifterlms-groups' ),
				'required'    => true,
				'value'       => $group->get( 'name' ),
			),
			array(
				'id'       => 'llms_group_visibility',
				'label'    => __( 'Visibility', 'lifterlms-groups' ),
				'required' => true,
				'type'     => 'select',
				'options'  => $int->get_visibility_options(),
				'value'    => $group->get( 'visibility' ),
				'default'  => 'private',
			),
		);

		if ( current_user_can( 'publish_groups' ) ) {

			$selected = '';
			$post_id  = $group->get( 'post_id' );
			if ( $post_id ) {
				$post_type_obj = get_post_type_object( get_post_type( $post_id ) );
				$selected      = sprintf( '%1$s (%2$s)', get_the_title( $post_id ), $post_type_obj->labels->singular_name );
			}

			$settings[] = array(
				'id'          => 'llms_group_post_id',
				'label'       => __( 'Course or Membership', 'lifterlms-groups' ),
				'required'    => true,
				'placeholder' => __( 'Search for a course or membership...', 'lifterlms-groups' ),
				'value'       => $selected,
			);

		}

		/**
		 * Filter group settings fields
		 *
		 * @since 1.0.0-beta.8
		 *
		 * @param array[]    $settings Array of settings fields to be parsed by `llms_form_field()`.
		 * @param LLMS_Group $group    Group object.
		 */
		return apply_filters( 'llms_groups_profile_get_settings', $settings, $group );
	}

	/**
	 * Retrieve the URL for a profile tab.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param string $tab Tab ID.
	 * @return string
	 */
	public static function get_tab_url( $tab ) {

		$nav = self::get_navigation( false );
		return isset( $nav[ $tab ] ) ? $nav[ $tab ]['url'] : $tab;
	}

	/**
	 * Determines whether or not a group profile can be viewed by the current user.
	 *
	 * Sets 404 status on the global `$wp_query` and sends 404 status headers when:
	 * + A logged out user visits a closed or private group
	 * + A logged in user visits a closed group they are not a member of
	 *
	 * Users with the `manage_lifterlms` capability can bypass these restrictions.
	 *
	 * @since 1.0.0-beta.4
	 *
	 * @return null|bool Returns `null` on non group profile pages, `true` when a 404 is served, and `false` otherwise.
	 */
	public static function maybe_serve_404() {

		// Return early when we're not viewing a group.
		if ( ! is_llms_group() ) {
			return null;
		}

		$group      = get_llms_group();
		$visibility = $group->get( 'visibility' );

		// Group is open so we can show it to everyone.
		if ( 'open' === $visibility ) {
			return self::handle_404( false, $group );
		}

		$uid = get_current_user_id();
		if ( $uid ) {

			/**
			 * Don't 404 for logged in users who:
			 * 1. Visit a private group: private groups are visible to all logged-in users.
			 * 2. Can "manage_lifterlms": Admins and LMS Managers can always view the group regardless of their enrollment status in the group.
			 * 3. Visit a closed group that they are enrolled in.
			 */
			if ( 'private' === $visibility || current_user_can( 'manage_lifterlms' ) || ( 'closed' === $visibility && llms_is_user_enrolled( $uid, $group->get( 'id' ) ) ) ) {
				return self::handle_404( false, $group );
			}
		}

		// Logged out user viewing a private/closed group or a logged in user viewing a closed group they are not a member of.
		return self::handle_404( true, $group );
	}

	/**
	 * Handle serving a 404
	 *
	 * The logic for determining whether or not a 404 will actually be served can be found in LLMS_Groups_Profile::maybe_serve_404().
	 *
	 * @since 1.0.0-beta.4
	 *
	 * @param boolean    $serve_404 Whether or not to serve a 404. If `true`, a 404 will be served.
	 * @param LLMS_Group $group     Group object for the group being viewed.
	 * @return boolean
	 */
	protected static function handle_404( $serve_404, $group ) {

		/**
		 * Determines whether or not to serve a 404 for the currently viewed group
		 *
		 * @since 1.0.0-beta.4
		 *
		 * @param boolean    $serve_404 Whether or not to serve a 404. If `true`, a 404 will be served.
		 * @param LLMS_Group $group     Group object for the group being viewed.
		 */
		$serve_404 = apply_filters( 'llms_groups_profile_serve_404', $serve_404, $group );

		if ( $serve_404 ) {

			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();

		}

		return $serve_404;
	}

	/**
	 * Register rewrite rules for profile tabs.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public static function register_rewrites() {

		$obj  = get_post_type_object( LLMS_Groups_Post_Type::POST_TYPE );
		$slug = $obj->rewrite['slug'];

		add_rewrite_tag( '%' . self::TAB_QUERY_VAR . '%', '([^/]*)' );

		// EG: whatever.com/groups/{$groupname}/{$tabname}/page/{$pagenumber}. // phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- This is not commented out code.
		add_rewrite_rule(
			sprintf( '^%1$s/([^/]*)/?([^/]*)/page/([0-9]{1,})/?', $slug ),
			sprintf( 'index.php?post_type=%1$s&name=$matches[1]&%2$s=$matches[2]&paged=$matches[3]', LLMS_Groups_Post_Type::POST_TYPE, self::TAB_QUERY_VAR ),
			'top'
		);

		// EG: whatever.com/groups/{$groupname}/{$tabname}.
		add_rewrite_rule(
			sprintf( '^%1$s/([^/]*)/?([^/]*)/?', $slug ),
			sprintf( 'index.php?post_type=%1$s&name=$matches[1]&%2$s=$matches[2]', LLMS_Groups_Post_Type::POST_TYPE, self::TAB_QUERY_VAR ),
			'top'
		);
	}

	/**
	 * Fixes pagination not working in groups profile tabs
	 *
	 * @since 1.0.0-beta.7
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public static function avoid_redirects_in_paged_tabs( $query ) {
		// Nested if for readibility only.
		if ( isset( $query->query_vars['post_type'] ) && 'llms_group' === $query->query_vars['post_type'] ) {
			if ( $query->is_singular && -1 === $query->current_post && $query->is_paged ) {
				add_filter( 'redirect_canonical', '__return_false' );
			}
		}
	}
}

return LLMS_Groups_Profile::init();
