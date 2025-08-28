<?php

namespace LLMSGAA\Common;

if ( ! defined( 'ABSPATH' ) ) exit;

class Assets {

    public static function init_hooks() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend' ] );
    }

    public static function enqueue_frontend() {
        $css_files = [
            'main'          => 'main.css',
            'llmsgaa-passes'=> 'llmsgaa-passes.css',
            'llmsgaa-custom'=> 'llmsgaa-custom.css',
        ];

        $js_files = [
            'cart-repeater' => 'cart-repeater.js',
            'llmsgaa-passes'=> 'llmsgaa-passes.js',
            'llmsgaa-utils' => 'llmsgaa-utils.js',
            // 'members'       => 'members.js', // Removed – no longer needed
        ];

        foreach ( $css_files as $handle => $filename ) {
            $path = plugin_dir_path( LLMSGAA_PLUGIN_FILE ) . 'public/css/' . $filename;
            if ( file_exists( $path ) ) {
                wp_enqueue_style(
                    'llmsgaa-' . $handle,
                    plugins_url( 'public/css/' . $filename, LLMSGAA_PLUGIN_FILE ),
                    [],
                    filemtime( $path )
                );
            }
        }

        foreach ( $js_files as $handle => $filename ) {
            $path = plugin_dir_path( LLMSGAA_PLUGIN_FILE ) . 'public/js/' . $filename;
            if ( file_exists( $path ) ) {
                wp_enqueue_script(
                    'llmsgaa-' . $handle,
                    plugins_url( 'public/js/' . $filename, LLMSGAA_PLUGIN_FILE ),
                    [ 'jquery' ],
                    filemtime( $path ),
                    true
                );
            }
        }
    }
}
