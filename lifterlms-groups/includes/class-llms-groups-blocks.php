<?php
/**
 * LifterLMS Groups Blocks handler
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Groups_Blocks class.
 *
 * @since 1.0.0
 */
class LLMS_Groups_Blocks {

	/**
	 * Singleton instance.
	 *
	 * @var self $instance The class instance.
	 */
	private static $instance;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'llms_shortcode_blocks', array( $this, 'register_blocks' ) );
	}

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Array of registered blocks.
	 * @return array
	 */
	public function register_blocks( array $config ): array {
		$config['group-list'] = array(
			'render' => array( 'LLMS_Groups_Shortcode_Group_List', 'output' ),
			'path'   => LLMS_GROUPS_PLUGIN_DIR . 'assets/blocks/group-list',
		);

		return $config;
	}
}

return LLMS_Groups_Blocks::get_instance();
