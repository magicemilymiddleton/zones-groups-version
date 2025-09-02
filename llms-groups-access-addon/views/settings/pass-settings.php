<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;

echo '<div class="wrap">';
echo '<h1>' . esc_html__( 'License  Debug Tools', 'llms-groups-access-addon' ) . '</h1>';

// Fetch 10 most recently modified Licenses
$recent_passes = get_posts([
    'post_type'      => 'llms_access_pass',
    'posts_per_page' => 10,
    'orderby'        => 'modified',
    'order'          => 'DESC',
]);

echo '<h2>' . esc_html__( 'Recent Licenses', 'llms-groups-access-addon' ) . '</h2>';
if ( empty( $recent_passes ) ) {
    echo '<p>No Licenses found.</p>';
} else {
    echo '<table class="widefat"><thead><tr>';
    echo '<th>ID</th><th>Title</th><th>Buyer</th><th>Redeemed?</th><th>Items</th>';
    echo '</tr></thead><tbody>';
    foreach ( $recent_passes as $pass ) {
        $buyer     = get_post_meta( $pass->ID, 'buyer_id', true );
        $redeemed  = get_post_meta( $pass->ID, 'llmsgaa_redeemed', true ) === '1' ? '✅' : '❌';
        $items     = get_post_meta( $pass->ID, 'llmsgaa_pass_items', true );
        $item_data = is_array( $items ) ? esc_html( wp_json_encode( $items ) ) : '';
        echo '<tr>';
        echo '<td>' . esc_html( $pass->ID ) . '</td>';
        echo '<td><a href="' . esc_url( get_edit_post_link( $pass->ID ) ) . '">' . esc_html( $pass->post_title ) . '</a></td>';
        echo '<td>' . esc_html( $buyer ) . '</td>';
        echo '<td>' . $redeemed . '</td>';
        echo '<td><code style="white-space:pre-wrap;">' . $item_data . '</code></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

echo '</div>';
