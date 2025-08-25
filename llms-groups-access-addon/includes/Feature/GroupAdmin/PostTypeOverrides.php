<?php
namespace LLMSGAA\Feature\GroupAdmin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PostTypeOverrides {

    public static function init() {
    add_action( 'wp_loaded', [ __CLASS__, 'override_llms_group_post_type' ], 5 );
   
    }

    public static function override_llms_group_post_type() {
        if ( ! post_type_exists( 'llms_group' ) ) {
            return;
        }

        $existing = get_post_type_object( 'llms_group' );

        register_post_type( 'llms_group', array_merge( (array) $existing, [
            'public'        => true,
            'has_archive'   => false,
            'show_in_rest'  => false,
            'rewrite'       => [ 'slug' => 'group', 'with_front' => false ],
        ] ) );
    }
}
