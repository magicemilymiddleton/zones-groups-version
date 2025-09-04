<?php
/**
 * Manage the admin UI for group checkout enabled access plans.
 *
 * @package LifterLMS/Classes/Admin
 *
 * @since 1.1.0
 */

defined( 'ABSPATH' ) || exit;

class LLMS_Groups_Admin_Access_Plan_Settings_Admin_UI {
	public function __construct() {
		add_action( 'llms_access_plan_mb_after_row_five', array( $this, 'add_group_access_plan_settings' ), 10, 3 );
		add_action( 'llms_access_plan_dialog_after_pre_sale', array( $this, 'add_group_access_plan_dialog' ) );
		add_filter( 'llms_access_plan_dialog_show_group_addon_option', '__return_false' );
	}

	public function add_group_access_plan_dialog() {
		?>
		<button class="template" data-template="group">
			<strong><?php echo esc_html( __( 'Group Access', 'lifterlms-groups' ) ); ?></strong>
			<span><?php echo esc_html( __( 'Allow a buyer to purchase lifetime access for a group of people.', 'lifterlms-groups' ) ); ?></span>
		</button>
		<?php
	}

	public function add_group_access_plan_settings( $plan, $id, $order ) {
		if ( class_exists( 'LifterLMS_WooCommerce' ) ) {
			return;
		}

		?>
		<div class="llms-plan-row-group-checkout">

			<div class="llms-metabox-field d-1of3">
				<label for="_llms_plans[<?php echo esc_attr( $order ); ?>][group_enrolment]">
					<?php esc_html_e( 'Use for Group Enrolment', 'lifterlms-groups' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Indicate if the plan is used for group enrolment.', 'lifterlms-groups' ); ?></span>
					<span class="tip--top-right" data-tip="<?php esc_attr_e( 'Indicate if the plan is used for group enrolment.', 'lifterlms-groups' ); ?>">
						<i class="fa fa-question-circle"></i>
					</span>
				</label>
				<select id="_llms_plans[<?php echo esc_attr( $order ); ?>][group_enrolment]" data-controller-id="llms-group-enrolment" name="_llms_plans[<?php echo esc_attr( $order ); ?>][group_enrolment]"<?php echo ( $plan ) ? '' : ' disabled="disabled"'; ?>>
					<option value="no"<?php selected( 'no', $plan ? $plan->get( 'group_enrolment' ) : '' ); ?>><?php esc_html_e( 'No, do not use for group enrolment', 'lifterlms-groups' ); ?></option>
					<option value="yes"<?php selected( 'yes', $plan ? $plan->get( 'group_enrolment' ) : '' ); ?>><?php esc_html_e( 'Yes, use for group enrolment', 'lifterlms-groups' ); ?></option>
				</select>
			</div>

			<div class="clear"></div>

			<section data-controller="llms-group-enrolment" data-value-is="yes">
				<div class="llms-metabox-field d-1of2">
					<label for="_llms_plans[<?php echo esc_attr( $order ); ?>][group_enrolment_seats_type]">
						<?php esc_html_e( 'Seat Selection', 'lifterlms-groups' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Indicate if the number of seats is fixed or variable.', 'lifterlms-groups' ); ?></span>
						<span class="tip--top-right" data-tip="<?php esc_attr_e( 'Indicate if the number of seats is fixed or variable.', 'lifterlms-groups' ); ?>">
							<i class="fa fa-question-circle"></i>
						</span>
					</label>
					<select id="_llms_plans[<?php echo esc_attr( $order ); ?>][group_enrolment_seats_type]" data-controller-id="llms-group-enrolment-seats-type" name="_llms_plans[<?php echo esc_attr( $order ); ?>][group_enrolment_seats_type]"<?php echo ( $plan ) ? '' : ' disabled="disabled"'; ?>>
						<option value="fixed"<?php selected( 'fixed', $plan ? $plan->get( 'group_enrolment_seats_type' ) : '' ); ?>><?php esc_html_e( 'Fixed - Set a specific number of seats.', 'lifterlms-groups' ); ?></option>
						<option value="variable"<?php selected( 'variable', $plan ? $plan->get( 'group_enrolment_seats_type' ) : '' ); ?>><?php esc_html_e( 'Variable - Allow number of seats to be selected at checkout.', 'lifterlms-groups' ); ?></option>
					</select>
				</div>

				<div class="llms-metabox-field d-1of4" data-controller="llms-group-enrolment-seats-type" data-value-is="fixed">
					<label for="_llms_plans[<?php echo esc_attr( $order ); ?>][group_seat_count]">
						<?php esc_html_e( 'Seats', 'lifterlms-groups' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Set the number of group seats included in this access plan.', 'lifterlms-groups' ); ?></span>
						<span class="tip--top-right" data-tip="<?php esc_attr_e( 'Set the number of group seats included in this access plan.', 'lifterlms-groups' ); ?>">
							<i class="fa fa-question-circle"></i>
						</span>
					</label>
					<input id="_llms_plans[<?php echo esc_attr( $order ); ?>][group_seat_count]" name="_llms_plans[<?php echo esc_attr( $order ); ?>][group_seat_count]" min="1" placeholder="1" required="required" type="number"<?php echo $plan ? ' value="' . ( absint( $plan->get( 'group_seat_count' ) ) ? esc_attr( absint( $plan->get( 'group_seat_count' ) ) ) : '1' ) . '"' : ' value="1" disabled="disabled"'; ?>>
				</div>

				<div class="llms-metabox-field d-1of4" data-controller="llms-group-enrolment-seats-type" data-value-is="variable">
					<label for="_llms_plans[<?php echo esc_attr( $order ); ?>][group_seat_count_min]">
						<?php esc_html_e( 'Min Seats', 'lifterlms-groups' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Set the minimum number of group seats included in this access plan.', 'lifterlms-groups' ); ?></span>
						<span class="tip--top-right" data-tip="<?php esc_attr_e( 'Set the minimum number of group seats included in this access plan.', 'lifterlms-groups' ); ?>">
							<i class="fa fa-question-circle"></i>
						</span>
					</label>
					<input id="_llms_plans[<?php echo esc_attr( $order ); ?>][group_seat_count_min]" name="_llms_plans[<?php echo esc_attr( $order ); ?>][group_seat_count_min]" min="1" placeholder="1" required="required" type="number"<?php echo $plan ? ' value="' . ( absint( $plan->get( 'group_seat_count_min' ) ) ? esc_attr( absint( $plan->get( 'group_seat_count_min' ) ) ) : '1' ) . '"' : ' value="1" disabled="disabled"'; ?>>
				</div>

				<div class="llms-metabox-field d-1of4" data-controller="llms-group-enrolment-seats-type" data-value-is="variable">
					<label for="_llms_plans[<?php echo esc_attr( $order ); ?>][group_seat_count_max]">
						<?php esc_html_e( 'Max Seats', 'lifterlms-groups' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Set the maximum number of group seats included in this access plan.', 'lifterlms-groups' ); ?></span>
						<span class="tip--top-right" data-tip="<?php esc_attr_e( 'Set the maximum number of group seats included in this access plan.', 'lifterlms-groups' ); ?>">
							<i class="fa fa-question-circle"></i>
						</span>
					</label>
					<input id="_llms_plans[<?php echo esc_attr( $order ); ?>][group_seat_count_max]" name="_llms_plans[<?php echo esc_attr( $order ); ?>][group_seat_count_max]" min="1" placeholder="1" required="required" type="number"<?php echo $plan ? ' value="' . ( absint( $plan->get( 'group_seat_count_max' ) ) ? esc_attr( absint( $plan->get( 'group_seat_count_max' ) ) ) : '1' ) . '"' : ' value="1" disabled="disabled"'; ?>>
				</div>

			</section>
			<div class="clear"></div>
		</div>

		<?php
	}
}

return new LLMS_Groups_Admin_Access_Plan_Settings_Admin_UI();
