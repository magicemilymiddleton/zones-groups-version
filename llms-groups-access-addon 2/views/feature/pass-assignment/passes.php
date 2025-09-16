<?php
/**
 * Passes View
 *
 * This renders a responsive table of Licenses with Tailwind CSS utility classes.
 */

 // Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( empty( $passes ) ) {
    echo '<p class="text-gray-600">' . esc_html__( 'No passes found.', 'llms-groups-access-addon' ) . '</p>';
    return;
}
?>

<div class="space-y-4">
    <h2 class="text-xl font-semibold text-gray-800">
        <?php esc_html_e( 'Licenses', 'llms-groups-access-addon' ); ?>
    </h2>

    <table class="min-w-full bg-white shadow-md rounded-xl overflow-hidden">
        <thead class="bg-gray-100 text-left text-sm font-semibold text-gray-700">
            <tr>
                <th class="p-3"><?php esc_html_e( 'Pass', 'llms-groups-access-addon' ); ?></th>
                <th class="p-3"><?php esc_html_e( 'Date Purchased', 'llms-groups-access-addon' ); ?></th>
                <th class="p-3"><?php esc_html_e( 'Action', 'llms-groups-access-addon' ); ?></th>
            </tr>
        </thead>
        <tbody class="text-sm text-gray-700">
            <?php foreach ( $passes as $pass ) :
                $title        = get_the_title( $pass );
                $date_created = get_the_date( 'F j, Y', $pass->ID );
                $is_redeemed  = get_post_meta( $pass->ID, 'llmsgaa_redeemed', true );
            ?>
                <tr class="border-t border-gray-200">
                    <td class="p-3">
                        <button class="text-blue-600 hover:underline llmsgaa-pass-details"
                                data-id="<?php echo esc_attr( $pass->ID ); ?>">
                            <?php echo esc_html( $title ); ?>
                        </button>
                    </td>
                    <td class="p-3"><?php echo esc_html( $date_created ); ?></td>
                    <td class="p-3">
                        <?php if ( $is_redeemed ) : ?>
                            <span class="text-green-600 font-medium">Redeemed</span>
                        <?php else : ?>
                            <button class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 llmsgaa-redeem-btn"
                                    data-pass-id="<?php echo esc_attr( $pass->ID ); ?>">
                                <?php esc_html_e( 'Redeem', 'llms-groups-access-addon' ); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modals will be inserted here by JavaScript -->
