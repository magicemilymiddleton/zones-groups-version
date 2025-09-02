<?php
$products = array_filter( array_map( 'trim', explode( ',', $sku_list ) ) );

echo '<div class="wrap">';
echo '<h1>' . esc_html__( 'SKU ↔ Course Mapping', 'llms-groups-access-addon' ) . '</h1>';
echo '<form method="post" action="options.php">';
settings_fields( 'llmsgaa-settings' );
do_settings_sections( 'llmsgaa-settings' );

// SKU list field
echo '<h2>' . esc_html__( 'Define Your SKUs', 'llms-groups-access-addon' ) . '</h2>';
echo '<table class="form-table"><tbody>';
echo '<tr><th scope="row"><label for="llmsgaa_sku_list">'
    . esc_html__( 'SKU List (comma separated)', 'llms-groups-access-addon' )
    . '</label></th><td>';
echo '<textarea id="llmsgaa_sku_list" name="llmsgaa_sku_list" rows="3" class="large-text">'
    . esc_textarea( $sku_list ) . '</textarea>';
echo '<p class="description">'
    . esc_html__( 'Enter all SKUs you want to map, separated by commas.', 'llms-groups-access-addon' )
    . '</p>';
echo '</td></tr>';
echo '</tbody></table>';

// SKU → course mapping
echo '<h2>' . esc_html__( 'Map SKUs to Courses/Memberships', 'llms-groups-access-addon' ) . '</h2>';
echo '<table class="form-table"><tbody>';
foreach ( $products as $sku ) {
    $current = $sku_map[ $sku ] ?? '';
    echo '<tr><th>' . esc_html( $sku ) . '</th><td>';
    echo '<select name="llmsgaa_sku_map[' . esc_attr( $sku ) . ']">';
    echo '<option value="">' . esc_html__( '— none —', 'llms-groups-access-addon' ) . '</option>';
    foreach ( $posts as $p ) {
        $sel = selected( $current, $p->ID, false );
        echo '<option value="' . esc_attr( $p->ID ) . '" ' . $sel . '>'
            . esc_html( $p->post_title ) . '</option>';
    }
    echo '</select></td></tr>';
}
echo '</tbody></table>';

submit_button( __( 'Save Mappings', 'llms-groups-access-addon' ) );
echo '</form></div>';
