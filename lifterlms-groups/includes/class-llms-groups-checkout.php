<?php
/**
 * Manage the admin UI for group checkout enabled access plans.
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.1.0
 */

defined( 'ABSPATH' ) || exit;

class LLMS_Groups_Checkout {
	public function __construct() {
		if ( class_exists( 'LifterLMS_WooCommerce' ) ) {
			return;
		}

		add_action( 'llms_get_access_plan_properties', array( $this, 'add_access_plan_properties' ), 10, 1 );
		add_action( 'llms_get_order_properties', array( $this, 'add_order_properties' ), 10, 1 );

		add_action( 'lifterlms_new_pending_order', array( $this, 'add_group_seat_count_to_order' ), 10, 3 );

		add_action( 'llms_acces_plan_content', array( $this, 'add_seat_count_to_access_plan_box' ), 21, 1 );
		add_action( 'llms_checkout_order_summary_end', array( $this, 'add_group_seat_count_to_order_summary' ), 10, 3 );
		add_filter( 'lifterlms_completed_transaction_redirect', array( $this, 'create_group_and_redirect' ), 10, 2 );
		add_action( 'lifterlms_order_status_completed', array( $this, 'handle_order_status_completed' ), 10, 1 );

		add_filter( 'llms_access_plan_get_price_before_formatting', array( $this, 'get_price_with_seats' ), 10, 4 );
		add_filter( 'llms_get_access_plan_price_price_with_coupon_before_formatting', array( $this, 'get_price_with_seats' ), 10, 4 );
		add_filter( 'llms_get_access_plan_trial_price_price_with_coupon_before_formatting', array( $this, 'get_price_with_seats' ), 10, 4 );
		add_filter( 'llms_get_access_plan_sale_price_price_with_coupon_before_formatting', array( $this, 'get_price_with_seats' ), 10, 4 );

		add_filter( 'llms_after_setup_pending_order', array( $this, 'add_group_seat_count_to_pending_order_data' ), 10, 2 );

		add_action( 'lifterlms_view_order_table_body', array( $this, 'add_group_seat_count_to_user_order_table' ), 10, 1 );
		add_action( 'lifterlms_order_meta_box_after_plan_information', array( $this, 'add_group_seat_count_to_admin_order_table' ), 10, 1 );
	}

	// TODO: Verify if this is even needed if we're using the session value to calculate? Just validate and add/update the request data to the session?

	public function add_group_seat_count_to_pending_order_data( $order_data, $request_data ) {
		if ( 'yes' !== $order_data['plan']->get( 'group_enrolment' ) ) {
			return $order_data;
		}

		if ( ! isset( $request_data['llms_seat_count'] ) || ! is_numeric( $request_data['llms_seat_count'] ) ) {
			return $order_data;
		}

		if ( 'fixed' === $order_data['plan']->get( 'group_enrolment_seats_type' ) ) {
			// TODO: Verify if we need to do this since it's not passed into get_price / get_price_with_coupon?
			$order_data['seat_count'] = absint( $order_data['plan']->get( 'group_seat_count' ) );

			// TODO: Delete this. Since it's fixed we can just get it off the plan and should never be from the session.
			// llms()->session->set( 'llms_group_seat_count_' . $order_data['plan']->get( 'id' ), absint( $order_data['plan']->get( 'group_seat_count' ) ) );

			return $order_data;
		}

		$seat_count = absint( $request_data['llms_seat_count'] );

		if ( ! $seat_count || $seat_count > $order_data['plan']->get( 'group_seat_count_max' ) || $seat_count < $order_data['plan']->get( 'group_seat_count_min' ) ) {
			$err = new WP_Error();
			$err->add( 'invalid-seats', __( 'Invalid seat count.', 'lifterlms-groups' ) );

			return $err;
		}

		// TODO: Do we need to add this to order data since it's not passed into methods like get_price and get_price_with_coupon?
		$order_data['seat_count'] = $seat_count;

		llms()->session->set( 'llms_group_seat_count_' . $order_data['plan']->get( 'id' ), $seat_count );

		return $order_data;
	}

	public function add_group_seat_count_to_user_order_table( $order ) {
		if ( ! $order->get( 'group_seat_count' ) ) {
			return;
		}

		?>
		<tr>
			<th><?php esc_html_e( 'Seats', 'lifterlms-groups' ); ?></th>
			<td><?php echo esc_html( $order->get( 'group_seat_count' ) ); ?></td>
		</tr>
		<?php
	}

	public function add_group_seat_count_to_admin_order_table( $order ) {
		if ( ! $order->get( 'group_seat_count' ) ) {
			return;
		}

		?>
		<div class="llms-metabox-field">
			<label><?php esc_html_e( 'Seats', 'lifterlms-groups' ); ?>:</label>
			<?php echo esc_html( $order->get( 'group_seat_count' ) ); ?>
		</div>
		<?php if ( $group = $this->get_group_from_order( $order ) ) : ?>
			<div class="llms-metabox-field">
				<label><?php esc_html_e( 'Group', 'lifterlms-groups' ); ?>:</label>
				<a href="<?php echo esc_html( get_permalink( $group->get( 'id' ) ) ); ?>"><?php echo esc_html( $group->get( 'title' ) ); ?></a>
			</div>
		<?php endif; ?>
		<?php
	}

	public function get_price_with_seats( $price, $key, $price_args, $plan ) {
		if ( 'yes' === $plan->get( 'group_enrolment' ) ) {
			// TODO: Maybe get from posted data if available? It's in the return from llms_setup_pending_order()
			$seats = $this->get_current_seat_count( $plan );

			return intval( $seats ) * $price;
		}
		return $price;
	}

	public function add_access_plan_properties( $props ) {
		$props['group_enrolment']            = 'yesno';
		$props['group_enrolment_seats_type'] = 'string';
		$props['group_seat_count']           = 'absint';
		$props['group_seat_count_min']       = 'absint';
		$props['group_seat_count_max']       = 'absint';

		return $props;
	}

	public function add_order_properties( $props ) {
		$props['group_seat_count'] = 'absint';

		return $props;
	}

	public function add_group_seat_count_to_order( $order, $user, $user_data ) {
		$plan = new LLMS_Access_Plan( $order->get( 'plan_id' ) );

		if ( 'yes' === $plan->get( 'group_enrolment' ) ) {
			$order->set( 'group_seat_count', $this->get_current_seat_count( $plan ) );
		}
	}

	private function get_default_seat_count( $plan ) {
		if ( 'variable' === $plan->get( 'group_enrolment_seats_type' ) ) {
			return intval( $plan->get( 'group_seat_count_min' ) );
		}
		return intval( $plan->get( 'group_seat_count' ) );
	}

	public function add_seat_count_to_access_plan_box( $plan ) {
		if ( 'yes' !== $plan->get( 'group_enrolment' ) ) {
			return;
		}
		?>
		<?php if ( 'variable' === $plan->get( 'group_enrolment_seats_type' ) ) : ?>

			<div class="llms-access-plan-group-seats llms-groups-seat-count-entry llms-form-fields flush">

				<?php
				llms_form_field(
					array(
						'label'       => __( 'Seats', 'lifterlms-groups' ),
						'columns'     => 12,
						'id'          => 'llms-group-seat-count-' . $plan->get( 'id' ),
						'last_column' => true,
						'required'    => false,
						'attributes'  => array(
							'min' => $plan->get( 'group_seat_count_min' ),
							'max' => $plan->get( 'group_seat_count_max' ),
						),
						'classes'     => array(
							'llms-group-seat-count',
						),
						'value'       => llms()->session->get( 'llms_group_seat_count_' . $plan->get( 'id' ) ) ? llms()->session->get( 'llms_group_seat_count_' . $plan->get( 'id' ) ) : $this->get_default_seat_count( $plan ),
						'type'        => 'number',

					)
				);
				?>
			</div>
		<?php else : ?>
			<div class="llms-access-plan-group-seats">
				<div class="llms-access-plan-group-seats-label">
					<?php esc_html_e( 'Seats:', 'lifterlms' ); ?>
					<span class="llms-access-plan-group-seats-count">
						<?php echo esc_html( $this->get_default_seat_count( $plan ) ); ?>
					</span>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	public function add_group_seat_count_to_order_summary( $plan, $product, $coupon ) {
		if ( 'yes' !== $plan->get( 'group_enrolment' ) ) {
			return;
		}
		?>
		<?php if ( 'variable' === $plan->get( 'group_enrolment_seats_type' ) ) : ?>

			<div class="llms-group-seat-entry llms-form-fields flush">

				<div class="llms-group-seat-messages"></div>

				<?php
					llms_form_field(
						array(
							'label'       => __( 'Seats', 'lifterlms-groups' ),
							'columns'     => 12,
							'id'          => 'llms-group-seat-count',
							'last_column' => true,
							'required'    => true,
							'attributes'  => array(
								'min' => $plan->get( 'group_seat_count_min' ),
								'max' => $plan->get( 'group_seat_count_max' ),
							),

							// TODO: Move this to a form checkout variable like $coupon?
							'value'       => llms()->session->get( 'llms_group_seat_count_' . $plan->get( 'id' ) ) ? llms()->session->get( 'llms_group_seat_count_' . $plan->get( 'id' ) ) : $plan->get( 'group_seat_count_min' ),
							'type'        => 'number',

						)
					);
				?>
			</div>
		<?php else : ?>
			<div class="llms-access-plan-group-seats">
				<div class="llms-access-plan-group-seats-label">
					<?php esc_html_e( 'Seats:', 'lifterlms' ); ?>
					<span class="llms-access-plan-group-seats-count">
								<?php echo esc_html( $plan->get( 'group_seat_count' ) ); ?>
							</span>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	public function create_group_and_redirect( $url, $order ) {

		$group = $this->create_group_from_order( $order );

		if ( ! $group ) {
			return $url;
		}

		return get_permalink( $group->get( 'id' ) );
	}

	private function create_group_from_order( $order ) {
		$plan = new LLMS_Access_Plan( $order->get( 'plan_id' ) );

		if ( 'yes' === $plan->get( 'group_enrolment' ) ) {

			$group = $this->get_group_from_order( $order );
			if ( $group ) {
				return $group;
			}

			$int = llms_groups()->get_integration();

			// Setup arguments.
			$args = array(
				'post_status' => 'publish',
				'post_author' => $order->get( 'user_id' ),
				// Translators: %s = Singular user-defined group name.
				'post_title'  => sprintf( __( 'New %s', 'lifterlms-groups' ), $int->get_option( 'post_name_singular' ) ),
				'meta_input'  => array(
					'_llms_visibility' => $int->get_option( 'visibility' ),
				),
			);

			$group = new LLMS_Group( 'new', $args );

			LLMS_Groups_Enrollment::add( $group->get( 'author' ), $group->get( 'id' ), 'primary_admin', 'primary_admin' );

			$group->set( 'order_id', $order->get( 'id' ) );
			$group->set( 'seats', $order->get( 'group_seat_count' ) );
			$group->set( 'post_id', $order->get( 'product_id' ) );

			return $group;
		}
	}

	protected function get_group_from_order( $order ) {

		$args  = array(
			'post_type'  => 'llms_group',          // Query posts of type llms_group
			'meta_query' => array(
				array(
					'key'     => '_llms_order_id',   // Meta key to search for
					'value'   => $order->get( 'id' ),   // The number you're looking for
					'compare' => '=',               // Comparison operator
					'type'    => 'NUMERIC',         // Ensure the value is treated as a number
				),
			),
		);
		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return false;
		}

		return new LLMS_Group( $posts[0]->ID );
	}

	public function handle_order_status_completed( $order ) {
		$this->create_group_from_order( $order );
	}

	private function get_current_seat_count( $plan ) {
		if ( 'variable' === $plan->get( 'group_enrolment_seats_type' ) ) {
			$seats = llms()->session->get( 'llms_group_seat_count_' . $plan->get( 'id' ) ) ? llms()->session->get( 'llms_group_seat_count_' . $plan->get( 'id' ) ) : $this->get_default_seat_count( $plan );
		} else {
			$seats = $this->get_default_seat_count( $plan );
		}

		return $seats;
	}
}

return new LLMS_Groups_Checkout();
