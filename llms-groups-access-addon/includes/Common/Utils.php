<?php
namespace LLMSGAA\Common;

/**
 * Shared utility functions used across the plugin.
 */
class Utils {

    /**
     * Update the redeemed seat count on a given License.
     */
    public static function update_redeem( int $pass_id, int $delta ) {
        $redeemed = intval( get_post_meta( $pass_id, 'quantity_redeemed', true ) );
        $new = max( 0, $redeemed + $delta );
        update_post_meta( $pass_id, 'quantity_redeemed', $new );
    }

    /**
     * Sanitize a comma-separated list into a cleaned string.
     */
    public static function sanitize_comma_list( $input ) {
        $clean = array_map( 'sanitize_text_field', explode( ',', $input ) );
        return implode( ',', array_filter( array_map( 'trim', $clean ) ) );
    }

    /**
     * Sanitize the SKU-to-post ID mapping array.
     */
    public static function sanitize_sku_map( $input ) {
        $clean = [];
        if ( is_array( $input ) ) {
            foreach ( $input as $sku => $pid ) {
                $sku = sanitize_text_field( $sku );
                $pid = absint( $pid );
                if ( $sku !== '' && $pid > 0 ) {
                    $clean[ $sku ] = $pid;
                }
            }
        }
        return $clean;
    }

public static function sku_to_product_id( string $sku ): ?int {
    $map = get_option( 'llmsgaa_sku_map', [] );

    if ( is_string( $map ) ) {
        $map = maybe_unserialize( $map );
    }

    if ( is_array( $map ) && isset( $map[ $sku ] ) ) {
        return absint( $map[ $sku ] );
    }

    return null;
}



}
