<?php
/**
 * REST Group Members Controller
 *
 * @package LifterLMS_Groups/Classes/REST
 *
 * @since 1.0.0-beta.6
 * @version 1.0.0-beta.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_REST_Group_Members_Controller class.
 *
 * @since 1.0.0-beta.6
 */
class LLMS_Groups_REST_Group_Members_Controller extends LLMS_REST_Students_Controller {

	/**
	 * Resource ID or Name.
	 *
	 * @var string
	 */
	protected $resource_name = 'llms_group_member';

	/**
	 * Base Resource
	 *
	 * @var string
	 */
	protected $rest_base = 'groups/(?P<group_id>[\d]+)/members';

	/**
	 * Temporary array of prepared query args used to filter WP_User_Query.
	 *
	 * @var array
	 */
	private $prepared_query_args = array();

	/**
	 * Temporary request object used to filter WP_User_Query
	 *
	 * @var WP_REST_Request
	 */
	private $temp_request_obj = null;

	/**
	 * Determine if the current user can view group members based on the group's visibility settings.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param int $group_id WP_Post ID of the group.
	 * @return bool
	 */
	protected function can_user_view_members( $group_id ) {

		if ( current_user_can( 'manage_lifterlms' ) ) {
			return true;
		}

		$group      = llms_get_post( $group_id );
		$visibility = $group->get( 'visibility' );
		$user       = get_current_user_id();

		// Closed group, the current user must be enrolled in the group to view members.
		if ( 'closed' === $visibility && ! llms_is_user_enrolled( $user, $group_id ) ) {
			return false;

			// Private group, the current user must be a site user to view group members.
		} elseif ( 'private' === $visibility && ! $user ) {
			return false;
		}

		// Okay.
		return true;
	}

	/**
	 * Determine if the current user can view the requested student.
	 *
	 * This method intentionally always returns `true` because we determine whether or not an API consumer
	 * can read member data based on the user's group association and the group's settings.
	 *
	 * This logic is handled in `$this->can_user_view_members()`.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param int $item_id WP_User id.
	 * @return bool
	 */
	protected function check_read_item_permissions( $item_id ) {
		return true;
	}

	/**
	 * Determine if current user has permission to remove a user from the group.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {

		// Any user other than the primary user is allowed to leave a group.
		if ( get_current_user_id() === $request['id'] && ! llms_group_is_user_primary_admin( $request['id'], $request['group_id'] ) ) {
			return true;

			// Current user has permissions to remove the other member.
		} elseif ( in_array( 'remove', array_keys( llms_groups_get_actions_for_member( $request['id'], $request['group_id'] ) ), true ) ) {
			return true;
		}

		return llms_rest_authorization_required_error( __( 'You are not allowed to remove this member from the group.', 'lifterlms-groups' ) );
	}

	/**
	 * Determine if current user has permission to get a user.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {

		if ( ! $this->can_user_view_members( $request['group_id'] ) ) {
			return llms_rest_authorization_required_error( __( 'You are not allowed to view this member.', 'lifterlms-groups' ) );
		} elseif ( 'edit' === $request['context'] && ! llms_groups_can_user_manage_member( get_current_user_id(), $request['id'], $request['group_id'] ) ) {
			return llms_rest_authorization_required_error( __( 'You are not allowed to view this member in edit context.', 'lifterlms-groups' ) );
		}

		return true;
	}

	/**
	 * Determine if current user has permission to list users.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {

		if ( ! $this->can_user_view_members( $request['group_id'] ) ) {
			return llms_rest_authorization_required_error( __( "You are not allowed to list this group's members.", 'lifterlms-groups' ) );
		} elseif ( 'edit' === $request['context'] && ! ( current_user_can( 'manage_group_members', $request['group_id'] ) || current_user_can( 'manage_group_managers', $request['group_id'] ) ) ) {
			return llms_rest_authorization_required_error( __( "You are not allowed to list this group's members in edit context.", 'lifterlms-groups' ) );
		}

		return true;
	}

	/**
	 * Determine if current user has permission to update a user.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {

		$available = llms_groups_get_actions_for_member( $request['id'], $request['group_id'] );

		// Removal takes place via a DELETE request.
		unset( $available['remove'] );

		if ( empty( $available ) ) {
			return llms_rest_authorization_required_error( __( 'You are not allowed to manage this member.', 'lifterlms-groups' ) );
		}

		// Current user can manage the member and the requested role is already the user's role.
		if ( llms_groups_can_user_manage_member( get_current_user_id(), $request['id'], $request['group_id'] ) && LLMS_Groups_Enrollment::get_role( $request['id'], $request['group_id'] ) === $request['group_role'] ) {
			return true;
		}

		// Current user cannot switch the member to the requested role.
		if ( ! in_array( $request['group_role'], array_keys( $available ), true ) ) {
			// Translators: %s = Requested group member role id.
			return llms_rest_authorization_required_error( sprintf( __( 'You are not allowed to make this member\'s role "%s".', 'lifterlms-groups' ), $request['group_role'] ) );
		}

		return true;
	}

	/**
	 * Delete the members group enrollment.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param obj             $object  Instance of the object from $this->get_object().
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error true when the object is removed, WP_Error on failure.
	 */
	protected function delete_object( $object, $request ) {

		$id = $object->get( 'id' );

		$res = LLMS_Groups_Enrollment::delete( $id, $request['group_id'], $request['trigger'] );
		if ( ! $res ) {
			return llms_rest_bad_request_error( __( 'The user could not be removed from the group.', 'lifterlms-groups' ) );
		}

		return true;
	}

	/**
	 * Retrieves the query params for the object's collection.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {

		$params = parent::get_collection_params();

		$safelist = array(
			'context',
			'page',
			'per_page',
			'order',
			'orderby',
			'include',
			'exclude',
		);

		foreach ( array_keys( $params ) as $prop ) {
			if ( ! in_array( $prop, $safelist, true ) ) {
				unset( $params[ $prop ] );
			}
		}

		$params['group_roles'] = array(
			'description' => __( 'Filter members by group role', 'lifterlms-groups' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
				'enum' => $this->get_roles_enum(),
			),
		);

		return $params;
	}

	/**
	 * Retrieve the item schema.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @return array
	 */
	public function get_item_schema() {

		$schema = $this->filter_schema_properties( parent::get_item_schema() );

		$schema['properties']['group_role'] = array(
			'description' => __( 'Group member role.', 'lifterlms-groups' ),
			'type'        => 'string',
			'enum'        => $this->get_roles_enum(),
			'context'     => array( 'view', 'edit' ),
			'required'    => true,
		);

		return $schema;
	}

	/**
	 * Retrieve a query object based on arguments from a `get_items()` (collection) request.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param array           $prepared Array of collection arguments.
	 * @param WP_REST_Request $request  Request object.
	 * @return WP_User_Query
	 */
	protected function get_objects_query( $prepared, $request ) {

		$this->prepared_query_args = $prepared;
		$this->temp_request_obj    = $request;
		add_action( 'pre_user_query', array( $this, 'get_objects_query_pre' ) );

		$query = parent::get_objects_query( $prepared, $request );

		$this->prepared_query_args = array();
		$this->temp_request_obj    = null;
		remove_action( 'pre_user_query', array( $this, 'get_objects_query_pre' ) );

		return $query;
	}

	/**
	 * Callback for WP_User_Query "pre_user_query" action.
	 *
	 * Adds select fields and a having clause to ensure users are enrolled in the specified group
	 * and to check against the `group_roles` collection query args.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @link https://developer.wordpress.org/reference/hooks/pre_user_query/
	 *
	 * @param WP_User_Query $query Query object.
	 * @return void
	 */
	public function get_objects_query_pre( $query ) {

		global $wpdb;

		$group_id = absint( $this->temp_request_obj['group_id'] );

		// Add a having clause to the where query so we can check against the values added by our subqueries.
		$query->query_where .= ' Having 1';

		// Add a subquery to check for the enrollment status into the group.
		$query->query_fields .= ", (
			SELECT meta_value
			FROM {$wpdb->prefix}lifterlms_user_postmeta
			WHERE user_id = {$wpdb->users}.ID
			  AND post_id = {$group_id}
			  AND meta_key = '_status'
			ORDER BY updated_date DESC
			LIMIT 1
		) AS group_{$group_id}_status";

		$query->query_where .= " AND group_{$group_id}_status = 'enrolled'";

		if ( ! empty( $this->prepared_query_args['group_roles'] ) ) {
			$this->get_objects_query_pre_handle_roles( $query, $group_id );
		}
	}

	/**
	 * Modifications for the WP_User_Query related to user role filtering
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param WP_User_Query $query    Query object.
	 * @param int           $group_id WP_Post ID of the group.
	 * @return void
	 */
	protected function get_objects_query_pre_handle_roles( $query, $group_id ) {

		global $wpdb;

		// If primary admin is requested.
		$primary = in_array( 'primary_admin', $this->prepared_query_args['group_roles'], true );
		if ( false !== $primary ) {

			// Remove the role since it doesn't actually exist.
			unset( $this->prepared_query_args['group_roles'][ $primary ] );

			// Ensure admin is in the list.
			$this->prepared_query_args['group_roles'][] = 'admin';
			$this->prepared_query_args['group_roles']   = array_unique( $this->prepared_query_args['group_roles'] );

			$query->query_from .= " JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$group_id} AND {$wpdb->posts}.post_author = {$wpdb->users}.ID";

		}

		// Add a subquery to ensure the user has one of the roles specified in the query args.
		$roles = array_map( array( $this, '_escape_and_quote_string' ), $this->prepared_query_args['group_roles'] );
		$roles = implode( ', ', $roles );

		$query->query_fields .= ", (
			SELECT meta_value
			FROM {$wpdb->prefix}lifterlms_user_postmeta
			WHERE user_id = {$wpdb->users}.ID
			  AND post_id = {$group_id}
			  AND meta_key = '_group_role'
			LIMIT 1
		) AS group_{$group_id}_role";
		$query->query_where  .= " AND group_{$group_id}_role IN ( {$roles} )";
	}

	/**
	 * Retrieve a list of accepted roles used when updating a member's role
	 *
	 * This method adds an additional (unregistered and invalid) group member role: "primary_admin".
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @return string[]
	 */
	protected function get_roles_enum() {

		return array_merge(
			array_keys( llms_groups_get_roles() ),
			array( 'primary_admin' )
		);
	}

	/**
	 * Modifies the schema (retrieved from the parent class) to output only properties used by the model
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param array $schema Schema array.
	 * @return array
	 */
	protected function filter_schema_properties( $schema ) {

		// Whitelist properties expected by our model.
		$safelist = array(
			'avatar_urls',
			'email',
			'id',
			'name',
			'nickname',
		);

		// Remove any properties not specified in $safelist.
		foreach ( $schema['properties'] as $prop => &$data ) {

			if ( ! in_array( $prop, $safelist, true ) ) {
				unset( $schema['properties'][ $prop ] );
				continue;
			}

			// Remaining properties all become readonly.
			$data['readonly'] = true;

		}

		return $schema;
	}

	/**
	 * Retrieve arguments for removing a member from a group.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @return array
	 */
	public function get_delete_item_args() {
		return array(
			'trigger' => array(
				'type'        => 'string',
				'description' => __( 'The group enrollment trigger.', 'lifterlms-groups' ),
				'default'     => 'any',
			),
		);
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param LLMS_Student    $student Student object.
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	protected function prepare_links( $student, $request ) {

		$links = parent::prepare_links( $student, $request );

		// Remove unnecessary items from the parent.
		unset( $links['enrollments'], $links['progress'] );

		// Add link to the group.
		$links['group'] = array(
			'href' => str_replace( '/members', '', rest_url( sprintf( '/%1$s/%2$s', $this->namespace, $this->rest_base ) ) ),
		);

		// Replace placeholder with actual group id in all links.
		foreach ( $links as &$link ) {
			$link['href'] = str_replace( '(?P<group_id>[\d]+)', $request['group_id'], $link['href'] );
		}

		// Add a link to the student.
		$links['student'] = array(
			'href' => rest_url( sprintf( '/%1$s/students/%2$d', $this->namespace, $this->get_object_id( $student ) ) ),
		);

		return $links;
	}

	/**
	 * Prepare an object for response.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param LLMS_Student    $object  Student object.
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	protected function prepare_object_for_response( $object, $request ) {

		$prepared = parent::prepare_object_for_response( $object, $request );

		$role = LLMS_Groups_Enrollment::get_role( $object->get( 'id' ), $request['group_id'] );

		if ( 'admin' === $role && llms_group_is_user_primary_admin( $object->get( 'id' ), $request['group_id'] ) ) {
			$role = 'primary_admin';
		}

		$prepared['group_role'] = $role;

		return $prepared;
	}

	/**
	 * Register routes.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args'   => $this->get_path_params( array( 'id' ) ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => $this->get_path_params(),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_get_item_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
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

	/**
	 * Retrieve an array of path parameters for use in route registration.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param string[] $exclude Specify parameters to be excluded from the returned array.
	 * @return array[]
	 */
	protected function get_path_params( $exclude = array() ) {

		$args = array(
			'group_id' => array(
				'description'       => __( 'Unique identifier for the group. The WP Post ID.', 'lifterlms-groups' ),
				'type'              => 'integer',
				'validate_callback' => 'is_llms_group',
			),
			'id'       => array(
				'description'       => __( 'Unique identifier for the group member. The WP User ID.', 'lifterlms-groups' ),
				'type'              => 'integer',
				'validate_callback' => array( $this, 'validate_member_id' ),
			),
		);

		foreach ( $exclude as $prop ) {
			unset( $args[ $prop ] );
		}

		return $args;
	}

	/**
	 * Updates additional information not handled by WP Core insert/update user functions
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param int             $object_id WP User id.
	 * @param array           $prepared  Prepared item data.
	 * @param WP_REST_Request $request   Request object.
	 * @return LLMS_Abstract_User_Data|WP_error
	 */
	protected function update_additional_data( $object_id, $prepared, $request ) {

		$object = $this->get_object( $object_id );

		if ( is_wp_error( $object ) ) {
			return $object;
		}

		LLMS_Groups_Enrollment::update_role( $object_id, $request['group_id'], $prepared['group_role'] );

		return $object;
	}

	/**
	 * Validate the path parameter "id" (member/user id).
	 *
	 * A valid member id must exist as a WP_User and be enrolled in the group.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @param int             $value   WP User ID.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name ("id").
	 * @return bool
	 */
	public function validate_member_id( $value, $request, $param ) {

		$user = get_user_by( 'id', $value );
		return ( $user && llms_is_user_enrolled( $value, $request['group_id'] ) );
	}

	/**
	 * SQL Escape and add quotes around a string.
	 *
	 * This method will be removed without a deprecation warnings in a future release, see issue noted in todo below.
	 *
	 * @since 1.0.0-beta.6
	 *
	 * @access private
	 *
	 * @todo This method can be removed when this issue is resolved in the core: https://github.com/gocodebox/lifterlms/issues/1027.
	 *
	 * @param string $input Input string.
	 * @return string
	 */
	public function _escape_and_quote_string( $input ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- I know but still.
		return "'" . esc_sql( $input ) . "'";
	}
}
