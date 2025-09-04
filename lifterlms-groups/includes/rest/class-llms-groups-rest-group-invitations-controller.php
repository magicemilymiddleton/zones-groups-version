<?php
/**
 * REST Group Invitations Controller
 *
 * @package LifterLMS_Groups/Classes/REST
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.19
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_REST_Group_Invitations_Controller class.
 *
 * @since 1.0.0-beta.1
 * @since 1.0.0-beta.5 Validate against number of open seats when attempting to create a new invitation.
 *                     Implement invitation listing.
 * @since 1.0.0-beta.6 Added `$request` parameter to the `prepare_links()` method.
 */
class LLMS_Groups_REST_Group_Invitations_Controller extends LLMS_REST_Controller {

	/**
	 * Base Resource
	 *
	 * @var string
	 */
	protected $rest_base = 'groups/(?P<group_id>[\d]+)/invitations';

	/**
	 * Check if the authenticated user can perform the request action.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @param  string          $cap     Requested user capability.
	 * @return boolean
	 */
	protected function check_permissions( $request, $cap ) {
		return current_user_can( $cap, $request['group_id'] ) ? true : llms_rest_authorization_required_error();
	}

	/**
	 * Check if a given request has access to create an item.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function create_item_permissions_check( $request ) {
		$cap = 'member' === $request['role'] ? 'manage_group_members' : 'manage_group_managers';
		return $this->check_permissions( $request, $cap );
	}

	/**
	 * Check if a given request has access to delete an item.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0 Moved most of the logic into the new function `llms_groups_invitation_can_be_deleted`.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return llms_groups_invitation_can_be_deleted( $request['id'], $request['group_id'] )
			? true : llms_rest_authorization_required_error();
	}

	/**
	 * Check if a given request has access to read an item.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_permissions( $request, 'manage_group_members' );
	}

	/**
	 * Check if a given request has access to read items.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_permissions( $request, 'manage_group_members' );
	}

	/**
	 * Create item.
	 *
	 * @since 1.0.0-beta.2
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {

		$group = llms_get_post( $request['group_id'] );
		$seats = $group->get_seats();

		if ( $seats['open'] <= 0 ) {
			return llms_rest_bad_request_error( __( 'Cannot create a new invitation: there are no open seats remaining.', 'lifterlms-groups' ) );
		}

		return parent::create_item( $request );
	}

	/**
	 * Insert the prepared data into the database.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param array           $prepared Prepared item data.
	 * @param WP_REST_Request $request Request object.
	 * @return obj Object Instance of object from $this->get_object().
	 */
	protected function create_object( $prepared, $request ) {

		$obj = llms_groups()->invitations()->create( $prepared );

		return $this->get_object( $obj->get( 'id' ) );
	}

	/**
	 * Delete the object.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param obj             $object Instance of the object from $this->get_object().
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error true when the object is removed, WP_Error on failure.
	 */
	protected function delete_object( $object, $request ) {

		return $object->delete();
	}

	/**
	 * Retrieves the query params for the objects collection.
	 *
	 * @since 1.0.0-beta.5
	 *
	 * @return array
	 */
	public function get_collection_params() {

		$query_params = parent::get_collection_params();

		$query_params['email'] = array(
			'description'       => __( 'Limit results to a list of email addresses. Accepts a single email or a comma-separated list of emails.', 'lifterlms-groups' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'string',
			),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$query_params['role'] = array(
			'description'       => __( 'Limit results to a list of roles. Accepts a single role or a comma-separated list of roles.', 'lifterlms-groups' ),
			'type'              => 'array',
			'items'             => array(
				'type' => 'string',
				'enum' => array_keys( llms_groups_get_roles() ),
			),
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $query_params;
	}

	/**
	 * Get the Invitation's schema.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return array
	 */
	public function get_item_schema() {

		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'llms_group_invitations',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier for the invitation.', 'lifterlms-groups' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'accept_link' => array(
					'description' => __( 'URL used to accept the invitation.', 'lifterlms-groups' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'email'       => array(
					'description' => __( 'Email address of the invited group member.', 'lifterlms-groups' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'view', 'edit' ),
				),
				'role'        => array(
					'description' => __( 'Group member role.', 'lifterlms-groups' ),
					'type'        => 'string',
					'enum'        => array_keys( llms_groups_get_roles() ),
					'context'     => array( 'view', 'edit' ),
					'default'     => 'member',
				),
			),
		);
	}

	/**
	 * Retrieve a Group Invitation Object.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param int|obj $id Invitation ID or an invitation object.
	 * @return WP_Error|LLMS_Group_Invitation
	 */
	protected function get_object( $id ) {

		$id = is_numeric( $id ) ? $id : $this->get_object_id( $id );

		$obj = llms_groups()->invitations()->get( $id );
		return $obj ? $obj : llms_rest_not_found_error();
	}

	/**
	 * Retrieve a query object based on arguments from a `get_items()` (collection) request.
	 *
	 * @since 1.0.0-beta.5
	 *
	 * @param array           $prepared Array of collection arguments.
	 * @param WP_REST_Request $request  Request object.
	 * @return object
	 */
	protected function get_objects_query( $prepared, $request ) {

		return new LLMS_Groups_Invitations_Query( $prepared );
	}

	/**
	 * Retrieve an array of objects from the result of $this->get_objects_query().
	 *
	 * @since 1.0.0-beta.5
	 *
	 * @param LLMS_Groups_Invitations_Query $query Objects query result.
	 * @return obj[]
	 */
	protected function get_objects_from_query( $query ) {

		return $query->get_results();
	}

	/**
	 * Retrieve pagination information from an objects query.
	 *
	 * @since 1.0.0-beta.1
	 * @since 1.0.0-beta.19 Fixed access of protected LLMS_Abstract_Query properties.
	 *
	 * @param LLMS_Groups_Invitations_Query $query    Objects query result.
	 * @param array                         $prepared Array of collection arguments.
	 * @param WP_REST_Request               $request  Request object.
	 * @return array {
	 *     Array of pagination information.
	 *
	 *     @type int $current_page  Current page number.
	 *     @type int $total_results Total number of results.
	 *     @type int $total_pages   Total number of results pages.
	 * }
	 */
	protected function get_pagination_data_from_query( $query, $prepared, $request ) {

		return array(
			'current_page'  => $query->get( 'page' ),
			'total_results' => $query->get_found_results(),
			'total_pages'   => $query->get_max_pages(),
		);
	}

	/**
	 * Map request keys to database keys for insertion.
	 *
	 * Array keys are the request fields (as defined in the schema) and
	 * array values are the database fields.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @return array
	 */
	protected function map_schema_to_database() {

		$map = parent::map_schema_to_database();
		unset( $map['accept_link'] );
		return $map;
	}

	/**
	 * Format query arguments to retrieve a collection of objects.
	 *
	 * @since 1.0.0-beta.5
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	protected function prepare_collection_query_args( $request ) {

		$prepared = parent::prepare_collection_query_args( $request );

		$prepared['sort'] = array(
			$prepared['orderby'] => $prepared['order'],
		);

		// We can only ever query within the "current" group.
		$prepared['group'] = array( $request['group_id'] );

		unset( $prepared['context'], $prepared['orderby'], $prepared['order'] );

		return $prepared;
	}

	/**
	 * Prepare request arguments for a database insert/update.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param WP_Rest_Request $request Request object.
	 * @return array
	 */
	protected function prepare_item_for_database( $request ) {

		$prepared             = parent::prepare_item_for_database( $request );
		$prepared['group_id'] = $request['group_id'];

		return $prepared;
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 1.0.0-beta.5
	 * @since 1.0.0-beta.6 Added `$request` parameter.
	 *
	 * @param LLMS_Group_Invitation $object  Invitation object.
	 * @param WP_REST_Request       $request Request object.
	 * @return array
	 */
	protected function prepare_links( $object, $request ) {

		$links = parent::prepare_links( $object, $request );

		// Add link to the group.
		$links['group'] = array(
			'href' => str_replace( '/invitations', '', rest_url( sprintf( '/%1$s/%2$s', $this->namespace, $this->rest_base ) ) ),
		);

		// Replace placeholder with actual group id in all links.
		$group_id = $object->get( 'group_id' );
		foreach ( $links as &$link ) {
			$link['href'] = str_replace( '(?P<group_id>[\d]+)', $group_id, $link['href'] );
		}

		return $links;
	}

	/**
	 * Prepare an object for response.
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param LLMS_Group_Invitation $object Invitation object.
	 * @param WP_REST_Request       $request Request object.
	 * @return array
	 */
	protected function prepare_object_for_response( $object, $request ) {

		$prepared                = parent::prepare_object_for_response( $object, $request );
		$prepared['accept_link'] = $object->get_accept_link();
		return $prepared;
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
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the resource.', 'lifterlms-groups' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_get_item_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => $this->get_delete_item_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}
}
