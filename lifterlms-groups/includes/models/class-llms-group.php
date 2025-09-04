<?php
/**
 * LLMS_Group class file
 *
 * @package LifterLMS/Models
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.21
 */

defined( 'ABSPATH' ) || exit;

/**
 * LifterLMS Group Post Type Model.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.4 Add method `has_open_seats()`.
 *
 * @property int    $banner     WP_Attachement ID of the group's banner image.
 * @property int    $members    The cached number of members for the group. This number should never be updated directly and may
 *                              be inaccurate depending on the cache status. Use {@see LLMS_Group::get_members_count()}.
 * @property int    $post_id    WP_Post ID of the group post.
 * @property int    $seats      The total number of seats allotted to the group.
 * @property string $visibility The group's visibility setting.
 */
class LLMS_Group extends LLMS_Post_Model {

	/**
	 * Post type name.
	 *
	 * @var string
	 */
	protected $db_post_type = 'llms_group';

	/**
	 * Model name.
	 *
	 * @var string
	 */
	protected $model_post_type = 'group';

	/**
	 * Model Meta Properties
	 *
	 * @var array
	 */
	protected $properties = array(
		'banner'     => 'absint',
		'members'    => 'absint',
		'post_id'    => 'absint',
		'seats'      => 'absint',
		'visibility' => 'string',
		'order_id'   => 'absint',
	);

	/**
	 * Array of default meta values for class props.
	 *
	 * @var array
	 */
	protected $property_defaults = array(
		'seats'   => 1,
		'members' => 0,
	);

	/**
	 * Clears cached member count data.
	 *
	 * @since 1.0.0-beta.21
	 *
	 * @return boolean Returns `true` on success and `false` on failure.
	 */
	public function clear_members_cache() {
		return delete_post_meta( $this->get( 'id' ), $this->meta_prefix . 'members' );
	}

	/**
	 * Retrieve the group banner image source url.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return string
	 */
	public function get_banner() {
		return $this->get_image( 'full', 'banner' );
	}

	/**
	 * Retrieve an image source/url for a given image type.
	 *
	 * Automatically falls back to the image as set in the global integration settings.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param string $size Registered image size or numeric array with width and height as ints.
	 * @param string $key  Image key, 'thumbnail' (logo) or 'banner'.
	 * @return string
	 */
	public function get_image( $size = 'full', $key = '' ) {

		$url = parent::get_image( $size, $key );

		// If no image found, fallback to the global defaults.
		if ( ! $url ) {
			$int = llms_groups()->get_integration();
			$key = 'thumbnail' === $key ? 'logo' : $key;
			$url = $int->get_image( $key . '_image', true );
		}

		return $url;
	}

	/**
	 * Retrieve the group logo image source url.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return string
	 */
	public function get_logo() {
		return $this->get_image( 'full', 'thumbnail' );
	}

	/**
	 * Retrieve the open invitation record for the group
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return LLMS_Group_Invitation|false
	 */
	public function get_open_invite() {

		return llms_groups()->invitations()->get_open_link( $this->get( 'id' ), true );
	}

	/**
	 * Retrieve the link for the group's open invitation record.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return string
	 */
	public function get_open_invite_link() {

		$obj  = $this->get_open_invite();
		$link = '';

		if ( $obj ) {
			$link = $obj->get_accept_link();
		}

		return $link;
	}

	/**
	 * Retrieves the number of active members in the group.
	 *
	 * @since 1.0.0-beta.21
	 *
	 * @param bool $use_cache Whether or not to use cached data.
	 * @return int
	 */
	public function get_members_count( $use_cache = true ) {

		// Use the cache if requested and available.
		if ( $use_cache && isset( $this->members ) ) {
			return $this->get( 'members' );
		}

		$members = llms_group_get_members(
			$this->get( 'id' ),
			array(
				'per_page' => 1,
				'sort'     => array(
					'id' => 'ASC',
				),
			)
		);

		$count = $members->get_query()->get_found_results();

		$this->set( 'members', $count );
		return $count;
	}

	/**
	 * Determine if the group has an open invite link.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return boolean
	 */
	public function has_open_invite() {
		return $this->get_open_invite() ? true : false;
	}

	/**
	 * Determine if the group has open seats.
	 *
	 * @since 1.0.0-beta.4
	 * @since 1.0.0-beta.21 Added `$use_cache` parameter.
	 *
	 * @param bool $use_cache Whether or not to use cached data.
	 * @return boolean
	 */
	public function has_open_seats( $use_cache = true ) {

		$seats = $this->get_seats( $use_cache );
		return $seats['open'] > 0;
	}

	/**
	 * Retrieve the total number of pending invitations for the group.
	 *
	 * @since 1.0.0-beta.2
	 *
	 * @return int
	 */
	public function get_pending_invitations_count() {

		$group_id = $this->get( 'id' );

		$found = null;
		$count = wp_cache_get( $group_id, 'llms_group_invitations_count', false, $found );
		if ( ! $count && ! $found ) {

			global $wpdb;
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$wpdb->prefix}lifterlms_group_invitations WHERE group_id = %d AND email != ''", $group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_set( $group_id, $count, 'llms_group_invitations_count' );

		}

		return $count ? $count : 0;
	}

	/**
	 * Retrieve information about the groups number of available seats.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.2 Fixed the `used` property return to display the real value instead of always returning `1`.
	 * @since 1.0.0-beta.13 Improve performance by reducing the sorting constraints on the members query.
	 * @since 1.0.0-beta.19 Fixed access of protected LLMS_Abstract_Query properties.
	 * @since 1.0.0-beta.21 Moved active member count data to {@see LLMS_Group::get_members_count()} and added the `$use_cache` parameter.
	 *
	 * @param bool $use_cache Whether or not to retrieve cached data.
	 * @return int[] {
	 *     Array of seat information.
	 *
	 *     @type int $total Total number of seats available to the group.
	 *     @type int $used  Total number of used seats.
	 *     @type int $open  Number of seats remaining.
	 * }
	 */
	public function get_seats( $use_cache = true ) {

		$total = $this->get( 'seats' );
		$used  = $this->get_members_count( $use_cache ) + $this->get_pending_invitations_count();
		$open  = $total - $used;

		return compact( 'total', 'open', 'used' );
	}
}
