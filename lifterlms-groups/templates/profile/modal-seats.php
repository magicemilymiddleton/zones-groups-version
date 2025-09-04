<?php
/**
 * Single Group profile seats management modal
 *
 * @package LifterLMS_Groups/Templates/Profile
 *
 * @since 1.0.0-beta.1
 * @version 1.0.0-beta.8
 *
 * @property LLMS_Group $group Group object.
 */

$seats = $group->get_seats();

defined( 'ABSPATH' ) || exit;
?>

<?php if ( current_user_can( 'manage_group_seats', $group->get( 'id' ) ) ) : ?>

	<div class="llms-group-modal llms-group-seats-modal" id="llms-group-seats-modal" aria-hidden="true">

		<div class="llms-group-modal--overlay" tabindex="-1" data-micromodal-close>

			<div class="llms-group-modal--container" role="dialog" aria-modal="true" aria-labelledby="llms-group-seats-modal-title" >

				<header class="llms-group-modal--header">
					<h2 id="llms-group-seats-modal-title"><?php _e( 'Manage Seats', 'lifterlms-groups' ); ?></h2>
					<button class="llms-group-button ghost on-light-bg" data-micromodal-close>
						<span class="screen-reader-text"><?php _e( 'Close', 'lifterlms-groups' ); ?></span>
						<i class="fa fa-times" aria-hidden="true"></i>
					</button>
				</header>

				<div class="llms-group-modal--content llms-form-fields flush" id="llms-group-seats-modal-content">

					<form>

						<table>
							<thead>
								<th><?php _e( 'Used', 'lifterlms-groups' ); ?></th>
								<th><?php _e( 'Total', 'lifterlms-groups' ); ?></th>
								<th><?php _e( 'Open', 'lifterlms-groups' ); ?></th>
							</thead>
							<tbody>
								<td><h3><span id="llms-group-seats-used"><?php echo $seats['used']; ?></span>/</h3></td>
								<td>
									<?php
									llms_form_field(
										array(
											'type'  => 'number',
											'value' => $seats['total'],
											'id'    => 'llms_group_seats',
										)
									);
									?>
								</td>
								<td><h3>=<span id="llms-group-seats-open"><?php echo $seats['open']; ?></span></h3></td>
							</tbody>
						</table>

						<button class="llms-button-primary button-right llms-group-button" id="llms-group-save-seats" type="submit">
							<i class="fa fa-floppy-o" aria-hidden="true"></i>
							<?php _e( 'Save', 'lifterlms-groups' ); ?>
						</button>
						<div class="llms-group-error"></div>

					</form>

				</div>

			</div>

		</div>

	</div>

<?php endif; ?>
