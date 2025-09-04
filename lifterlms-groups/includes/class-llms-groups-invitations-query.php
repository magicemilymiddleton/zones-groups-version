<?php
/**
 * Query Group Invitations
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.5
 * @version 1.0.0-beta.19
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query LifterLMS Students for a given course / membership
 *
 * @since 1.0.0-beta.5
 *
 * @param int             $page     Get results by page number
 * @param int             $per_page Number of results per page. Default: 25.
 * @param array[]         $sort     Sorting options. Default array( 'id' => 'ASC' ).
 *
 * @param string|string[] $email    Filter results to match at least one of the specified email addresses.
 * @param string          $open     Determines how open invitations are treated in the results. Accepts:
 *                                    "include" (default): includes open invitations in the results.
 *                                    "exclude": excludes open invitations from the results set.
 *                                    "only": Include __only__ open invitations in the results.
 * @param int|int[]       $group    Filter results to match at least one of the specified group IDs.
 * @param string|string[] $role     Filter results to match at least one of the specified group roles.
 * @param int|int[]       $include  Include only results in the specified list of invitation IDs.
 * @param int|int[]       $exclude  Exclude results in the specified list of invitation IDs.
 */
class LLMS_Groups_Invitations_Query extends LLMS_Database_Query {

	/**
	 * Identify the extending query
	 *
	 * @var string
	 */
	protected $id = 'group_invitations';

	/**
	 * Retrieve default arguments for a student query
	 *
	 * @since 1.0.0-beta.5
	 *
	 * @return array
	 */
	protected function get_default_args() {

		$args = array(
			'group'   => array(),
			'email'   => array(),
			'role'    => array(),
			'include' => array(),
			'exclude' => array(),
			'open'    => true,
		);

		$args = wp_parse_args( $args, parent::get_default_args() );

		/**
		 * Filter default query arguments
		 *
		 * @since 1.0.0-beta.5
		 *
		 * @param array                         $args Default query arguments.
		 * @param LLMS_Groups_Invitations_Query $this Query object.
		 */
		return apply_filters( 'llms_group_invitations_query_default_args', $args, $this );
	}

	/**
	 * Retrieve an array of LLMS_Group_Invitation objects for the given result set returned by the query
	 *
	 * @since 1.0.0-beta.5
	 *
	 * @return array
	 */
	public function get_invitations() {

		$invitations = array();
		$results     = $this->get_results();

		if ( $results ) {
			foreach ( $results as $result ) {
				$invitations[] = llms_groups()->invitations()->get( $result->id );
			}
		}

		if ( $this->get( 'suppress_filters' ) ) {
			return $invitations;
		}

		/**
		 * Filter the invitations result set
		 *
		 * @since 1.0.0-beta.5
		 *
		 * @param LLMS_Group_Invitation[]       $invitations Array of invitation objects.
		 * @param LLMS_Groups_Invitations_Query $this        Query object.
		 */
		return apply_filters( 'llms_group_invitations_query_get_invitations', $invitations, $this );
	}

	/**
	 * Parses and sanitize arguments
	 *
	 * @since 1.0.0-beta.5
	 *
	 * @return void
	 */
	protected function parse_args() {

		// Sanitize ID arrays.
		foreach ( array( 'group', 'include', 'exclude' ) as $key ) {
			$this->arguments[ $key ] = $this->sanitize_id_array( $this->arguments[ $key ] );
		}

		// Sanitize Email input.
		if ( ! empty( $this->arguments['email'] ) ) {

			// Allow single emails to be submitted as a string.
			if ( ! is_array( $this->arguments['email'] ) ) {
				$this->arguments['email'] = array( $this->arguments['email'] );
			}

			// Sanitize and remove empties.
			$this->arguments['email'] = array_filter( array_map( 'sanitize_email', $this->arguments['email'] ) );

		}

		// Validate roles.
		if ( ! empty( $this->arguments['role'] ) ) {
			$valid_roles = array_keys( llms_groups_get_roles() );

			// Allow single roles to be passed in as a string.
			if ( ! is_array( $this->arguments['role'] ) ) {
				$this->arguments['role'] = array( $this->arguments['role'] );
			}

			$this->arguments['role'] = array_intersect( $valid_roles, $this->arguments['role'] );

		}
	}

	/**
	 * Prepare the SQL for the query
	 *
	 * @since 1.0.0-beta.5
	 * @since 1.0.0-beta.19 Renamed `preprare_query()` to `prepare_query()`.
	 *
	 * @return string
	 */
	protected function prepare_query() {

		global $wpdb;

		return "SELECT SQL_CALC_FOUND_ROWS id
				FROM {$wpdb->prefix}lifterlms_group_invitations
				{$this->sql_where()}
				{$this->sql_orderby()}
				{$this->sql_limit()};";
	}

	/**
	 * SQL "where" clause for the query
	 *
	 * @since 1.0.0-beta.5
	 *
	 * @return string
	 */
	protected function sql_where() {

		global $wpdb;

		$sql = array(
			'WHERE 1',
		);

		// Include by group or invitation id.
		$cols = array(
			'group'   => 'group_id',
			'include' => 'id',
		);
		foreach ( $cols as $key => $col ) {
			$ids = $this->get( $key );
			if ( $ids ) {
				$prepared = implode( ',', $ids );
				$sql[]    = "{$col} IN ({$prepared})";
			}
		}

		// Exclude by invitation id.
		$excludes = $this->get( 'exclude' );
		if ( $excludes ) {
			$prepared = implode( ',', $excludes );
			$sql[]    = "id NOT IN ({$prepared})";
		}

		// Include by email and role.
		foreach ( array( 'email', 'role' ) as $key ) {
			$items = $this->get( $key );
			if ( $items ) {
				$prepared = implode( ',', array_map( array( $this, 'escape_and_quote_string' ), $items ) );
				$sql[]    = "{$key} IN ({$prepared})";
			}
		}

		$open = $this->get( 'open' );
		if ( 'exclude' === $open ) {
			// Exclude open invitations.
			$sql[] = "email != ''";
		} elseif ( 'only' === $open ) {
			// Include only open invitations.
			$sql[] = "email = ''";
		}

		// Make it into a string.
		$sql = implode( ' AND ', $sql );

		/**
		 * Filter the default "WHERE" clause of the query
		 *
		 * @since 1.0.0-beta.5
		 *
		 * @param string                        $sql  The raw SQL query fragment.
		 * @param LLMS_Groups_Invitations_Query $this Query object.
		 */
		return apply_filters( 'llms_group_invitations_query_where', $sql, $this );
	}
}
