<?php
/**
 * Manage group-related student dashboard modifications
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.20
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Student_Dashboard class.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.4 Always register the tab and then de-register it conditionally on account page load for logged in users without any group affiliations.
 */
class LLMS_Groups_Student_Dashboard {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'llms_get_student_dashboard_tabs', array( $this, 'register_tab' ) );
		add_filter( 'llms_get_student_dashboard_tabs', array( $this, 'maybe_hide_tab' ), 20 );
	}

	/**
	 * Conditionally de-registers the my groups tab on the student dashboard.
	 *
	 * This ensures that the tab is *always* actually registered with LifterLMS's endpoints so
	 * that a rewrite endpoint is added when rewrite rules are flushed.
	 *
	 * The tab is *removed* when the account page loads if the user is not a member of at least one group.
	 *
	 * @since 1.0.0-beta.4
	 * @since 1.0.0-beta.20 Early bail if the groups tab has not been added.
	 *                     Added `llms_groups_maybe_hide_dashboard_tab` filter.
	 *
	 * @param array[] $tabs Array of existing dashboard tabs.
	 * @return array[]
	 */
	public function maybe_hide_tab( $tabs ) {

		if ( ! isset( $tabs['view-groups'] ) ) {
			return $tabs;
		}

		$is_llms_account = function_exists( 'is_llms_account_page' ) && is_llms_account_page();

		/**
		 * Filters whether or not the checks to hide the groups tab are run.
		 *
		 * By default the tab will be hidden on the account page if the current student isn't a member of any groups.
		 *
		 * @since 1.0.0-beta.20
		 *
		 * @param bool $maybe_hide Whether or not checks are run that determine if the groups tab is hidden.
		 */
		if ( apply_filters( 'llms_groups_maybe_hide_dashboard_tab', $is_llms_account ) ) {

			$student = llms_get_student();
			// Don't need to worry about logged out students because can't access the tab anyway.
			if ( $student ) {

				// If the student isn't enrolled in any groups don't output the tab.
				$groups = $student->get_enrollments( 'llms_group', array( 'limit' => 1 ) );
				if ( ! $groups || empty( $groups['found'] ) ) {
					unset( $tabs['view-groups'] );
				}
			}
		}

		return $tabs;
	}

	/**
	 * Register the student dashboard tab.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.4 Always register the tab instead of conditionally registering it based on current student.
	 *                     Use `sanitize_title()` in favor of `strtolower()` when creating the endpoint slug.
	 *                     Added filter `llms_groups_register_my_groups_student_dashboard_endpoint` to allow customization of the endpoint settings.
	 * @since 1.0.0-beta.14 Allow pagination.
	 *
	 * @param array[] $tabs Existing dashboard tabs.
	 * @return array[]
	 */
	public function register_tab( $tabs ) {

		$name = llms_groups()->get_integration()->get_option( 'post_name_plural' );

		$settings = array(
			'content'  => 'llms_groups_template_student_dashboard_my_groups',
			// Translators: %s = User-customized group post name (plural).
			'endpoint' => sanitize_title( sprintf( _x( 'my-%s', '"My Groups" student dashboard tab endpoint slug', 'lifterlms-groups' ), $name ) ),
			'paginate' => true,
			'nav_item' => false,
			// Translators: %s = User-customized group post name (plural).
			'title'    => sprintf( _x( 'My %s', 'Groups student dashboard tab title', 'lifterlms-groups' ), $name ),
		);

		/**
		 * Modify the "My Groups" student dashboard endpoint settings.
		 *
		 * @since 1.0.0-beta.4
		 * @since 1.0.0-beta.10 Fixed typo in filter: `endoint` to `endpoint`.
		 *
		 * @param array $settings {
		 *     A hash of settings used to register the tab.
		 *
		 *     @type callable $content  A callback function used to render the contents of the tab.
		 *     @type string   $endpoint URL slug of the dashboard endpoint.
		 *     @type boolean  $paginate Whether or not the endpoint supports pagination.
		 *     @type boolean  $nav_item Whether or not a menu item is automatically generated.
		 *     @type string   $title    The title of the endpoint.
		 * }
		 */
		$slug = apply_filters( 'llms_groups_register_my_groups_student_dashboard_endpoint', $settings );

		$tabs = llms_assoc_array_insert(
			$tabs,
			'view-memberships',
			'view-groups',
			$settings
		);

		return $tabs;
	}
}

return new LLMS_Groups_Student_Dashboard();
