<?php
/**
 * REST Group Seats Controller
 *
 * @package LifterLMS_Groups/Classes/REST
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_REST_Group_Seats_Controller class
 *
 * @since 1.0.0-beta.1
 */
class LLMS_Groups_REST_Group_Seats_Controller extends LLMS_REST_Controller {

	/**
	 * Base Resource
	 *
	 * @var string
	 */
	protected $rest_base = 'groups/(?P<id>[\d]+)/seats';

	/**
	 * Determine if the current user has permission to perform the request.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_permissions( $request ) {
		if ( ! current_user_can( 'manage_lifterlms' ) ) {
			return llms_rest_authorization_required_error();
		}
		return true;
	}

	/**
	 * Get the API Key's schema, conforming to JSON Schema.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return array
	 */
	public function get_item_schema() {

		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'llms_group_seats',
			'type'       => 'object',
			'properties' => array(
				'open'  => array(
					'description' => __( 'Number of remaining seats.', 'lifterlms-groups' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'total' => array(
					'description' => __( 'Number of available seats.', 'lifterlms-groups' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'used'  => array(
					'description' => __( 'Number of used seats.', 'lifterlms-groups' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
			),
		);
	}

	/**
	 * Get object
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param int $group_id WP_Post ID of the group.
	 * @return LLMS_Group
	 */
	protected function get_object( $group_id ) {
		return llms_get_post( $group_id );
	}

	/**
	 * Prepare an object for response.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param LLMS_Group      $object  Group object.
	 * @param WP_REST_Request $request Request object.
	 * @return int[]
	 */
	protected function prepare_object_for_response( $object, $request ) {
		return $object->get_seats();
	}

	/**
	 * Register routes.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the group. The WP User ID.', 'lifterlms-groups' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_get_item_params(),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_endpoint_args_for_item_schema( 'PUT' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Update the object in the database with prepared data.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param array           $prepared Prepared item data.
	 * @param WP_REST_Request $request Request object.
	 * @return LLMS_Group
	 */
	protected function update_object( $prepared, $request ) {

		$group = $this->get_object( $request->get_param( 'id' ) );

		if ( isset( $prepared['total'] ) ) {
			$group->set( 'seats', $prepared['total'] );
		}

		return $group;
	}
}
