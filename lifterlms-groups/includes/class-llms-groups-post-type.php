<?php
/**
 * LifterLMS Groups Post Type
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.20
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Post_Type class.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.2 Disable post type archive in favor of custom directory output methods.
 * @since 1.0.0-beta.10 Conditionally disable groups in the WP sitemap.
 */
class LLMS_Groups_Post_Type {

	/**
	 * Post type name/slug
	 *
	 * @var  string
	 */
	const POST_TYPE = 'llms_group';

	/**
	 * Static constructor
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public static function init() {

		add_action( 'init', array( __CLASS__, 'register' ), 5 );

		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'maybe_exclude_from_sitemap' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'maybe_exclude_groups_from_sitemap' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'maybe_avoid_setting_group_to_draft' ), 10, 2 );
	}

	public static function maybe_avoid_setting_group_to_draft( $data, $postarr ) {
		// Check if the post type is 'llms_group' and if the post status is 'draft'
		if ( 'llms_group' === $data['post_type'] &&
			'trash' === get_post_status( $postarr['ID'] ) &&
			'draft' === $data['post_status'] ) {
			$data['post_status'] = 'publish';
		}
		return $data;
	}

	/**
	 * Retrieve a translated string for a group label by label key.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param  string $label The requested label key, eg "name" or "add_new_item".
	 * @return string        The translated string. If the string doesn't exist the submitted `$label` argument is returned.
	 */
	public static function get_label( $label ) {

		$labels = self::get_labels();
		return isset( $labels[ $label ] ) ? $labels[ $label ] : $label;
	}

	/**
	 * Retrieve label information for the Group post type.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return array
	 */
	protected static function get_labels() {

		$int      = llms_groups()->get_integration();
		$singular = $int->get_option( 'post_name_singular', 'Group' );
		$plural   = $int->get_option( 'post_name_plural', 'Groups' );

		$labels = array(
			'name'                     => $plural,
			'singular_name'            => $singular,
			'add_new'                  => _x( 'Add New', 'group', 'lifterlms-groups' ),
			// Translators: %s = Singular user-defined group name.
			'add_new_item'             => sprintf( __( 'Add New %s', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'edit_item'                => sprintf( __( 'Edit %s', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'new_item'                 => sprintf( __( 'New %s', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'view_item'                => sprintf( _x( 'View %s', 'group view single', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Plural user-defined group name.
			'view_items'               => sprintf( _x( 'View %s', 'group view archives', 'lifterlms-groups' ), $plural ),
			// Translators: %s = Plural user-defined group name.
			'search_items'             => sprintf( __( 'Search %s', 'lifterlms-groups' ), $plural ),
			// Translators: %s = Plural user-defined group name.
			'not_found'                => sprintf( __( 'No %s found', 'lifterlms-groups' ), $plural ),
			// Translators: %s = Plural user-defined group name.
			'not_found_in_trash'       => sprintf( __( 'No %s found in Trash', 'lifterlms-groups' ), $plural ),
			// Translators: %s = Plural user-defined group name.
			'all_items'                => sprintf( __( 'All %s', 'lifterlms-groups' ), $plural ),
			// Translators: %s = Singular user-defined group name.
			'archives'                 => sprintf( __( '%s Directory', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'attributes'               => sprintf( __( '%s Attributes', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'insert_into_item'         => sprintf( __( 'Insert into %s', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'uploaded_to_this_item'    => sprintf( __( 'Uploaded to this %s', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'featured_image'           => sprintf( __( '%s Avatar', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'set_featured_image'       => sprintf( __( 'Set %s avatar', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'remove_featured_image'    => sprintf( __( 'Remove %s avatar', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'use_featured_image'       => sprintf( __( 'Use as %s avatar', 'lifterlms-groups' ), $singular ),
			'menu_name'                => $plural,
			// Translators: %s = Plural user-defined group name.
			'filter_items_list'        => sprintf( __( 'Filter %s list', 'lifterlms-groups' ), $plural ),
			// Translators: %s = Plural user-defined group name.
			'items_list_navigation'    => sprintf( __( '%s list navigation', 'lifterlms-groups' ), $plural ),
			// Translators: %s = Plural user-defined group name.
			'items_list'               => sprintf( __( '%s list', 'lifterlms-groups' ), $plural ),
			// Translators: %s = Singular user-defined group name.
			'item_published'           => sprintf( __( '%s published', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'item_published_privately' => sprintf( __( '%s published privately', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'item_reverted_to_draft'   => sprintf( __( '%s reverted to draft', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'item_scheduled'           => sprintf( __( '%s scheduled', 'lifterlms-groups' ), $singular ),
			// Translators: %s = Singular user-defined group name.
			'item_updated'             => sprintf( __( '%s updated', 'lifterlms-groups' ), $singular ),
		);

		/**
		 * Filter the labels used for the groups post type
		 *
		 * @since 1.0.0-beta.1
		 *
		 * @param  array $labels Array of label strings.
		 */
		return apply_filters( 'llms_groups_post_type_labels', $labels );
	}

	/**
	 * Conditionally remove the groups post type from being listed on the WP sitemap.
	 *
	 * If group directory visibility is "closed", the group post type is removed completely from
	 * the sitemap.
	 *
	 * When visibility is "open" or "private" individual groups are conditionally removed
	 * from the sitemap based on their group visibility setting.
	 *
	 * @since 1.0.0-beta.10
	 *
	 * @param WP_Post_Type[] $post_types Array of post type objects.
	 * @return WP_Post_Type[]
	 */
	public static function maybe_exclude_from_sitemap( $post_types ) {

		if ( 'closed' === llms_groups()->get_integration()->get_option( 'visibility' ) ) {
			unset( $post_types[ self::POST_TYPE ] );
		}

		return $post_types;
	}

	/**
	 * Conditionally exclude individual groups from the WP sitemap
	 *
	 * Groups with visibility set to "closed" and "private" are excluded from the groups
	 * sitemap.
	 *
	 * @since 1.0.0-beta.10
	 *
	 * @param array  $args      WP_Query args for the sitemap query.
	 * @param string $post_type Post type name for the query.
	 * @return array
	 */
	public static function maybe_exclude_groups_from_sitemap( $args, $post_type ) {

		if ( self::POST_TYPE === $post_type ) {

			$excluded_groups = new WP_Query(
				array(
					'fields'         => 'ids',
					'post_status'    => 'publish',
					'post_type'      => self::POST_TYPE,
					'posts_per_page' => -1,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_llms_visibility',
							'value'   => array( 'closed', 'private' ),
							'compare' => 'IN',
						),
					),
				)
			);

			$existing             = isset( $args['post__not_in'] ) ? $args['post__not_in'] : array();
			$args['post__not_in'] = array_merge( $existing, $excluded_groups->posts );

		}

		return $args;
	}

	/**
	 * Register the Group post type.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.2 Switch `has_archive` property to always be `false`.
	 * @since 1.0.0-beta.20 Permastruct not prepended with the front base anymore.
	 *
	 * @link https://developer.wordpress.org/reference/functions/register_post_type/
	 *
	 * @return WP_Post_Type|WP_Error
	 */
	public static function register() {

		$int = llms_groups()->get_integration();

		$args = array(
			'labels'              => self::get_labels(),
			'public'              => true,
			'hierarchical'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'menu_position'       => 54,
			'menu_icon'           => 'dashicons-networking', // @todo need an icon.
			'capabilities'        => LLMS_Post_Types::get_post_type_caps( 'group' ),
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'thumbnail' ),
			'has_archive'         => false,
			'rewrite'             => array(
				'slug'       => $int->get_option( 'post_slug' ),
				'with_front' => false,
			),
		);

		/**
		 * Filter the groups post type registration arguments
		 *
		 * @since 1.0.0-beta.1
		 *
		 * @param array $args Post type arguments.
		 */
		$args = apply_filters( 'llms_groups_post_type_args', $args );

		return register_post_type( self::POST_TYPE, $args );
	}
}

return LLMS_Groups_Post_Type::init();
