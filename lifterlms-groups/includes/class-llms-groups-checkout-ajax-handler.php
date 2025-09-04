<?php
/**
 * Groups checkout AJAX Handler.
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.1.0
 */

defined( 'ABSPATH' ) || exit;

class LLMS_Groups_Checkout_AJAX_Handler {
	public function __construct() {
		add_action( 'wp_ajax_validate_seat_count', array( $this, 'handle_validate_seat_count' ) );
		add_action( 'wp_ajax_nopriv_validate_seat_count', array( $this, 'handle_validate_seat_count' ) );

		add_action( 'wp_ajax_validate_seat_count_access_plan', array( $this, 'handle_validate_seat_count' ) );
		add_action( 'wp_ajax_nopriv_validate_seat_count_access_plan', array( $this, 'handle_validate_seat_count' ) );
	}

	public function handle_validate_seat_count() {
		if ( ! class_exists( 'LLMS_AJAX' ) ) {
			return;
		}

		if ( ! method_exists( $this, $_REQUEST['action'] ) ) {
			return;
		}

		check_ajax_referer( LLMS_AJAX::NONCE );

		$response = $this->{$_REQUEST['action']}( $_REQUEST );

		if ( $response instanceof WP_Error ) {
			wp_send_json(
				array(
					'code'    => $response->get_error_code(),
					'message' => $response->get_error_message(),
				)
			);
		}

		wp_send_json_success( $response );

		die();
	}

	private function validate_seat_count_access_plan( $request ) {

		if ( $error = $this->validate_request_params( $request ) ) {
			return $error;
		}

		$plan = new LLMS_Access_Plan( $request['plan_id'] );

		$seat_count = absint( $request['seat_count'] );

		if ( $error = $this->validate_seat_count_value( $seat_count, $plan ) ) {
			return $error;
		}

		$seat_count = $this->ensure_seat_count_in_range( $seat_count, $plan );

		$this->set_seat_count_in_session( $seat_count, $plan );

		ob_start();
		llms_template_access_plan( $plan );

		return array(
			'seat_count'       => intval( $request['seat_count'] ),
			'access_plan_html' => ob_get_clean(),
		);
	}

	private function validate_request_params( $request ) {
		$error = new WP_Error();
		if ( empty( $request['seat_count'] ) ) {
			$error->add( 400, __( 'Missing required parameters', 'lifterlms' ) );
			return $error;
		}

		if ( absint( $request['seat_count'] ) < 1 ) {
			$error->add( 400, __( 'Seat count must be greater than 0', 'lifterlms' ) );
			return $error;
		}

		if ( empty( $request['plan_id'] ) ) {
			$error->add( 'error', __( 'Please enter a plan ID.', 'lifterlms' ) );
			return $error;
		}

		return null;
	}

	private function validate_seat_count_value( $seat_count, $plan ) {
		$error = new WP_Error();
		if ( 'variable' !== $plan->get( 'group_enrolment_seats_type' ) ) {
			$error->add( 'error', __( 'Seat count is not available for this plan.', 'lifterlms-groups' ) );

			return $error;
		}

		if ( ! $seat_count ) {
			$error->add( 'error', __( 'Invalid seat count.', 'lifterlms-groups' ) );

			return $error;
		}
	}

	private function ensure_seat_count_in_range( $seat_count, $plan ) {
		if ( $seat_count > $plan->get( 'group_seat_count_max' ) ) {
			$seat_count = $plan->get( 'group_seat_count_max' );
		}

		if ( $seat_count < $plan->get( 'group_seat_count_min' ) ) {
			$seat_count = $plan->get( 'group_seat_count_min' );
		}

		return $seat_count;
	}

	private function set_seat_count_in_session( $seat_count, $plan ) {
		llms()->session->set(
			'llms_group_seat_count_' . intval( $plan->get( 'id' ) ),
			$seat_count
		);
	}

	private function validate_seat_count( $request ) {

		if ( $error = $this->validate_request_params( $request ) ) {
			return $error;
		}

		$plan = new LLMS_Access_Plan( $request['plan_id'] );

		$seat_count = absint( $request['seat_count'] );

		if ( $error = $this->validate_seat_count_value( $seat_count, $plan ) ) {
			return $error;
		}

		$seat_count = $this->ensure_seat_count_in_range( $seat_count, $plan );

		$this->set_seat_count_in_session( $seat_count, $plan );

		$coupon = null;

		if ( llms()->session->get( 'llms_coupon' ) ) {
			$coupon = new LLMS_Coupon( llms()->session->get( 'llms_coupon' )['coupon_id'] );
		}

		ob_start();
		llms_get_template(
			'checkout/form-coupon.php',
			array(
				'coupon' => $coupon,
			)
		);
		$coupon_html = ob_get_clean();

		ob_start();
		llms_get_template(
			'checkout/form-gateways.php',
			array(
				'coupon'           => $coupon,
				'gateways'         => llms()->payment_gateways()->get_enabled_payment_gateways(),
				'selected_gateway' => llms()->payment_gateways()->get_default_gateway(),
				'plan'             => $plan,
			)
		);
		$gateways_html = ob_get_clean();

		ob_start();
		llms_get_template(
			'checkout/form-summary.php',
			array(
				'coupon'  => $coupon,
				'plan'    => $plan,
				'product' => $plan->get_product(),
			)
		);
		$summary_html = ob_get_clean();

		return array(
			'seat_count'    => intval( $request['seat_count'] ),
			'coupon_html'   => $coupon_html,
			'gateways_html' => $gateways_html,
			'summary_html'  => $summary_html,
		);
	}
}

new LLMS_Groups_Checkout_AJAX_Handler();
