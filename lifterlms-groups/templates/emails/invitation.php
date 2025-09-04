<?php
/**
 * Group Invitation Email Body Content
 *
 * @package LifterLMS_Groups/Templates/Emails
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 *
 * Merge Codes:
 * {site_title}   The name of the Site as stored in the WordPress General Settings
 * {divider}      The HTML for a horizontal divider
 * {button_style} An inline style attribute string which can be used on an <a> to create a button.
 * {invite_url}   The invitation acceptance URL.
 * {group_name}   Name of the group.
 */

defined( 'ABSPATH' ) || exit;
?>

<p>
<?php
	// Translators: %1$s = Group name; %2$s = Site title.
	printf( __( 'You have been invited to join %1$s at %2$s.', 'lifterlms-groups' ), '<strong>{group_name}</strong>', '<strong>{site_title}</strong>' );
?>
</p>

<p><?php _e( 'To accept the invitation and get started, click on the button below:', 'lifterlms-groups' ); ?></p>

<p><a href="{invite_url}" style="{button_style}"><?php _e( 'Accept Invitation', 'lifterlms-groups' ); ?></a></p>

{divider}

<p><small>
<?php
	// Translators: %s = Invitation url link HTML.
	printf( __( 'Trouble clicking? Copy and paste this URL into your browser: %s', 'lifterlms-groups' ), '<br><a href="{invite_url}">{invite_url}</a>' );
?>
</p></small>
