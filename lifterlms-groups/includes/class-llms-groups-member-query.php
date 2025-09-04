<?php
/**
 * Perform queries for Group members
 *
 * Utilizes LLMS_Student_Query to perform look ups.
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Member_Query class
 *
 * @since 1.0.0-beta.1
 */
class LLMS_Groups_Member_Query {

	/**
	 * Instance of the main student query.
	 *
	 * @var LLMS_Student_Query
	 */
	protected $query;

	/**
	 * Construct a new query
	 *
	 * @since 1.0.0-beta.1
	 *
	 * @param int   $group_id WP_Post ID of the group.
	 * @param array $args     {
	 *     Additional query arguments.
	 *
	 *     @type string[] $group_role Limit results to members having one of the supplied groups. Accepts "member", "leader", and "admin".
	 *     @type int      $page       Page number of results. Default: 1.
	 *     @type int      $per_page   Number of results to return per page. Default: 25.
	 *     @type array    $sort       Array of sorting columns => directions.
	 * }
	 */
	public function __construct( $group_id, $args = array() ) {

		$args = $this->parse_args( $group_id, $args );

		if ( ! empty( $args['group_role'] ) || ! empty( $args['id'] ) ) {
			add_filter( 'llms_student_query_select', array( $this, 'mod_select_sql' ), 10, 2 );
			add_filter( 'llms_student_query_having', array( $this, 'mod_having_sql' ), 10, 2 );
		}

		$this->query = new LLMS_Student_Query( $args );

		if ( ! empty( $args['group_role'] ) || ! empty( $args['id'] ) ) {
			remove_filter( 'llms_student_query_select', array( $this, 'mod_select_sql' ), 10 );
			remove_filter( 'llms_student_query_having', array( $this, 'mod_having_sql' ), 10 );
		}
	}

	/**
	 * Alias for main LLMS_Student_Query `get_students()` method.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @return LLMS_Student[]
	 */
	public function get_members() {
		return $this->query->get_students();
	}

	/**
	 * Alias for the main LLMS_Student_Query `get_results()` method.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @return obj[]
	 */
	public function get_results() {
		return $this->query->get_results();
	}

	/**
	 * Return the main LLMS_Student_Query
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @return LLMS_Student_Query
	 */
	public function get_query() {
		return $this->query;
	}

	/**
	 * Modify the sql SELECT string of the main LLMS_Student_Query
	 *
	 * If a `group_role` parameter is set this will modify the query to pull in the student's
	 * group role.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  string             $sql   Preexisting SQL SELECT string.
	 * @param  LLMS_Student_Query $query Instance of the query object.
	 * @return string
	 */
	public function mod_having_sql( $sql, $query ) {

		if ( $query->get( 'group_role' ) ) {
			$roles = implode( ',', array_map( array( $query, 'escape_and_quote_string' ), $query->get( 'group_role' ) ) );
			$sql  .= " AND group_role IN ( {$roles} )";
		}

		if ( $query->get( 'id' ) ) {
			$sql .= ' AND id = ' . $query->get( 'id' );
		}

		return $sql;
	}

	/**
	 * Modify the sql HAVING string of the main LLMS_Student_Query
	 *
	 * If a `group_role` parameter is set this will modify the query to
	 * pull ensure only students with a qualifying role are included
	 * in the query results.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param  string             $sql   Preexisting SQL SELECT string.
	 * @param  LLMS_Student_Query $query Instance of the query object.
	 * @return string
	 */
	public function mod_select_sql( $sql, $query ) {

		$post_ids = implode( ',', array_map( 'absint', $query->get( 'post_id' ) ) );

		global $wpdb;
		$select = "SELECT meta_value
			       FROM {$wpdb->prefix}lifterlms_user_postmeta
			      WHERE 1
			        AND meta_key = '_group_role'
			        AND user_id = id
			        AND post_id IN ( $post_ids )
			   ORDER BY updated_date DESC
			      LIMIT 1";

		$sql .= ", ( {$select} ) AS `group_role`";

		return $sql;
	}

	/**
	 * Parse query arguments.
	 *
	 * Everything else is passed to LLMS_Student_Query and parsed there.
	 *
	 * @since  1.0.0-beta.1
	 *
	 * @param int   $group_id WP_Post ID of the group.
	 * @param array $args     Query arguments. See constructor for definitions.
	 * @return array
	 */
	protected function parse_args( $group_id, $args ) {

		$args = wp_parse_args(
			$args,
			array(
				'post_id'    => $group_id,
				'group_role' => array(),
			)
		);

		// Validate submitted group roles.
		if ( ! empty( $args['group_role'] ) ) {

			// Ensure an array.
			$args['group_role'] = ! is_array( $args['group_role'] ) ? array( $args['group_role'] ) : $args['group_role'];

			// Strip any invalid roles.
			$args['group_role'] = array_intersect( array_keys( llms_groups_get_roles() ), $args['group_role'] );

		}

		if ( ! empty( $args['id'] ) ) {
			$args['id'] = absint( $args['id'] );
		}

		return $args;
	}
}
