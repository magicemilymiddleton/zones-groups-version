<?php
/**
 * LLMS_Groups_Assets class file
 *
 * @package LifterLMS_Groups/Classes
 *
 * @since 1.0.0-beta.18
 * @version 1.0.0-beta.18
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles block templates.
 *
 * @since 1.0.0-beta.18
 */
class LLMS_Groups_Block_Templates {

	/**
	 * Directory name of the block templates.
	 *
	 * @var string
	 */
	const BLOCK_TEMPLATES_DIRECTORY_NAME = 'block-templates';

	/**
	 * Block Template namespace.
	 *
	 * This is used to save templates to the DB which are stored against this value in the wp_terms table.
	 *
	 * @var string
	 */
	const BLOCK_TEMPLATES_NAMESPACE = 'lifterlms-groups/lifterlms-groups';

	/**
	 * Block Template slug prefix.
	 *
	 * @var string
	 */
	const BLOCK_TEMPLATES_PREFIX = 'llms-groups_';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0-beta.18
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'llms_block_templates_config', array( $this, 'block_templates_config' ) );
		add_filter( 'llms_blocks_php_templates_block', array( $this, 'add_php_template_blocks' ) );
		add_filter( 'llms_blocks_render_php_template_block', array( $this, 'maybe_render_group_php_template_block' ), 10, 3 );
		add_filter( 'llms_forced_block_template_slug', array( $this, 'maybe_force_block_template_slug' ) );
	}

	/**
	 * Configure block templates.
	 *
	 * @since 1.0.0-beta.18
	 *
	 * @param array $config Block templates configuration array.
	 * @return array
	 */
	public function block_templates_config( $config ) {

		return array_merge(
			$config,
			array(
				LLMS_GROUPS_PLUGIN_DIR . 'templates/' . self::BLOCK_TEMPLATES_DIRECTORY_NAME => array(
					'slug_prefix'       => self::BLOCK_TEMPLATES_PREFIX,
					'namespace'         => self::BLOCK_TEMPLATES_NAMESPACE,
					'blocks_dir'        => self::BLOCK_TEMPLATES_DIRECTORY_NAME, // Relative to the plugin's templates directory.
					'admin_blocks_l10n' => $this->block_editor_l10n(),
					'template_titles'   => $this->template_titles(),
				),
			)
		);
	}

	/**
	 * Add php templates slugs that can be render via the php-template block.
	 *
	 * @since 1.0.0-beta.18
	 *
	 * @param array $templates Templates map, where the keys are the template attribute value and the values are the php file names (w/o extension).
	 * @return array
	 */
	public function add_php_template_blocks( $templates ) {

		return array_merge(
			$templates,
			array(
				'single-llms_group' => 'content-single-llms_group',
			)
		);
	}

	/**
	 * Maybe render the single-llms_group php-template block.
	 *
	 * @since 1.0.0-beta.18
	 *
	 * @param string $block_content The block's html.
	 * @param array  $attributes    The block's array of attributes.
	 * @param array  $template      The template file basename to be rendered.
	 * @return string
	 */
	public function maybe_render_group_php_template_block( $block_content, $attributes, $template ) {

		if ( 'single-llms_group' === $attributes['template'] ) {
			ob_start();

			llms_groups_get_template( $template . '.php' );

			$block_content = ob_get_clean();
		}

		return $block_content;
	}

	/**
	 * Maybe force load a block template slug on front.
	 *
	 * @since 1.0.0-beta.18
	 *
	 * @param string $template_slug The template slug to be loaded forced.
	 * @return string
	 */
	public function maybe_force_block_template_slug( $template_slug ) {

		if ( ! empty( $template_slug ) ) {
			return $template_slug;
		}

		global $wp_query;

		if ( is_llms_group() && ! $wp_query->is_404() && is_singular( 'llms_group' ) ) {
			$template_slug = self::BLOCK_TEMPLATES_PREFIX . 'single-llms_group';
		}

		return $template_slug;
	}

	/**
	 * Returns an associative array of template titles.
	 *
	 * Keys are template slugs.
	 * Values are template titles in a human readable form.
	 *
	 * @since 1.0.0-beta.18
	 *
	 * @return array
	 */
	private function template_titles() {

		return array(
			self::BLOCK_TEMPLATES_PREFIX . 'single-llms_group' => esc_html__( 'Single Group', 'lifterlms-groups' ),
		);
	}

	/**
	 * Block Templates admin js strings.
	 *
	 * @since 1.0.0-beta.18
	 *
	 * @return string[]
	 */
	private function block_editor_l10n() {

		return array(
			'single-llms_group' => esc_html__( 'LifterLMS Group Template', 'lifterlms-groups' ),
		);
	}
}

return new LLMS_Groups_Block_Templates();
