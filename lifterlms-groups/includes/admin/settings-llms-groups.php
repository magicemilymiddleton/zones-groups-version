<?php
/**
 * LifterLMS Groups Integrations Settings partial
 *
 * @package LifterLMS_Groups/Admin
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 */

defined( 'ABSPATH' ) || exit;

$settings = array();

// Group Profiles and Directory Section.
$settings[] = array(
	'id'    => 'groups-dir-opts',
	'desc'  => __( 'Each group has it\'s own profile where group administrators can manage their group and group members can find information about the group. The Group Directory allows members and visitors to browse groups on the site.', 'lifterlms-groups' ),
	'title' => __( 'Group Profiles and Directory', 'lifterlms-groups' ),
	'type'  => 'subtitle',
);

$settings[] = array(
	'class'   => 'llms-select2',
	'default' => 'private',
	'desc'    => __( 'Controls the default visibility for groups on the site which can be customized for each group from the group administration panel.', 'lifterlms-groups' ),
	'id'      => $this->get_option_name( 'visibility' ),
	'options' => array(
		'open'    => __( 'Open - Groups are listed publicly on the site for anyone to access', 'lifterlms-groups' ),
		'private' => __( 'Private - Groups are only accessible to users with active accounts on the site', 'lifterlms-groups' ),
		'closed'  => __( 'Closed - Group profiles are only accessible by members of the group', 'lifterlms-groups' ),
	),
	'title'   => __( 'Visibility', 'lifterlms-groups' ),
	'type'    => 'select',
);

$settings[] = array(
	'class'             => 'llms-select2-post',
	'custom_attributes' => array(
		'data-allow-clear' => true,
		'data-post-type'   => 'page',
		'data-placeholder' => __( 'Select a page', 'lifterlms-groups' ),
	),
	'desc'              => '<br>' . __( 'The page where users can visit to search and browse through all groups on the site.', 'lifterlms-groups' ),
	'id'                => $this->get_option_name( 'directory_page_id' ),
	'options'           => llms_make_select2_post_array( $this->get_option( 'directory_page_id' ) ),
	'title'             => __( 'Directory Page', 'lifterlms-groups' ),
	'type'              => 'select',
);


// Group Default Images.
$settings[] = array(
	'id'    => 'groups-img-opts',
	'desc'  => __( 'Customize the default images used for group profiles.', 'lifterlms-groups' ),
	'title' => __( 'Group Default Profile Images', 'lifterlms-groups' ),
	'type'  => 'subtitle',
);

$settings[] = array(
	// Translators: %1$d = Size of the logo.
	'desc'  => '<br>' . sprintf( _x( 'Recommended dimensions: %1$d x %1$dpx.', 'Group logo dimensions', 'lifterlms-groups' ), $theme['logo_dimensions'] ),
	'id'    => $this->get_option_name( 'logo_image' ),
	'title' => __( 'Logo / Avatar Image', 'lifterlms-groups' ),
	'type'  => 'image',
	'value' => $this->get_image( 'logo_image', false ),
);

$settings[] = array(
	// Translators: %1$d = Width of the banner; %2$d = Height of the banner.
	'desc'  => '<br>' . sprintf( _x( 'Recommended dimensions: %1$d x %2$dpx.', 'Group banner dimensions', 'lifterlms-groups' ), $theme['banner_dimensions'][0], $theme['banner_dimensions'][1] ),
	'id'    => $this->get_option_name( 'banner_image' ),
	'title' => __( 'Banner Image', 'lifterlms-groups' ),
	'type'  => 'image',
	'value' => $this->get_image( 'banner_image', false ),
);

// Group Language and Terminology.
$settings[] = array(
	'id'    => 'groups-lang-opts',
	'desc'  => __( 'Customize the language used for groups throughout the site. For example, if Groups can be renamed to "Teams" with these settings.', 'lifterlms-groups' ),
	'title' => __( 'Group Language and Terminology', 'lifterlms-groups' ),
	'type'  => 'subtitle',
);

$settings[] = array(
	'default'  => __( 'Group', 'lifterlms-groups' ),
	'id'       => $this->get_option_name( 'post_name_singular' ),
	'required' => true,
	'title'    => __( 'Singular Name', 'lifterlms-groups' ),
	'type'     => 'text',
);

$settings[] = array(
	'default'  => __( 'Groups', 'lifterlms-groups' ),
	'id'       => $this->get_option_name( 'post_name_plural' ),
	'required' => true,
	'title'    => __( 'Plural Name', 'lifterlms-groups' ),
	'type'     => 'text',
);

$settings[] = array(
	// Translators: %s = preview url string.
	'desc'     => '<br>' . sprintf( __( 'The slug is used in the URL for a group profile. Changing the slug will cause bookmarked group profile urls to stop working! Preview URL: %s', 'lifterlms-groups' ), '<code>' . get_site_url() . '/<span id="llms-groups-slug-preview"></span>/example-name</code>' ),
	'default'  => _x( 'group', 'group profile url slug', 'lifterlms-groups' ),
	'id'       => $this->get_option_name( 'post_slug' ),
	'required' => true,
	'sanitize' => 'slug',
	'title'    => __( 'Slug', 'lifterlms-groups' ),
	'type'     => 'text',
);

$settings[] = array(
	'default'  => __( 'Leader', 'lifterlms-groups' ),
	'id'       => $this->get_option_name( 'leader_name_singular' ),
	'required' => true,
	'title'    => __( 'Leader Singular Name', 'lifterlms-groups' ),
	'type'     => 'text',
);

$settings[] = array(
	'default'  => __( 'Leaders', 'lifterlms-groups' ),
	'id'       => $this->get_option_name( 'leader_name_plural' ),
	'required' => true,
	'title'    => __( 'Leader Plural Name', 'lifterlms-groups' ),
	'type'     => 'text',
);

return $settings;
