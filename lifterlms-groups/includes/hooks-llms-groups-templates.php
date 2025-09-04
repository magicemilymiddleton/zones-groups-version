<?php
/**
 * Template Hooks
 *
 * @package LifterLMS/Hooks/Templates
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.4
 */

defined( 'ABSPATH' ) || exit;

add_action( 'llms_group_profile_before_content', 'llms_groups_template_profile_header', 10 );
add_action( 'llms_group_profile_before_content', 'llms_groups_template_profile_navigation', 20 );

add_action( 'llms_group_profile_main_about', 'llms_groups_template_profile_about', 10 );
add_action( 'llms_group_profile_main_members', 'llms_groups_template_profile_members', 10 );
add_action( 'llms_group_profile_main_reports', 'llms_groups_template_profile_reports', 10 );
add_action( 'llms_group_profile_main_settings', 'llms_groups_template_profile_settings', 10 );

add_action( 'llms_group_profile_sidebar', 'llms_groups_template_profile_members', 10 );
add_action( 'llms_group_profile_sidebar', 'llms_groups_template_profile_sidebar_invitations', 10 );
add_action( 'llms_group_profile_sidebar', 'llms_groups_template_profile_sidebar_content', 15 );
add_action( 'llms_group_profile_sidebar', 'llms_groups_template_profile_sidebar_seats', 20 );

add_action( 'llms_group_member_block', 'llms_groups_template_profile_member', 10, 2 );

add_action( 'wp_footer', 'llms_groups_template_profile_modal_upload', 10 );
add_action( 'wp_footer', 'llms_groups_template_profile_modal_seats', 10 );
add_action( 'wp_footer', 'llms_groups_template_profile_modal_invitations', 10 );

add_action( 'llms_groups_directory_loop', 'llms_groups_template_directory_loop', 10 );

add_action( 'llms_groups_group_card', 'llms_groups_template_group_card', 10 );

add_action( 'llms_groups_profile_reports_before', 'llms_groups_template_profile_reports_wrapper_open', 5 );
add_action( 'llms_groups_profile_reports_after', 'llms_groups_template_profile_reports_wrapper_close', 15 );

add_action( 'wp', 'llms_groups_templates_conditionals' );
/**
 * Conditionally remove actions in various circumstances.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.5 Add invitations sidebar.
 *
 * @return void
 */
function llms_groups_templates_conditionals() {

	$tab = LLMS_Groups_Profile::get_current_tab();

	// Only display invitations on the members page.
	if ( 'members' !== $tab ) {
		remove_action( 'llms_group_profile_sidebar', 'llms_groups_template_profile_sidebar_invitations', 10 );
	}

	if ( 'members' === $tab ) {
		// Don't show the members sidebar on the members tab.
		remove_action( 'llms_group_profile_sidebar', 'llms_groups_template_profile_members', 10 );
	} elseif ( 'reports' === $tab ) {

		// Reports has no sidebar.
		remove_action( 'llms_group_profile_sidebar', 'llms_groups_template_profile_members', 10 );
		remove_action( 'llms_group_profile_sidebar', 'llms_groups_template_profile_sidebar_content', 15 );
		remove_action( 'llms_group_profile_sidebar', 'llms_groups_template_profile_sidebar_seats', 20 );

	}
}
