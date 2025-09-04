<?php
/**
 * Theme Support: Twenty Twenty-One
 *
 * @package LifterLMS_Groups/ThemeSupport/Classes
 *
 * @since 1.0.0-beta.15
 * @version [verion]
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_TwentyTwentyOne class
 *
 * @since 1.0.0-beta.15
 */
class LLMS_Groups_TwentyTwentyOne {

	/**
	 * Static "constructor"
	 *
	 * @since 1.0.0-beta.15
	 *
	 * @return void
	 */
	public static function init() {

		add_filter( 'llms_groups_theme_settings', array( __CLASS__, 'theme_settings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_inline_styles' ) );
	}

	/**
	 * Modifiy theme related settings
	 *
	 * @since 1.0.0-beta.15
	 *
	 * @param array $settings Array of theme settings.
	 * @return array
	 */
	public static function theme_settings( $settings ) {
		$settings['banner_dimensions'] = array( 1240, 280 );
		return $settings;
	}

	/**
	 * Enqueue inline styles on the frontend
	 *
	 * @since 1.0.0-beta.15
	 *
	 * @return void
	 */
	public static function add_inline_styles() {
		wp_add_inline_style( 'twenty-twenty-one-style', self::generate_inline_styles() );
	}


	/**
	 * Generate inline CSS
	 *
	 * @since 1.0.0-beta.15
	 *
	 * @return string
	 */
	protected static function generate_inline_styles() {

		// Transparent buttons.
		$styles = array( '#llms-group-upload-banner, .llms-group-card-header .llms-group-button:not(:hover):not(:active):not(.has-background) { background-color: transparent }' );

		// Nav menu items and links color.
		$styles[] = '.llms-groups-card-list .llms-group a, .llms-group-modal .llms-group-modal--container a, .llms-group-menu-link, .llms-group-card-footer a { color:inherit !important }';

		// No text decoration on focused card links.
		$styles[] = '.llms-groups-card-list .llms-group a:focus:not(.wp-block-button__link):not(.wp-block-file__button){ text-decoration: none }';

		// Tagify placeholders.
		$styles[] = '.is-dark-theme .tagify__input:before { color: var( --global--color-background ) !important }';

		// Add background color and color to qualifying elements.
		$styles[] = LLMS_Theme_Support::get_css(
			array(
				'.llms-groups-card-list .llms-group',
				'.llms-group-card-header',
				'.llms-group-profile-nav',
				'.llms-group-card-footer',
				'.llms-group-modal .llms-group-modal--container',
			),
			array(
				'color'            => 'var( --global--color-background )',
				'background-color' => 'var( --global--color-secondary )',
				'border-color'     => 'var( --global--color-secondary )',
			),
			'.is-dark-theme'
		);

		$styles[] = LLMS_Theme_Support::get_css(
			array(
				'.llms-groups-card-list .llms-group:hover',
			),
			array(
				'color'            => 'var( --global--color-secondary )',
				'background-color' => 'var( --global--color-background )',
			),
			'.is-dark-theme'
		);

		return implode( "\r", $styles );
	}
}

return LLMS_Groups_TwentyTwentyOne::init();
