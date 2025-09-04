<?php
/**
 * Functions, hooks, and filters for group templates
 *
 * @package LifterLM_Groups/Functions
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.20
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modify the lifterlms_loop() columns when used on the profile/sidebar-content.php template.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.5 'llms_groups_profile_member_list_per_page' filter added. Also set the default members do display per page to 10.
 *
 * @return int
 */
function llms_groups_content_loop_cols() {
	return 1;
}

/**
 * Retrieve profile layout class
 *
 * @since 1.0.0-beta.1
 * @return string
 */
function llms_groups_get_profile_layout_class() {

	/**
	 * Customize the layout class for group profiles
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param string $layout The layout class. Default supported classes are  Supported classes are: 'sidebar--left' and 'sidebar--right'.
	 */
	return apply_filters( 'llms_groups_profile_layout_class', 'sidebar--right' );
}

/**
 * Retrieve a groups template or template part.
 *
 * Wrapper for the main LLMS template getter
 *
 * @since 1.0.0-beta.1
 *
 * @param string $template Name of the template (with .php).
 * @param array  $args     Args to extract into the template.
 * @return void
 */
function llms_groups_get_template( $template, $args = array() ) {
	llms_get_template( $template, $args, '', LLMS_GROUPS_PLUGIN_DIR . 'templates/' );
}

/**
 * Retrieve HTML for the role descriptions select element displayed to group managers
 *
 * @since 1.0.0-beta.1
 *
 * @return string
 */
function llms_groups_get_role_descriptions_html() {

	$singular = llms_groups_get_roles();
	$plural   = llms_groups_get_roles( 'plural' );
	$group    = strtolower( llms_groups()->get_integration()->get_option( 'post_name_singular' ) );
	ob_start();
	?>
	<span data-group-role="member">
	<?php
		// Translators: %1$s = member name (singular); %2$s = group name (singular); %3$s = member name (plural).
		printf( __( 'A %1$s can view the %2$s profile, content, and %3$s.', 'lifterlms-groups' ), $singular['member'], $group, strtolower( $plural['member'] ) );
	?>
	</span>
	<span data-group-role="leader">
	<?php
		// Translators: %1$s = leader name (singular); %2$s group name (singular); %3$s member name (plural).
		printf( __( 'A %1$s can edit %2$s settings, invite new %3$s, and view %2$s reports.', 'lifterlms-groups' ), $singular['leader'], $group, strtolower( $plural['member'] ) );
	?>
		</span>
	<span data-group-role="admin">
	<?php
		// Translators: %1$s = leader name (singular); %2$s group name (singular); %3$s leader name (plural); %4$s = admin name (pluaral).
		printf( __( 'A %1$s who can also manage %2$s billing, %3$s, and %4$s', 'lifterlms-groups' ), $singular['leader'], $group, strtolower( $plural['leader'] ), strtolower( $plural['admin'] ) );
	?>
		</span>
	<?php
	return ob_get_clean();
}

/**
 * Include the groups directory/loop.php template part
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.4 Add optional $query parameter to allow overriding of the default list of groups that's displayed via the global `$wp_query`.
 *
 * @param WP_Query $query Optional. A query object used in favor of the default `$wp_query` global.
 * @return void
 */
function llms_groups_template_directory_loop( $query = null ) {

	if ( ! $query ) {
		global $wp_query;
		$query = $wp_query;
	}

	llms_groups_get_template( 'directory/loop.php', compact( 'query' ) );
}

/**
 * Include the single group card template part
 *
 * @since 1.0.0-beta.4
 *
 * @param LLMS_Group|WP_Post|int $group Optional. Group object or ID which will be passed to `llms_get_post()`. Uses `global $post` if not supplied.
 * @return void
 */
function llms_groups_template_group_card( $group = null ) {

	$group = llms_get_post( $group );
	llms_groups_get_template( 'group-card.php', compact( 'group' ) );
}

/**
 * Inject the groups main template when a group profile is being accessed.
 *
 * The groups default template is only injected when the loaded template is not
 * `single-llms_group.php`. This ensures that if a theme adds it's own group template
 * it will not be replaced by the default template included in the groups plugin.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.4 Don't inject our template when there's a 404.
 * @since 1.0.0-beta.18 Bail if filter `llms_force_php_template_loading'` is false. Used for FSE compatibility.
 *
 * @param string $template Path for the template to be included.
 * @return string
 */
function llms_groups_template_include( $template ) {
	/** This filter is documented in lifterlms/includes/class.llms.template.loader */
	if ( ! apply_filters( 'llms_force_php_template_loading', true ) ) {
		return $template;
	}

	global $wp_query;

	if ( is_llms_group() && ! $wp_query->is_404() && 'single-llms_group.php' !== basename( $template ) ) {
		$template = LLMS_GROUPS_PLUGIN_DIR . 'templates/single-llms_group.php';
	}

	return $template;
}
add_filter( 'template_include', 'llms_groups_template_include', 20 );

/**
 * Include the groups profile/about.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_about( $group = null ) {
	$group = get_llms_group( $group );
	llms_groups_get_template( 'profile/about.php', compact( 'group' ) );
}

/**
 * Include the groups profile/header.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_header( $group = null ) {
	$group = get_llms_group( $group );
	$theme = llms_groups()->get_integration()->get_theme_settings();
	llms_groups_get_template( 'profile/header.php', compact( 'group', 'theme' ) );
}

/**
 * Include the groups profile/members.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param LLMS_Student $member  Student object representing the member.
 * @param string       $context Card location context. Either "main" or "sidebar".
 * @return void
 */
function llms_groups_template_profile_member( $member, $context = 'main' ) {

	$role = LLMS_Groups_Enrollment::get_role( $member->get( 'id' ), get_the_ID() );

	llms_groups_get_template( 'profile/member.php', compact( 'member', 'context', 'role' ) );
}

/**
 * Include the groups profile/members.php template part.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.19 Fixed access of protected LLMS_Abstract_Query properties.
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_members( $group = null ) {

	$action  = current_action();
	$context = 'llms_group_profile_sidebar' === $action ? 'sidebar' : 'main';

	global $wp_query;
	$temp = $wp_query;

	$group = get_llms_group( $group );

	$page = $wp_query->get( 'paged' ) ? $wp_query->get( 'paged' ) : 1;

	$args = array(
		'per_page' => 'sidebar' === $context ? 6 : 20,
		'page'     => 'sidebar' === $context ? 1 : $page,
	);

	$members = llms_group_get_members( $group->get( 'id' ), $args );

	$leaders = 'sidebar' === $context ? null : llms_group_get_members(
		$group->get( 'id' ),
		array(
			'per_page'   => 10,
			'group_role' => array( 'leader', 'admin' ),
		)
	);

	$wp_query->max_num_pages = $members->get_query()->get_max_pages();

	llms_groups_get_template( 'profile/members.php', compact( 'group', 'context', 'members', 'leaders' ) );

	$wp_query = $temp; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Sometimes you have to break the rules.
}

/**
 * Include the groups profile/modal-invitations.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_modal_invitations( $group = null ) {
	$group = get_llms_group( $group );
	if ( $group ) {
		llms_groups_get_template( 'profile/modal-invitations.php', compact( 'group' ) );
	}
}

/**
 * Include the groups profile/modal-seats.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_modal_seats( $group = null ) {
	$group = get_llms_group( $group );
	if ( $group ) {
		llms_groups_get_template( 'profile/modal-seats.php', compact( 'group' ) );
	}
}

/**
 * Include the groups profile/modal-upload.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_modal_upload( $group = null ) {
	$group = get_llms_group( $group );
	if ( $group ) {
		$theme = llms_groups()->get_integration()->get_theme_settings();
		llms_groups_get_template( 'profile/modal-upload.php', compact( 'group', 'theme' ) );
	}
}

/**
 * Include the groups profile/navigation.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_navigation( $group = null ) {
	$group      = get_llms_group( $group );
	$navigation = LLMS_Groups_Profile::get_navigation();
	$current    = 'about';
	llms_groups_get_template( 'profile/navigation.php', compact( 'group', 'navigation', 'current' ) );
}

/**
 * Master template function for the group profile "reports" tab.
 *
 * @since 1.0.0-beta.4
 * @since 1.0.0-beta.5 'llms_groups_profile_member_list_per_page' filter added. Also set the default members per page to 10.
 * @since 1.0.0-beta.7 Filter non existing courses before passing them to the single-member-courses-list template.
 * @since 1.0.0-beta.12 Check current user permissions and return early if user cannot access group reporting.
 * @since 1.0.0-beta.15 Cast achievement_template ID to string when comparing to the list of achievement IDs related to the course/membership (list of strings).
 * @since 1.0.0-beta.19 Use updated method signature for `LLMS_Student::get_achievements()`.
 *              Replaced use of the deprecated `achievement_template` meta key with the post's `parent` property.
 * @since 1.0.0-beta.20 Fixed link when using a permastruct without a trailing slash.
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_reports( $group = null ) {

	$group = get_llms_group( $group );

	if ( ! current_user_can( 'view_group_reporting', $group->get( 'id' ) ) ) {
		return;
	}

	$post_type = get_post_type( $group->get( 'post_id' ) );
	$member    = llms_filter_input( INPUT_GET, 'mid', FILTER_SANITIZE_NUMBER_INT );
	$base_url  = trailingslashit( get_permalink( $group->get( 'id' ) ) ) . LLMS_Groups_Profile::get_tab_slug( 'reports' );

	/**
	 * Output content before the reports main content
	 *
	 * Hooked: llms_groups_template_profile_reports_wrapper_open - 5
	 *
	 * @since 1.0.0-beta.4
	 */
	do_action( 'llms_groups_profile_reports_before' );

	// Viewing a single member's reports.
	if ( is_numeric( $member ) ) {

		$student = llms_get_student( $member );
		if ( ! $student || ! llms_group_has_member( $group->get( 'id' ), $student->get( 'id' ) ) ) {
			return;
		}
		$course_id = ( 'course' === $post_type ) ? $group->get( 'post_id' ) : llms_filter_input( INPUT_GET, 'cid', FILTER_SANITIZE_NUMBER_INT );

		if ( $course_id ) {

			$course = llms_get_post( $course_id );

			$achievement_template_ids = LLMS()->achievements()->get_achievements_by_post( $course->get( 'id' ) );
			$latest_achievement       = false;
			$student_achievements     = $student->get_achievements( array( 'sort' => array( 'date', 'DESC' ) ) )->get_awards();
			foreach ( $student_achievements as $student_achievement ) {
				if ( in_array( (string) $student_achievement->get( 'parent' ), $achievement_template_ids, true ) ) {
					$latest_achievement = $student_achievement;
					break;
				}
			}

			$last_activity = $student->get_events(
				array(
					'per_page' => 1,
					'post_id'  => $course->get( 'id' ),
				)
			);
			llms_groups_get_template( 'profile/reports/single-member-course.php', compact( 'student', 'course', 'latest_achievement', 'last_activity' ) );

		} elseif ( 'llms_membership' === $post_type ) {

			$membership = llms_get_post( $group->get( 'post_id' ) );
			$courses    = array_filter( array_map( 'llms_get_post', $membership->get_associated_posts( 'course' ) ) );
			$base_url   = add_query_arg( 'mid', $member, $base_url );

			llms_groups_get_template( 'profile/reports/single-member-courses-list.php', compact( 'student', 'membership', 'courses', 'base_url' ) );

		}
	} else {

		global $wp_query;
		$page = $wp_query->get( 'paged' );

		/**
		 * Filters the number of members per page to display in the member list
		 *
		 * @since 1.0.0-beta.5
		 *
		 * @param $members The number of members to display for each page. Default 10.
		 */
		$per_page = apply_filters( 'llms_groups_profile_member_list_per_page', 10 );
		$members  = llms_group_get_members(
			$group->get( 'id' ),
			array(
				'per_page' => $per_page,
				'page'     => $page ? $page : 1,
			)
		);
		$query    = $members->get_query();

		llms_groups_get_template( 'profile/reports/members-list.php', compact( 'group', 'post_type', 'members', 'query', 'base_url' ) );

	}

	/**
	 * Output content before the reports main content
	 *
	 * Hooked: llms_groups_template_profile_reports_wrapper_close - 15
	 *
	 * @since 1.0.0-beta.4
	 */
	do_action( 'llms_groups_profile_reports_after' );
}

/**
 * Output the reports profile tab closing wrapper HTML.
 *
 * @since 1.0.0-beta.4
 *
 * @return void
 */
function llms_groups_template_profile_reports_wrapper_close() {
	echo '</div><!-- .llms-group-card.card--group-profile-reports -->';
}

/**
 * Output the reports profile tab opening wrapper HTML.
 *
 * @since 1.0.0-beta.4
 *
 * @return void
 */
function llms_groups_template_profile_reports_wrapper_open() {
	echo '<div class="llms-group-card card--group-profile-reports">';
}

/**
 * Include the groups profile/sidebar-content.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_sidebar_content( $group = null ) {

	$group      = get_llms_group( $group );
	$card_title = __( 'Content', 'lifterlms-groups' );
	$post_id    = $group->get( 'post_id' );
	$loop_query = null;

	if ( $post_id ) {

		$loop_query = new WP_Query(
			array(
				'post__in'  => array( $post_id ),
				'post_type' => get_post_type( $post_id ),
			)
		);

		// Prevent pagination hack.
		$loop_query->max_num_pages = 1;

		$card_title = get_post_type_object( get_post_type( $post_id ) )->labels->singular_name;

	}

	add_filter( 'lifterlms_loop_columns', 'llms_groups_content_loop_cols', 999 );

	llms_groups_get_template( 'profile/sidebar-content.php', compact( 'group', 'loop_query', 'card_title' ) );

	remove_filter( 'lifterlms_loop_columns', 'llms_groups_content_loop_cols', 999 );
}


/**
 * Include the groups profile/sidebar-invitations.php template part
 *
 * @since 1.0.0-beta.5
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_sidebar_invitations( $group = null ) {

	$group = get_llms_group( $group );
	if ( ! current_user_can( 'manage_group_members', $group->get( 'id' ) ) ) {
		return;
	}

	$query = llms_groups()->invitations()->query(
		array(
			'group'    => array( $group->get( 'id' ) ),
			'open'     => 'exclude',
			'per_page' => 100,
		)
	);

	llms_groups_get_template( 'profile/sidebar-invitations.php', compact( 'group', 'query' ) );
}

/**
 * Include the groups profile/sidebar-seats.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_sidebar_seats( $group = null ) {
	$group = get_llms_group( $group );
	llms_groups_get_template( 'profile/sidebar-seats.php', compact( 'group' ) );
}

/**
 * Include the groups profile/about.php template part
 *
 * @since 1.0.0-beta.1
 *
 * @param  WP_Post|int|null $group WP_Post|int|null (Optional) Post ID or post object for the group. Defaults to global $post.
 * @return void
 */
function llms_groups_template_profile_settings( $group = null ) {
	$group    = get_llms_group( $group );
	$settings = LLMS_Groups_Profile::get_settings( $group );
	llms_groups_get_template( 'profile/settings.php', compact( 'group', 'settings' ) );
}

/**
 * Include the dashboard/my-groups.php template part
 *
 * @since 1.0.0-beta.4
 * @since 1.0.0-beta.14 Handle pagination.
 *
 * @return void
 */
function llms_groups_template_student_dashboard_my_groups() {

	$student = llms_get_student();
	$groups  = $student ? $student->get_enrollments( 'llms_group', array( 'limit' => 500 ) ) : array();

	/**
	 * Filter the number of groups per page to be displayed in the dashboard.
	 *
	 * @since 1.0.0-beta.14
	 *
	 * @param int $per_page The number of groups per page to be displayed. Defaults to 10.
	 */
	$per_page = apply_filters( 'llms_groups_dashboard_groups_per_page', 10 );

	$args  = array(
		'post_type'      => LLMS_Groups_Post_Type::POST_TYPE,
		'post_status'    => 'publish',
		// If there aren't any groups for the student, we want to show nothing vs. showing ALL groups.
		'post__in'       => ( $groups && isset( $groups['results'] ) && count( $groups['results'] ) ) ? $groups['results'] : array( -1 ),
		'posts_per_page' => $per_page,
		'paged'          => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
	);
	$query = new WP_Query( $args );

	// Override the `$wp_query`, needed by the loop/pagination template.
	global $wp_query;
	$temp     = $wp_query;
	$wp_query = $query; //phpcs:ignore -- we know what we're doing and we're going to reset it a couple of lines below.

	/**
	 * `llms_modify_dashboard_pagination_links()` callback is defined in lifterlms/includes/functions/llms.functions.templates.dashboard.php
	 */
	add_filter( 'paginate_links', 'llms_modify_dashboard_pagination_links' );

	llms_groups_get_template( 'dashboard/my-groups.php', compact( 'query' ) );

	remove_filter( 'paginate_links', 'llms_modify_dashboard_pagination_links' );

	if ( $query ) {
		$wp_query = $temp; //phpcs:ignore --- we know what we did.
		wp_reset_postdata();
	}
}
