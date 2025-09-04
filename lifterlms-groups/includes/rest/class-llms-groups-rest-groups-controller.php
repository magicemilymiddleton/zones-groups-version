<?php
/**
 * REST Groups Controller
 *
 * @package LifterLMS_Groups/Classes/REST
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.17
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_REST_Groups_Controller class.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.6 Added `$request` parameter to the `prepare_links()` method.
 * @since 1.0.1 Allow group leaders to update group information.
 */
class LLMS_Groups_REST_Groups_Controller extends LLMS_REST_Posts_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'groups';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = 'llms_group';


	/**
	 * Schema properties available for ordering the collection.
	 *
	 * @var string[]
	 */
	protected $orderby_properties = array(
		'id',
		'title',
		'date_created',
		'date_updated',
		'order',
		'relevance',
	);

	/**
	 * Get the Section's schema, conforming to JSON Schema.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return array
	 */
	public function get_item_schema() {

		$schema = parent::get_item_schema();

		// Update language.
		$schema['properties']['title']['description'] = __( 'Group Title', 'lifterlms-groups' );

		// Update defaults.
		$schema['properties']['content']['required'] = false;

		$schema['properties']['slug']['arg_options']['sanitize_callback'] = array( $this, 'sanitize_slug' );

		// Add additional props.
		$schema['properties']['banner'] = array(
			'description' => __( 'Post ID of the attachment post for the group banner.', 'lifterlms-groups' ),
			'type'        => 'integer',
			'context'     => array( 'view', 'edit' ),
			'arg_options' => array(
				'sanitize_callback' => 'absint',
				'validate_callback' => array( $this, 'validate_attachment_id' ),
			),
		);
		$schema['properties']['logo']   = array(
			'description' => __( 'Post ID of the attachment post for the group logo.', 'lifterlms-groups' ),
			'type'        => 'integer',
			'context'     => array( 'view', 'edit' ),
			'arg_options' => array(
				'sanitize_callback' => 'absint',
				'validate_callback' => array( $this, 'validate_attachment_id' ),
			),
		);
		$schema['properties']['post']   = array(
			'description' => __( 'Post ID of the course or membership accessible by the group.', 'lifterlms-groups' ),
			'type'        => 'integer',
			'context'     => array( 'view', 'edit' ),
			'required'    => true,
			'arg_options' => array(
				'sanitize_callback' => 'absint',
				'validate_callback' => array( $this, 'validate_post_id' ),
			),
		);

		$visibility_opts                    = array_keys( llms_groups()->get_integration()->get_visibility_options() );
		$schema['properties']['visibility'] = array(
			'description' => __( 'Visibility of the group profile.', 'lifterlms-groups' ),
			'type'        => 'string',
			'default'     => $visibility_opts[0],
			'enum'        => $visibility_opts,
			'context'     => array( 'view', 'edit' ),
		);

		// Remove unnecessary props.
		$remove = array(
			'status',
			'comment_status',
			'password',
			'ping_status',
			'featured_media',
		);
		foreach ( $remove as $prop ) {
			unset( $schema['properties'][ $prop ] );
		}

		return $schema;
	}

	/**
	 * Prepares a single post for create or update.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.17 By default create groups with `publish` status.
	 *
	 * @param WP_REST_Request $request  Request object.
	 * @return array|WP_Error Array of llms post args or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {

		$prepared_item = parent::prepare_item_for_database( $request );
		$schema        = $this->get_item_schema();

		// If we're creating a new item, make the group publish by default.
		if ( ! isset( $request['id'] ) ) {
			$prepared_item['post_status'] = 'publish';
		}

		// Post ID.
		if ( ! empty( $schema['properties']['post'] ) && isset( $request['post'] ) ) {
			$prepared_item['post_id'] = $request['post'];
		}

		// Visibility.
		if ( ! empty( $schema['properties']['visibility'] ) && isset( $request['visibility'] ) ) {
			$prepared_item['visibility'] = $request['visibility'];
		}

		// Banner.
		if ( ! empty( $schema['properties']['banner'] ) && isset( $request['banner'] ) ) {
			if ( is_numeric( $request['banner'] ) ) {
				$prepared_item['banner'] = $request['banner'];
			} elseif ( isset( $request['banner']['id'] ) ) {
				$prepared_item['banner'] = $request['banner']['id'];
			}
		}

		/**
		 * Filters the group data for an insert.
		 *
		 * @since 1.0.0-beta.1
		 *
		 * @param array           $prepared_item Array of course item properties prepared for database.
		 * @param WP_REST_Request $request       Full details about the request.
		 * @param array           $schema        The item schema.
		 */
		return apply_filters( 'llms_rest_pre_insert_group', $prepared_item, $request, $schema );
	}

	/**
	 * Determine if current user has permission to update the group.
	 *
	 * @since 1.0.1
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$object = $this->get_object( (int) $request['id'] );
		if ( is_wp_error( $object ) ) {
			return $object;
		}

		$post_type_object = get_post_type_object( $this->post_type );
		$post_type_name   = $post_type_object->labels->name;

		if ( ! current_user_can( 'manage_group_information', $object->id ) ) {
			// Translators: %s = The post type name.
			return llms_rest_authorization_required_error( sprintf( __( 'Sorry, you are not allowed to update %s as this user.', 'lifterlms-groups' ), $post_type_name ) );

		}

		return true;
	}
	/**
	 * Prepare a single object output for response.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param LLMS_Group      $group  Course object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	protected function prepare_object_for_response( $group, $request ) {

		$data = parent::prepare_object_for_response( $group, $request );

		$data['post'] = $group->get( 'post_id' );

		$data['visibility'] = $group->get( 'visibility' );

		$data['banner'] = array(
			'id'         => $group->get( 'banner' ),
			'source_url' => $group->get_banner(),
		);

		$data['logo'] = array(
			'id'         => absint( get_post_thumbnail_id( $group->get( 'id' ) ) ),
			'source_url' => $group->get_logo(),
		);

		/**
		 * Filters the group data for a response.
		 *
		 * @since 1.0.0-beta.9
		 *
		 * @param array           $data    Array of group properties prepared for response.
		 * @param LLMS_Group      $group   Course object.
		 * @param WP_REST_Request $request Full details about the request.
		 */
		return apply_filters( 'llms_rest_prepare_group_object_response', $data, $group, $request );
	}


	/**
	 * Prepare links for the request.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.6 Added `$request` parameter.
	 *
	 * @param LLMS_Group      $group Group oblect.
	 * @param WP_REST_Request $request Request object.
	 * @return array Links for the given object.
	 */
	protected function prepare_links( $group, $request ) {

		$links = parent::prepare_links( $group, $request );

		unset( $links['content'] );

		return $links;
	}

	/**
	 * Sanitize callback for group slug
	 *
	 * Sanitizes according to default sanitization for a slug and additionally
	 * returns an error if the slug is already used by another group.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  string          $value User-submitted slug.
	 * @param  WP_REST_Request $request REST request instance.
	 * @return WP_Error|string Error object if there was an error or the sanitized slug.
	 */
	public function sanitize_slug( $value, $request = null ) {

		$value = parent::sanitize_slug( $value );

		if ( ! is_wp_error( $value ) ) {

			global $wpdb;

			$id  = $request ? absint( $request->get_param( 'id' ) ) : 0;
			$res = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d LIMIT 1", $value, LLMS_Groups_Post_Type::POST_TYPE, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( $res ) {
				$value = new WP_Error(
					'llms_rest_invalid_slug',
					// Translators: %s = user-submitted slug.
					sprintf( __( 'The web address slug "%s" is not available.', 'lifterlms-groups' ), $value )
				);
			}
		}

		return $value;
	}

	/**
	 * Update additional group fields.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param LLMS_Group      $group         Group instance.
	 * @param WP_REST_Request $request       Full details about the request.
	 * @param array           $schema        The item schema.
	 * @param array           $prepared_item Array.
	 * @param bool            $creating      Optional. Whether we're in creation or update phase. Default true (create).
	 * @return bool|WP_Error True on success or false if nothing to update, WP_Error object if something went wrong during the update.
	 */
	protected function update_additional_object_fields( $group, $request, $schema, $prepared_item, $creating = true ) {

		if ( ! empty( $schema['properties']['logo'] ) && isset( $request['logo'] ) ) {

			$logo_id = 0;
			if ( is_numeric( $request['logo'] ) ) {
				$logo_id = $request['logo'];
			} elseif ( isset( $request['logo']['id'] ) ) {
				$logo_id = $request['logo']['id'];
			}

			return $this->handle_featured_media( $logo_id, $group->get( 'id' ) );

		}

		return true;
	}

	/**
	 * Validation callback for `banner` and `logo` properties.
	 *
	 * Ensures that the submitted value is the ID of a WP Attachment post.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  int $value User-submitted value.
	 * @return boolean
	 */
	public function validate_attachment_id( $value ) {

		return wp_get_attachment_url( $value ) ? true : false;
	}


	/**
	 * Validation callback for `post` property.
	 *
	 * Ensures that the submitted value is the ID of Course or Membership post.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  int $value User-submitted value.
	 * @return boolean
	 */
	public function validate_post_id( $value ) {

		$post = get_post( $value );
		return ( $post && in_array( $post->post_type, array( 'course', 'llms_membership' ), true ) );
	}
}
